<?php

namespace App\Http\Controllers\Reception;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CashRegisterActivity;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\ReceptionSale;
use App\Models\ReceptionSaleItem;
use App\Models\ServiceItem;
use App\Services\CashRegisterCircuit;
use App\Services\CheckOutService;
use App\Services\TaxationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReceptionPosController extends Controller
{
    public function __construct(
        private readonly CheckOutService $checkOutService,
        private readonly TaxationService $taxation,
        private readonly CashRegisterCircuit $cashRegisterCircuit,
    ) {
    }

    /**
     * Écran principal du Mode POS Réception.
     */
    public function index(Request $request): View
    {
        // 1. Session de caisse réception active (propre ou partagée pour le chef de réception)
        $cashRegisterContext = $this->cashRegisterCircuit->receptionContextePour(Auth::user());
        $activeSession = $cashRegisterContext['session'];

        $sessionStats = null;
        if ($activeSession) {
            $cashPayments = (int) $activeSession->payments()
                ->where('method', 'cash')
                ->where('status', 'completed')
                ->sum('amount');

            $disbursements = (int) $activeSession->disbursements()->sum('amount');
            $theoreticalCash = $activeSession->opening_amount + $cashPayments - $disbursements;

            $sessionStats = [
                'session_id' => $activeSession->id,
                'opened_at' => $activeSession->opened_at,
                'opening_amount' => $activeSession->opening_amount,
                'cash_payments' => $cashPayments,
                'disbursements' => $disbursements,
                'theoretical_cash' => $theoreticalCash,
                'sales_count' => $activeSession->receptionSales()->count(),
                'disbursements_list' => $activeSession->disbursements()->with('user')->latest()->take(5)->get(),
            ];
        }

        // 2. Séjours en cours (Résidents chambres checked_in)
        $inHouseBookings = Booking::query()
            ->where('status', BookingStatus::CHECKED_IN)
            ->with(['room', 'customer'])
            ->orderBy('id', 'desc')
            ->get();

        // 3. Catalogue des prestations actives
        $serviceItems = ServiceItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // 4. Clients pour recherche
        $customers = Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(100)
            ->get();

        // 5. Dernières ventes du jour
        $recentSales = ReceptionSale::query()
            ->with(['items', 'booking.room'])
            ->whereDate('created_at', now()->toDateString())
            ->orderByDesc('id')
            ->take(8)
            ->get();

        return view('reception.pos.index', [
            'activeSession' => $activeSession,
            'cashRegisterContext' => $cashRegisterContext,
            'sessionStats' => $sessionStats,
            'inHouseBookings' => $inHouseBookings,
            'serviceItems' => $serviceItems,
            'customers' => $customers,
            'recentSales' => $recentSales,
        ]);
    }

    /**
     * Enregistre une vente POS Réception (Paiement immédiat ou Débit sur le folio).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_mode' => ['required', 'in:room,customer'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'create_customer' => ['nullable', 'boolean'],
            'payment_method' => ['required', 'in:cash,card,mobile_money,bank_transfer,room_charge,other'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_item_id' => ['nullable', 'exists:service_items,id'],
            'items.*.category' => ['nullable', 'string', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.5'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $isRoomCharge = $validated['payment_method'] === 'room_charge';

        // Si débit folio, le séjour en cours est obligatoire
        if ($isRoomCharge && empty($validated['booking_id'])) {
            return back()->withInput()->withErrors(['booking_id' => 'Vous devez sélectionner un résident en chambre pour débiter sur le folio.']);
        }

        // Vérification de la session de caisse pour encaissement immédiat
        $activeSession = $this->cashRegisterCircuit->receptionActivePour(Auth::user());

        if (!$isRoomCharge && !$activeSession) {
            return back()->withInput()->withErrors(['caisse' => 'Veuillez ouvrir votre caisse de réception avant d\'encaisser un paiement.']);
        }

        // Gestion du résident ou client
        $booking = null;
        $customerName = trim($validated['customer_name'] ?? '');
        $customerPhone = trim($validated['customer_phone'] ?? '');
        $customerId = $validated['customer_id'] ?? null;
        $roomNumber = null;

        if (!empty($validated['booking_id'])) {
            $booking = Booking::query()
                ->where('id', $validated['booking_id'])
                ->where('status', BookingStatus::CHECKED_IN)
                ->with(['customer', 'room'])
                ->first();

            if (!$booking) {
                return back()->withInput()->withErrors(['booking_id' => 'Le séjour sélectionné n\'est pas en cours (chambre non occupée).']);
            }

            $customerId = $booking->customer_id;
            $customerName = $booking->customer?->full_name ?: ($booking->customer?->first_name . ' ' . $booking->customer?->last_name);
            $customerPhone = $booking->customer?->phone;
            $roomNumber = $booking->room?->number;
        } elseif (!empty($validated['create_customer']) && !empty($customerName)) {
            $parts = explode(' ', $customerName, 2);
            $firstName = $parts[0] ?? 'Client';
            $lastName = $parts[1] ?? 'Passage';

            $newCustomer = Customer::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $customerPhone ?: null,
            ]);
            $customerId = $newCustomer->id;
        }

        if (empty($customerName)) {
            $customerName = 'Client de passage';
        }

        $sale = DB::transaction(function () use (
            $validated,
            $isRoomCharge,
            $activeSession,
            $booking,
            $customerId,
            $customerName,
            $customerPhone,
            $roomNumber
        ) {
            // 1. Calcul des totaux des lignes
            $subtotalCentimes = 0;
            $processedItems = [];

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $unitPriceCentimes = (int) round(((float) $itemData['unit_price']) * 100);
                $lineTotalCentimes = (int) round($qty * $unitPriceCentimes);

                $subtotalCentimes += $lineTotalCentimes;

                $processedItems[] = [
                    'service_item_id' => $itemData['service_item_id'] ?? null,
                    'category' => $itemData['category'] ?? 'other',
                    'name' => trim($itemData['name']),
                    'quantity' => $qty,
                    'unit_price' => $unitPriceCentimes,
                    'total_price' => $lineTotalCentimes,
                ];
            }

            $totalAmountCentimes = $subtotalCentimes;
            $taxAmountCentimes = $this->taxation->breakdown($totalAmountCentimes)->vat;

            // Numéro de vente unique
            $saleNumber = 'POS-REC-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

            // 2. Création de la vente ReceptionSale
            $sale = ReceptionSale::create([
                'tenant_id' => Auth::user()->tenant_id ?? \App\Models\Tenant::current()?->id,
                'sale_number' => $saleNumber,
                'user_id' => Auth::id(),
                'cash_register_session_id' => $activeSession?->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone ?: null,
                'booking_id' => $booking?->id,
                'room_number' => $roomNumber,
                'payment_type' => $isRoomCharge ? 'room_charge' : 'immediate',
                'payment_method' => $validated['payment_method'],
                'payment_status' => $isRoomCharge ? 'charged_to_room' : 'paid',
                'subtotal' => $subtotalCentimes,
                'tax_amount' => $taxAmountCentimes,
                'total_amount' => $totalAmountCentimes,
                'notes' => $validated['notes'] ?? null,
                'paid_at' => now(),
            ]);

            // 3. Enregistrement des lignes d'articles & répercussion Folio
            foreach ($processedItems as $item) {
                $folioItemId = null;

                // Si débit sur folio (Option B) ou si résident avec traçabilité complète au folio
                if ($isRoomCharge && $booking) {
                    $folioCategory = match($item['category']) {
                        'laundry' => FolioItem::TYPE_LAUNDRY,
                        'spa' => FolioItem::TYPE_SPA,
                        'activity' => FolioItem::TYPE_ACTIVITY,
                        'housekeeping' => FolioItem::TYPE_HOUSEKEEPING,
                        'minibar' => FolioItem::TYPE_MINIBAR,
                        'breakfast', 'restaurant' => FolioItem::TYPE_RESTAURANT,
                        default => FolioItem::TYPE_OTHER,
                    };

                    $folio = FolioItem::create([
                        'booking_id' => $booking->id,
                        'customer_id' => $booking->customer_id,
                        'type' => $folioCategory,
                        'description' => "POS Réception — {$item['name']}",
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'is_complimentary' => false,
                        'earns_points' => true,
                        'recorded_by' => Auth::id(),
                        'occurred_at' => now(),
                        'notes' => "Vente #{$sale->sale_number}",
                    ]);

                    $folioItemId = $folio->id;
                }

                ReceptionSaleItem::create([
                    'reception_sale_id' => $sale->id,
                    'service_item_id' => $item['service_item_id'],
                    'category' => $item['category'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'folio_item_id' => $folioItemId,
                ]);
            }

            // 4. Si Paiement Immédiat (Option A) : Enregistrer le Payment pour la caisse
            if (!$isRoomCharge && $activeSession) {
                // Génération référence de paiement PAY-AAAA-XXXXXX
                $maxSeq = 0;
                $payments = Payment::withoutGlobalScopes()
                    ->where('reference', 'like', 'PAY-' . now()->year . '-%')
                    ->get(['reference']);

                foreach ($payments as $p) {
                    $parts = explode('-', $p->reference);
                    $lastPart = end($parts);
                    if (is_numeric($lastPart)) {
                        $maxSeq = max($maxSeq, (int) $lastPart);
                    }
                }
                $paymentRef = sprintf('PAY-%d-%06d', now()->year, $maxSeq + 1);

                $payment = Payment::create([
                    'booking_id' => $booking?->id,
                    'customer_id' => $customerId,
                    'payment_origin' => Payment::ORIGIN_POS_RECEPTION,
                    'cash_register_session_id' => $activeSession->id,
                    'amount' => $totalAmountCentimes,
                    'currency' => 'XAF',
                    'method' => $validated['payment_method'],
                    'status' => 'completed',
                    'reference' => $paymentRef,
                    'paid_at' => now(),
                    'processed_by' => Auth::id(),
                    'notes' => "POS Réception — Vente #{$sale->sale_number}",
                ]);

                $sale->update(['payment_id' => $payment->id]);
            }

            // 5. Recalculer les totaux de la réservation si débit folio
            if ($isRoomCharge && $booking) {
                $this->checkOutService->recalculateTotals($booking->fresh());
            }

            return $sale;
        });

        if ($activeSession !== null) {
            $this->cashRegisterCircuit->enregistrerInterventionTiers(
                $activeSession,
                Auth::user(),
                \App\Models\CashRegisterActivity::ACTION_POS_SALE,
                $isRoomCharge
                    ? "Vente POS Réception {$sale->sale_number} — Chambre {$roomNumber} ({$customerName})"
                    : "Vente POS Réception {$sale->sale_number} — {$customerName}",
                $isRoomCharge ? null : (int) $sale->total_amount,
                $validated['payment_method'],
                $sale,
                [
                    'reception_sale_id' => $sale->id,
                    'payment_id' => $sale->payment_id,
                    'booking_id' => $sale->booking_id,
                ]
            );
        }

        $message = $isRoomCharge
            ? "Vente enregistrée et débitée sur la chambre {$roomNumber} avec succès."
            : "Vente et encaissement enregistrés avec succès dans la caisse.";

        return redirect()->route('reception.pos.receipt', $sale)->with('success', $message);
    }

    /**
     * Affiche le reçu / ticket de caisse imprimable.
     */
    public function receipt(ReceptionSale $sale): View
    {
        $sale->load(['items', 'booking.room', 'customer', 'user', 'cashRegisterSession', 'payment']);

        return view('reception.pos.receipt', [
            'sale' => $sale,
        ]);
    }

    /**
     * Historique des ventes POS de la réception.
     */
    public function history(Request $request): View
    {
        $query = ReceptionSale::query()
            ->with(['items', 'booking.room', 'user'])
            ->orderByDesc('id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sale_number', 'ilike', "%{$search}%")
                    ->orWhere('customer_name', 'ilike', "%{$search}%")
                    ->orWhere('room_number', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($date = $request->input('date')) {
            $query->whereDate('created_at', $date);
        }

        $sales = $query->paginate(15)->withQueryString();

        return view('reception.pos.history', [
            'sales' => $sales,
        ]);
    }
}
