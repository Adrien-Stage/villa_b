<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\FolioItem;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
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

        if ($booking->balance_due > 0) {
            throw new \LogicException(
                "Solde impayé de " . number_format($booking->balance_due / 100, 0, ',', ' ') . " FCFA. " .
                    "Réglez le solde avant le check-out."
            );
        }
    }

    /**
     * Recalcule et finalise tous les montants du booking
     */
    private function finalizeAmounts(Booking $booking): void
    {
        // Additionne toutes les prestations du folio (hors hébergement)
        $extrasAmount = $booking->folioItems()
            ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT, FolioItem::TYPE_DISCOUNT])
            ->where('is_complimentary', false)
            ->sum('total_price');

        // Remises du folio
        $discountAmount = $booking->folioItems()
            ->where('type', FolioItem::TYPE_DISCOUNT)
            ->sum('total_price');

        // Taxes (19.25% TVA Cameroun sur le total HT)
        $taxRate    = 0.1925;
        $subtotal   = $booking->total_room_amount + $extrasAmount - $discountAmount;
        $taxAmount  = (int) round($subtotal * $taxRate);
        $totalAmount = $subtotal + $taxAmount;

        // Paiements déjà reçus
        $paidAmount = $booking->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $booking->update([
            'extras_amount'   => $extrasAmount,
            'discount_amount' => $discountAmount,
            'tax_amount'      => $taxAmount,
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
        $invoice = Invoice::create([
            'tenant_id'      => $booking->tenant_id,
            'booking_id'     => $booking->id,
            'customer_id'    => $booking->customer_id,
            'invoice_number' => $this->generateInvoiceNumber($booking->tenant_id),
            'invoice_date'   => now(),
            'subtotal'       => $booking->total_room_amount + $booking->extras_amount - $booking->discount_amount,
            'tax_amount'     => $booking->tax_amount,
            'total_amount'   => $booking->total_amount,
            'paid_amount'    => $booking->paid_amount,
            'balance_due'    => $booking->balance_due,
            'status'         => $booking->balance_due <= 0 ? 'paid' : 'sent',
            'legal_notes'    => 'TVA 19.25% incluse — République du Cameroun',
        ]);

        // Ligne hébergement
        InvoiceItem::create([
            'tenant_id'   => $booking->tenant_id,
            'invoice_id'  => $invoice->id,
            'description' => "Hébergement — {$booking->total_nights} nuit(s) × " .
                number_format($booking->price_per_night / 100, 0, ',', ' ') . " FCFA",
            'quantity'    => $booking->total_nights,
            'unit_price'  => $booking->price_per_night,
            'total_price' => $booking->total_room_amount,
            'tax_rate'    => 19.25,
            'tax_amount'  => (int) round($booking->total_room_amount * 0.1925),
            'category'    => 'room',
            'source_type' => Booking::class,
            'source_id'   => $booking->id,
        ]);

        // Lignes du folio (extras)
        foreach ($booking->folioItems()->where('type', '!=', FolioItem::TYPE_PAYMENT)->get() as $item) {
            InvoiceItem::create([
                'tenant_id'   => $booking->tenant_id,
                'invoice_id'  => $invoice->id,
                'description' => $item->description,
                'quantity'    => $item->quantity,
                'unit_price'  => $item->unit_price,
                'total_price' => $item->total_price,
                'tax_rate'    => 19.25,
                'tax_amount'  => (int) round($item->total_price * 0.1925),
                'category'    => $item->type,
                'source_type' => FolioItem::class,
                'source_id'   => $item->id,
            ]);
        }

        return $invoice;
    }

    /**
     * Génère un numéro de facture séquentiel par tenant et par année
     * Format : F-2026-000001
     */
    private function generateInvoiceNumber(int $tenantId): string
    {
        $year = now()->year;

        $lastInvoice = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('invoice_date', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastInvoice
            ? (int) substr($lastInvoice->invoice_number, -6) + 1
            : 1;

        return sprintf('F-%d-%06d', $year, $sequence);
    }

    public function recalculateTotals(Booking $booking): void
    {
        $this->finalizeAmounts($booking);
    }

    public function syncDurationToNow(Booking $booking): void
    {
        if ($booking->status === BookingStatus::CHECKED_IN && $booking->actual_check_in) {
            $checkInDate = $booking->actual_check_in->copy()->startOfDay();
            $checkOutDate = $booking->actual_check_out ?? now();
            
            // Différence en jours complets
            $actualNights = $checkInDate->diffInDays($checkOutDate->copy()->startOfDay());
            
            // Si l'heure de départ dépasse 14h00, on compte une nuit supplémentaire
            if ($checkOutDate->format('H:i') >= '14:00' && $actualNights > 0) {
                $actualNights++;
            }
            
            // Minimum 1 nuit s'il part le même jour
            $actualNights = max(1, $actualNights);
            
            if ($booking->total_nights !== $actualNights) {
                $booking->total_nights = $actualNights;
                $booking->total_room_amount = $actualNights * $booking->price_per_night;
                // On peut aussi ajuster la date prévue pour refléter la réalité
                $booking->check_out = $checkOutDate->copy()->startOfDay();
                $booking->save();
                
                // Mettre à jour la ligne folio de l'hébergement
                $booking->folioItems()->where('type', FolioItem::TYPE_ROOM)->update([
                    'quantity'    => $actualNights,
                    'total_price' => $booking->total_room_amount,
                    'description' => "Hébergement {$actualNights} nuit(s) — Chambre {$booking->room->number}",
                ]);

                $this->recalculateTotals($booking);
            }
        }
    }
}
