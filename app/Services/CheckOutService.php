<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\FolioItem;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * CheckOutService : Orchestration complète du check-out
 *
 * Responsabilités :
 * 1. Valider que le check-out est possible
 * 2. Finaliser les montants du booking
 * 3. Créer la facture et ses lignes
 * 4. Mettre la chambre en statut 'dirty'
 * 5. Attribuer les points fidélité
 * 6. Mettre à jour le statut de la réservation
 *
 * Tout est dans une transaction DB — si une étape échoue,
 * tout est annulé. Aucune donnée partielle en base.
 */
class CheckOutService
{
    public function __construct(
        private LoyaltyService $loyaltyService
    ) {}

    /**
     * Point d'entrée principal — appelle cette méthode depuis le Controller
     *
     * @throws \Exception si le check-out est impossible
     */
    public function process(Booking $booking): Invoice
    {
        // Validation préalable
        $this->validate($booking);

        // Tout dans une transaction DB atomique
        return DB::transaction(function () use ($booking) {
            
            // --- MINUTEUR INTELLIGENT : Recalcul de la durée réelle au check-out ---
            $this->syncDurationToNow($booking);
            // ------------------------------------------------------------------------

            // 1. Finalise les montants
            $this->finalizeAmounts($booking);

            // 2. Crée la facture
            $invoice = $this->createInvoice($booking);

            // 3. Met la chambre en "sale" (dirty)
            $booking->room->updateStatus(
                RoomStatus::DIRTY,
                "Check-out {$booking->booking_number}",
                Auth::id()
            );

            // Une recouche prévue n'a plus lieu : le client est parti, la
            // chambre attend son ménage de départ.
            \App\Models\HousekeepingAssignment::query()
                ->where('room_id', $booking->room_id)
                ->where('kind', \App\Models\HousekeepingAssignment::KIND_RECOUCHE)
                ->whereIn('status', ['pending', 'in_progress'])
                ->update(['status' => 'cancelled', 'notes' => "Départ du client ({$booking->booking_number})"]);

            // Sans relevé du mini-bar au départ, il reste dû au ménage qui suit.
            app(MinibarService::class)->auDepart($booking->fresh('room'));

            // 4. Attribue les points fidélité
            $this->loyaltyService->awardPoints($booking);

            // 5. Met à jour le booking
            $booking->update([
                'status'           => BookingStatus::COMPLETED,
                'actual_check_out' => now(),
                'checked_out_by'   => Auth::id(),
            ]);

            return $invoice;
        });
    }

    /**
     * Valide que le check-out est possible
     */
    private function validate(Booking $booking): void
    {
        if ($booking->status !== BookingStatus::CHECKED_IN) {
            throw new \LogicException(
                "Impossible de faire le check-out : statut actuel '{$booking->status->label()}'"
            );
        }

        $due = $booking->effectiveBalanceDue();
        if ($due > 0) {
            throw new \LogicException(
                "Solde impayé de " . number_format($due / 100, 0, ',', ' ') . " FCFA. " .
                    "Réglez le solde avant le check-out."
            );
        }
    }

