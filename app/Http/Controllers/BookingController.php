<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function __construct(
        private CheckOutService $checkOutService
    ) {}

    // ===== LISTE =====

    public function index(Request $request)
    {
        $query = Booking::with(['customer', 'room.roomType']);

        // Filtre statut
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'customer',
                        fn($cq) =>
                        $cq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name',  'ilike', "%{$search}%")
                    );
            });
        }

        // Stats pour les badges
        $stats = [
            'all'          => Booking::count(),
            'pending'      => Booking::where('status', BookingStatus::PENDING)->count(),
            'confirmed'    => Booking::where('status', BookingStatus::CONFIRMED)->count(),
            'checked_in'   => Booking::where('status', BookingStatus::CHECKED_IN)->count(),
            'departing'    => Booking::departingToday()->count(),
            'arriving'     => Booking::arrivingToday()->count(),
        ];

        $bookings = $query
            ->orderBy('check_in', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('bookings.index', compact('bookings', 'stats'));
    }

    // ===== WIZARD ÉTAPE 1 : Sélection client =====

    public function create(Request $request)
    {
        $customer = null;

        // Si un client est déjà sélectionné (retour depuis étape 2)
        if ($request->filled('customer_id')) {
            $customer = Customer::find($request->customer_id);
        }

        // Charger les clients pour la recherche locale (AlpineJS)
        $customers = Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('bookings.create', compact('customer', 'customers'));
    }

    // ===== WIZARD ÉTAPE 2 : Choix chambre + dates =====

    public function store(Request $request)
    {
        // Étape 1 → on stocke le client et on passe à l'étape 2
        if ($request->step === '1') {
            return $this->storeStep1($request);
        }

        // Étape 2 → on cherche les chambres disponibles
        if ($request->step === '2') {
            return $this->storeStep2($request);
        }

        // Étape finale → on crée la réservation
        return $this->storeBooking($request);
    }

    private function storeStep1(Request $request)
    {
        // Création d'un nouveau client si nécessaire
        if ($request->filled('new_customer')) {
            $validated = $request->validate([
                'first_name'         => ['required', 'string', 'max:100'],
                'last_name'          => ['required', 'string', 'max:100'],
                'email'              => ['nullable', 'email'],
                'phone'              => ['nullable', 'string', 'max:30'],
                'nationality'        => ['nullable', 'string', 'max:5'],
                'id_document_type'   => ['nullable', 'string'],
                'id_document_number' => ['nullable', 'string', 'max:50'],
            ]);

            $tenantId = Auth::user()->tenant_id
                ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

            $customer = Customer::create(array_merge($validated, ['tenant_id' => $tenantId]));
        } else {
            $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
            $customer = Customer::findOrFail($request->customer_id);
        }

        return redirect()->route('bookings.create', [
            'customer_id' => $customer->id,
            'step'        => 2,
        ]);
    }

    private function storeStep2(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'check_in'    => ['required', 'date', 'after_or_equal:today'],
            'check_out'   => ['required', 'date', 'after:check_in'],
            'adults'      => ['required', 'integer', 'min:1'],
        ]);

        $customer    = Customer::findOrFail($request->customer_id);
        $checkIn     = $request->check_in;
        $checkOut    = $request->check_out;
        $adults      = $request->adults;
        $children    = $request->children ?? 0;
        $totalPeople = $adults + $children;

        // Chambres disponibles pour cette période avec capacité suffisante
        $availableRooms = Room::availableBetween($checkIn, $checkOut)
            ->with('roomType')
            ->whereHas('roomType', fn($q) => $q->where('max_capacity', '>=', $totalPeople))
            ->get()
            ->groupBy('room_type_id');

        $roomTypes = RoomType::whereIn('id', $availableRooms->keys())->get();

        return view('bookings.select-room', compact(
            'customer',
            'checkIn',
            'checkOut',
            'adults',
            'children',
            'availableRooms',
            'roomTypes'
        ));
    }

    private function storeBooking(Request $request)
    {
        $validated = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'room_id'      => ['required', 'exists:rooms,id'],
            'check_in'     => ['required', 'date'],
            'check_out'    => ['required', 'date', 'after:check_in'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'       => ['nullable', 'string'],
            'notes'        => ['nullable', 'string'],
        ]);

        $room     = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn  = \Carbon\Carbon::parse($validated['check_in']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out']);
        $nights   = $checkIn->diffInDays($checkOut);

        $pricePerNight   = $room->roomType->base_price;
        $totalRoomAmount = $nights * $pricePerNight;

        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $booking = Booking::create([
            'tenant_id'       => $tenantId,
            'room_id'         => $room->id,
            'customer_id'     => $validated['customer_id'],
            'status'          => BookingStatus::CONFIRMED,
            'check_in'        => $validated['check_in'],
            'check_out'       => $validated['check_out'],
            'adults_count'    => $validated['adults_count'],
            'children_count'  => $validated['children_count'] ?? 0,
            'total_nights'    => $nights,
            'price_per_night' => $pricePerNight,
            'total_room_amount' => $totalRoomAmount,
            'extras_amount'   => 0,
            'tax_amount'      => (int) round($totalRoomAmount * 0.1925),
            'discount_amount' => 0,
            'total_amount'    => $totalRoomAmount + (int) round($totalRoomAmount * 0.1925),
            'deposit_amount'  => 0,
            'paid_amount'     => 0,
            'balance_due'     => $totalRoomAmount + (int) round($totalRoomAmount * 0.1925),
            'source'          => $validated['source'] ?? 'direct',
            'notes'           => $validated['notes'] ?? null,
            'created_by'      => Auth::id(),
        ]);

        // Ligne folio hébergement créée automatiquement
        FolioItem::create([
            'tenant_id'    => $tenantId,
            'booking_id'   => $booking->id,
            'customer_id'  => $booking->customer_id,
            'type'         => FolioItem::TYPE_ROOM,
            'description'  => "Hébergement {$nights} nuit(s) — Chambre {$room->number}",
            'quantity'     => $nights,
            'unit_price'   => $pricePerNight,
            'total_price'  => $totalRoomAmount,
            'earns_points' => true,
            'occurred_at'  => now(),
            'recorded_by'  => Auth::id(),
        ]);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', "Réservation {$booking->booking_number} créée.");
    }

    // ===== DÉTAIL =====

    public function show(Booking $booking)
    {
        $booking->load([
            'customer',
            'room.roomType',
            'guests',
            'payments',
            'folioItems',
        ]);

        return view('bookings.show', compact('booking'));
    }

    // ===== CHECK-IN =====

    public function checkIn(Request $request, Booking $booking)
    {
        if ($booking->status !== BookingStatus::CONFIRMED) {
            return back()->withErrors(['status' => 'Cette réservation ne peut pas être mise en check-in.']);
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'         => BookingStatus::CHECKED_IN,
                'actual_check_in' => now(),
                'checked_in_by'  => Auth::id(),
            ]);

            $booking->room->updateStatus(
                RoomStatus::OCCUPIED,
                "Check-in {$booking->booking_number}",
                Auth::id()
            );
        });

        return back()->with('success', "Check-in effectué pour {$booking->customer->full_name}.");
    }

    // ===== CHECK-OUT =====

    public function checkOut(Request $request, Booking $booking)
    {
        try {
            $invoice = $this->checkOutService->process($booking);

            return redirect()
                ->route('bookings.show', $booking)
                ->with('success', "Check-out effectué. Facture {$invoice->invoice_number} générée.");
        } catch (\LogicException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }

    // ===== ANNULATION =====

    public function cancel(Request $request, Booking $booking)
    {
        if (!$booking->isEditable()) {
            return back()->withErrors(['cancel' => 'Cette réservation ne peut plus être annulée.']);
        }

        $booking->update(['status' => BookingStatus::CANCELLED]);

        return back()->with('success', 'Réservation annulée.');
    }

    // ===== AJOUT PRESTATION AU FOLIO =====

    public function addFolioItem(Request $request, Booking $booking)
    {
        if ($booking->status !== BookingStatus::CHECKED_IN) {
            return back()->withErrors(['folio' => 'Les prestations ne peuvent être ajoutées que pendant le séjour.']);
        }
        $validated = $request->validate([
            'type'             => ['required', 'string'],
            'description'      => ['required', 'string', 'max:255'],
            'quantity'         => ['required', 'numeric', 'min:0.5'],
            'unit_price'       => ['required', 'integer', 'min:0'],
            'is_complimentary' => ['boolean'],
            'notes'            => ['nullable', 'string'],
        ]);

        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $totalPrice = $validated['is_complimentary'] ?? false
            ? 0
            : (int) round($validated['quantity'] * $validated['unit_price'] * 100);

        FolioItem::create([
            'tenant_id'        => $tenantId,
            'booking_id'       => $booking->id,
            'customer_id'      => $booking->customer_id,
            'type'             => $validated['type'],
            'description'      => $validated['description'],
            'quantity'         => $validated['quantity'],
            'unit_price'       => $validated['unit_price'] * 100,
            'total_price'      => $totalPrice,
            'is_complimentary' => $validated['is_complimentary'] ?? false,
            'earns_points'     => !($validated['is_complimentary'] ?? false),
            'occurred_at'      => now(),
            'recorded_by'      => Auth::id(),
            'notes'            => $validated['notes'] ?? null,
        ]);

        // Recalcule les extras et le solde du booking
        if (!($validated['is_complimentary'] ?? false)) {
            $extrasAmount = $booking->folioItems()
                ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT, FolioItem::TYPE_DISCOUNT])
                ->where('is_complimentary', false)
                ->sum('total_price');

            $taxAmount    = (int) round(($booking->total_room_amount + $extrasAmount) * 0.1925);
            $totalAmount  = $booking->total_room_amount + $extrasAmount + $taxAmount - $booking->discount_amount;
            $balanceDue   = max(0, $totalAmount - $booking->paid_amount);

            $booking->update([
                'extras_amount' => $extrasAmount,
                'tax_amount'    => $taxAmount,
                'total_amount'  => $totalAmount,
                'balance_due'   => $balanceDue,
            ]);
        }

        return redirect()->route('bookings.show', $booking)->with('success', '...');
    }

    public function removeFolioItem(Booking $booking, FolioItem $folioItem)
    {
        // Sécurité : la prestation appartient bien à cette réservation
        if ($folioItem->booking_id !== $booking->id) {
            abort(403);
        }

        // On ne peut pas supprimer une ligne d'hébergement
        if ($folioItem->type === FolioItem::TYPE_ROOM) {
            return back()->withErrors(['folio' => 'La ligne hébergement ne peut pas être supprimée.']);
        }

        // Uniquement en checked_in
        if ($booking->status !== BookingStatus::CHECKED_IN) {
            return back()->withErrors(['folio' => 'Impossible de modifier le folio à ce stade.']);
        }

        $folioItem->delete();

        $this->checkOutService->recalculateTotals($booking);

        return redirect()->route('bookings.show', $booking)->with('success', '...');
    }

    public function addPayment(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'amount'  => ['required', 'integer', 'min:1'],
            'method'  => ['required', 'string', 'in:cash,stripe,orange_money,mtn_momo,bank_transfer'],
            'notes'   => ['nullable', 'string'],
        ]);

        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        // Montant saisi en FCFA → on stocke en centimes
        $amountCentimes = $validated['amount'] * 100;

        // Ajoute cette vérification après le calcul de $amountCentimes
        if ($amountCentimes > $booking->balance_due + 100) {
            return back()->withErrors(['payment' => 'Le montant dépasse le solde dû.']);
        }

        // Génère le numéro de paiement
        $lastPayment = \App\Models\Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')->first();
        $seq = $lastPayment ? (int) substr($lastPayment->reference, -6) + 1 : 1;
        $reference = sprintf('PAY-%d-%06d', now()->year, $seq);

        \App\Models\Payment::create([
            'tenant_id'    => $tenantId,
            'booking_id'   => $booking->id,
            'customer_id'  => $booking->customer_id,
            'amount'       => $amountCentimes,
            'currency'     => 'XAF',
            'method'       => $validated['method'],
            'status'       => 'completed',
            'reference'    => $reference,
            'paid_at'      => now(),
            'processed_by' => Auth::id(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        $this->checkOutService->recalculateTotals($booking);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Paiement enregistré. Solde restant : ' .
                number_format($booking->balance_due / 100, 0, ',', ' ') . ' FCFA');
    }

    public function edit(Booking $booking)
    {
        if (!$booking->isEditable()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->withErrors(['edit' => 'Cette réservation ne peut plus être modifiée.']);
        }

        $booking->load(['customer', 'room.roomType']);
        $roomTypes = RoomType::with('rooms')->get();

        return view('bookings.edit', compact('booking', 'roomTypes'));
    }

    public function update(Request $request, Booking $booking)
    {
        if (!$booking->isEditable()) {
            return redirect()
                ->route('bookings.show', $booking)
                ->withErrors(['edit' => 'Cette réservation ne peut plus être modifiée.']);
        }

        $validated = $request->validate([
            'room_id'        => ['required', 'exists:rooms,id'],
            'check_in'       => ['required', 'date'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'adults_count'   => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'         => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
        ]);

        $room     = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn  = \Carbon\Carbon::parse($validated['check_in']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out']);
        $nights   = $checkIn->diffInDays($checkOut);

        // Vérifie disponibilité si chambre ou dates ont changé
        if (
            $room->id !== $booking->room_id ||
            $checkIn->ne($booking->check_in) ||
            $checkOut->ne($booking->check_out)
        ) {
            $conflict = Booking::where('room_id', $room->id)
                ->where('id', '!=', $booking->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in', [$checkIn, $checkOut])
                        ->orWhereBetween('check_out', [$checkIn, $checkOut])
                        ->orWhere(function ($sq) use ($checkIn, $checkOut) {
                            $sq->where('check_in', '<=', $checkIn)
                                ->where('check_out', '>=', $checkOut);
                        });
                })->exists();

            if ($conflict) {
                return back()->withErrors([
                    'room_id' => 'Cette chambre est déjà réservée sur cette période.'
                ])->withInput();
            }
        }

        $pricePerNight    = $room->roomType->base_price;
        $totalRoomAmount  = $nights * $pricePerNight;
        $taxAmount        = (int) round($totalRoomAmount * 0.1925);
        $totalAmount      = $totalRoomAmount + $taxAmount;

        $booking->update([
            'room_id'          => $room->id,
            'check_in'         => $validated['check_in'],
            'check_out'        => $validated['check_out'],
            'adults_count'     => $validated['adults_count'],
            'children_count'   => $validated['children_count'] ?? 0,
            'total_nights'     => $nights,
            'price_per_night'  => $pricePerNight,
            'total_room_amount' => $totalRoomAmount,
            'tax_amount'       => $taxAmount,
            'total_amount'     => $totalAmount,
            'balance_due'      => max(0, $totalAmount - $booking->paid_amount),
            'source'           => $validated['source'] ?? $booking->source,
            'notes'            => $validated['notes'],
        ]);

        // Met à jour la ligne hébergement dans le folio
        $booking->folioItems()
            ->where('type', FolioItem::TYPE_ROOM)
            ->update([
                'description' => "Hébergement {$nights} nuit(s) — Chambre {$room->number}",
                'quantity'    => $nights,
                'unit_price'  => $pricePerNight,
                'total_price' => $totalRoomAmount,
            ]);

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', 'Réservation mise à jour.');
    }
}