    /**
     * Recalcule et finalise tous les montants du booking
     */
    private function finalizeAmounts(Booking $booking): void
    {
        $isAdditiveTax = $booking->hasAdditiveTax();

        // Additionne toutes les prestations du folio (hors hébergement)
        $extrasAmount = (int) $booking->folioItems()
            ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT, FolioItem::TYPE_DISCOUNT])
            ->where('is_complimentary', false)
            ->sum('total_price');

        $hasPackageFolio = $booking->folioItems()
            ->where('type', FolioItem::TYPE_OTHER)
            ->where('description', 'like', 'Formule —%')
            ->exists();
        $uninvoicedPackage = $hasPackageFolio ? 0 : (int) $booking->package_amount;

        // Remises du folio (stockées en négatif depuis le wizard, ou parfois en positif)
        $discountItems = $booking->folioItems()
            ->where('type', FolioItem::TYPE_DISCOUNT)
            ->get();
        $discountAmount = $discountItems->isNotEmpty()
            ? (int) $discountItems->sum(fn ($item) => abs((int) $item->total_price))
            : (int) $booking->discount_amount;

        $subtotal = (int) $booking->total_room_amount + $extrasAmount + $uninvoicedPackage - $discountAmount;

        if ($isAdditiveTax) {
            $taxAmount = (int) $booking->tax_amount;
            $totalAmount = $subtotal + $taxAmount;
        } else {
            $totalAmount = max(0, $subtotal);
            $taxAmount = app(TaxationService::class)->breakdown($totalAmount)->vat;
        }
        $lodgingTax = $booking->lodgingTax((int) $booking->total_nights);
        $totalAmount += $lodgingTax;

        // Paiements déjà reçus
        $paidAmount = (int) $booking->payments()
            ->where(fn ($q) => $q->whereNull('payment_origin')->orWhere('payment_origin', '!=', \App\Models\Payment::ORIGIN_POS_RECEPTION))
            ->where(fn ($q) => $q->whereNull('invoice_id')->orWhereIn('invoice_id', Invoice::where('kind',
                Invoice::KIND_SEJOUR)->select('id')))
            ->where('status', 'completed')
            ->sum('amount');

        $booking->update([
            'extras_amount'   => $extrasAmount,
            'discount_amount' => $discountAmount,
            'tax_amount'      => $taxAmount,
            'tourist_tax_amount' => $lodgingTax,
            'total_amount'    => $totalAmount,
            'paid_amount'     => $paidAmount,
            'balance_due'     => max(0, $totalAmount - $paidAmount),
        ]);
    }

    /**
     * Crée la facture finale avec toutes ses lignes
     */
    private function createInvoice(Booking $booking): Invoice
    {
        $nightlyRate = $booking->effectiveNightlyRate();
        $legacyTax = $booking->hasAdditiveTax() ? (int) $booking->tax_amount : 0;

        $invoice = Invoice::create([
            'booking_id'     => $booking->id,
            'customer_id'    => $booking->customer_id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'invoice_date'   => now(),
            'subtotal'       => $booking->total_amount - $booking->tourist_tax_amount - $legacyTax,
            'tax_amount'     => $booking->tax_amount,
            'tourist_tax_amount' => $booking->tourist_tax_amount,
            'total_amount'   => $booking->total_amount,
            'paid_amount'    => $booking->paid_amount,
            'balance_due'    => $booking->balance_due,
            'status'         => $booking->balance_due <= 0 ? 'paid' : 'sent',
            'legal_notes'    => 'République du Cameroun',
        ]);

        // Ligne hébergement
        $items = [[
            'description' => "Hébergement — {$booking->total_nights} nuit(s) × " .
                number_format($nightlyRate / 100, 0, ',', ' ') . " FCFA",
            'quantity'    => $booking->total_nights,
            'unit_price'  => $nightlyRate,
            'total_price' => $booking->total_room_amount,
            'tax_rate'    => 0.0,
            'tax_amount'  => 0,
            'category'    => 'room',
            'source_type' => Booking::class,
            'source_id'   => $booking->id,
        ]];
        $discount = (int) $booking->discount_amount;

        // Lignes du folio (extras). La ligne d'hébergement du folio est déjà
        // portée par la ligne ci-dessus : la reprendre ferait payer la chambre deux fois à l'écran.
        foreach ($booking->folioItems()->whereNotIn('type', [FolioItem::TYPE_PAYMENT, FolioItem::TYPE_ROOM, FolioItem::TYPE_DISCOUNT])
            ->where('is_complimentary', false)->orderBy('id')->get() as $item) {
            $items[] = [
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'unit_price'  => $item->unit_price,
                'total_price' => $item->total_price,
                'tax_rate'    => 0.0,
                'tax_amount'  => 0,
                'category'    => $item->type,
                'source_type' => FolioItem::class,
                'source_id'   => $item->id,
                'tax_rate_code' => $item->vat_rate_code,
                'vat_reason' => $item->vat_reason,
            ];
        }

        $package = (int) $invoice->subtotal + $discount - array_sum(array_column($items, 'total_price'));
        if ($package > 0) {
            $items[] = [
                'description' => 'Formule du séjour', 'quantity' => 1, 'unit_price' => $package,
                'total_price' => $package, 'category' => 'package',
            ];
        }
        if ($discount > 0) {
            $items[] = [
                'description' => 'Remise du séjour', 'quantity' => 1, 'unit_price' => -$discount,
                'total_price' => -$discount, 'category' => FolioItem::TYPE_DISCOUNT,
            ];
        }

        $taxation = app(TaxationService::class);
        if ($legacyTax > 0) {
            $items[] = [
                'description' => 'Taxe historique du séjour', 'quantity' => 1, 'unit_price' => $legacyTax,
                'total_price' => $legacyTax, 'category' => 'legacy_tax',
            ];
        } else {
            $taxTotal = 0;
            foreach (collect($items)->groupBy(fn ($item) => $item['tax_rate_code'] ?? '', preserveKeys: true) as $code => $group) {
                $tax = $taxation->breakdownLines($group->pluck('total_price')->all(), $code === '' ? null : $code);
                foreach (array_values($group->keys()->all()) as $offset => $index) {
                    $line = $tax['lines'][$offset];
                    $items[$index]['tax_rate'] = $line->ratePercentage();
                    $items[$index]['tax_rate_code'] = $line->rateCode;
                    $items[$index]['tax_amount'] = $line->vat;
                    $items[$index]['tax_surtax_amount'] = $line->surtax;
                }
                $taxTotal += $tax['total']->vat;
            }
            $invoice->update(['tax_amount' => $taxTotal]);
            $booking->update(['tax_amount' => $taxTotal]);
        }

        if ($invoice->tourist_tax_amount > 0) {
            $units = max(1, (int) $booking->adults_count + (int) $booking->children_count) * (int) $booking->total_nights;
            $items[] = [
                'description' => 'Taxe de séjour — ' . $units . ' nuitée(s) personne',
                'quantity' => $units, 'unit_price' => intdiv($invoice->tourist_tax_amount, max(1, $units)),
                'total_price' => $invoice->tourist_tax_amount, 'category' => 'tourist_tax',
                'tax_rate_code' => 'HORS_CHAMP',
                'vat_reason' => 'Taxe de séjour à reverser',
            ];
        }
        $invoice->items()->createMany($items);

        return $invoice;
    }

    /**
     * Génère un numéro de facture séquentiel par année.
     * Format : F-2026-000001
     *
     * Aucun établissement n'est passé en paramètre : une base ne contient qu'un
     * seul établissement, la séquence est donc déjà propre à lui. La signature
     * en attendait un — hérité d'un modèle où toutes les filiales partageaient
     * une base — et exigeait un entier là où `bookings.tenant_id` vaut toujours
     * NULL. Tout check-out échouait donc en erreur 500 au moment de facturer.
     */
    private function generateInvoiceNumber(): string
    {
        return Invoice::prochainNumero();
    }

    public function recalculateTotals(Booking $booking): void
    {
        $this->finalizeAmounts($booking);
    }

    public function syncDurationToNow(Booking $booking, ?string $defaultCheckOutTime = null,
        ?CarbonInterface $at = null): void
    {
        if ($booking->status !== BookingStatus::CHECKED_IN) {
            return;
        }

        $checkOutClock = $defaultCheckOutTime ?? app(RoomAvailabilityService::class)->checkOutTime();
        $reference = ($at ?? now())->copy();

        if ($booking->hasOverstayedCheckout($checkOutClock, $reference)) {
            $overstayNights = $booking->overstayNights($checkOutClock, $reference);
            $addedRoomAmount = $overstayNights * $booking->effectiveNightlyRate();
            $newTotalNights = (int) $booking->total_nights + $overstayNights;
            $newRoomAmount = (int) $booking->total_room_amount + $addedRoomAmount;
            $newScheduledOut = $booking->scheduledCheckOutAt($checkOutClock)
                ->copy()
                ->addDays($overstayNights)
                ->startOfDay();

            $booking->total_nights = $newTotalNights;
            $booking->total_room_amount = $newRoomAmount;
            $booking->total_amount = (int) $booking->total_amount + $addedRoomAmount;
            $booking->check_out = $newScheduledOut;
            $booking->save();

            $this->syncRoomFolioItem($booking, $newTotalNights, $newRoomAmount);
            $this->recalculateTotals($booking);

            return;
        }

        if (! $booking->actual_check_in) {
            return;
        }

        $scheduled = $booking->scheduledCheckOutAt($checkOutClock);
        $checkInDate = $booking->actual_check_in->copy()->timezone($scheduled->getTimezone())->startOfDay();
        $checkOutRef = ($booking->actual_check_out ?? $reference)->copy()->timezone($scheduled->getTimezone());
        $clockSeconds = $scheduled->format('H:i:s');

        // Différence en jours complets
        $actualNights = (int) $checkInDate->diffInDays($checkOutRef->copy()->startOfDay());

        // Si l'heure de départ atteint l'heure limite de check-out, on compte une nuit supplémentaire
        if ($checkOutRef->format('H:i:s') >= $clockSeconds && $actualNights > 0) {
            $actualNights++;
        }

        // Minimum 1 nuit s'il part le même jour
        $actualNights = max(1, $actualNights);

        if ($booking->total_nights !== $actualNights) {
            $newRoomAmount = $actualNights * $booking->effectiveNightlyRate();
            $delta = $newRoomAmount - (int) $booking->total_room_amount;

            $booking->total_nights = $actualNights;
            $booking->total_room_amount = $newRoomAmount;
            $booking->total_amount = max(0, (int) $booking->total_amount + $delta);
            // On ajuste la date prévue pour refléter la réalité
            $booking->check_out = $checkOutRef->copy()->startOfDay();
            $booking->save();

            $this->syncRoomFolioItem($booking, $actualNights, $newRoomAmount);
            $this->recalculateTotals($booking);
        }
    }

    private function syncRoomFolioItem(Booking $booking, int $nights, int $roomAmount): void
    {
        $roomItem = $booking->folioItems()->where('type', FolioItem::TYPE_ROOM)->first();
        if (! $roomItem) {
            return;
        }

        $roomNumber = $booking->room?->number;
        $nightlyRate = (int) $roomItem->unit_price > 0
            ? (int) $roomItem->unit_price
            : $booking->effectiveNightlyRate();

        $roomItem->update([
            'quantity'    => $nights,
            'unit_price'  => $nightlyRate,
            'total_price' => $roomAmount,
            'description' => $roomNumber
                ? "Hébergement {$nights} nuit(s) — Chambre {$roomNumber}"
                : "Hébergement — {$nights} nuit(s)",
        ]);
    }
}
