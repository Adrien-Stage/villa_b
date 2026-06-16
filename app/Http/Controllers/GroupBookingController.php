<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\GroupBooking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupBookingController extends Controller
{
    public function __construct(
        private CheckOutService $checkOutService
    ) {}

    // ===== LISTE =====

    public function index(Request $request)
    {
        $query = GroupBooking::with(['contactCustomer'])
            ->withCount('bookings');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('group_code', 'ilike', "%{$search}%")
                    ->orWhere('group_name', 'ilike', "%{$search}%")
                    ->orWhereHas(
                        'contactCustomer',
                        fn ($cq) => $cq->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%")
                    );
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $stats = [
            'total' => GroupBooking::count(),
            'pending' => GroupBooking::where('status', 'pending')->count(),
            'confirmed' => GroupBooking::where('status', 'confirmed')->count(),
            'in_house' => GroupBooking::where('status', 'in_house')->count(),
        ];

        $groups = $query->orderBy('start_date', 'desc')->paginate(20)->withQueryString();

        return view('groups.index', compact('groups', 'stats'));
    }

    // ===== CRÉATION =====

    public function create(Request $request)
    {
        $customers = Customer::orderBy('last_name')->orderBy('first_name')->get();

        return view('groups.create', compact('customers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_customer_id' => ['required', 'exists:customers,id'],
            'group_name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:family,corporate,wedding,tour_group'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        $tenantId = Auth::user()->tenant_id
            ?? Tenant::where('slug', 'villa-boutanga')->value('id');

        $bookerId = null;
        if ($request->is_booker === 'other') {
            if ($request->filled('new_booker')) {
                $validatedBooker = $request->validate([
                    'booker_first_name'         => ['required', 'string', 'max:100'],
                    'booker_last_name'          => ['required', 'string', 'max:100'],
                    'booker_email'              => ['nullable', 'email'],
                    'booker_phone'              => ['required', 'string', 'max:30'],
                    'booker_id_document_type'   => ['nullable', 'string'],
                    'booker_id_document_number' => ['required', 'string', 'max:50'],
                ]);
                
                $bookerData = [];
                foreach($validatedBooker as $key => $value) {
                    $bookerData[str_replace('booker_', '', $key)] = $value;
                }
                $bookerData['tenant_id'] = $tenantId;
                
                $booker = Customer::create($bookerData);
                $bookerId = $booker->id;
            } else {
                $request->validate(['booker_id' => ['required', 'exists:customers,id']]);
                $bookerId = $request->booker_id;
            }
        }

        // Génère le code groupe : GRP-2026-0001
        $lastGroup = GroupBooking::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')->first();
        $seq = $lastGroup ? (int) substr($lastGroup->group_code, -4) + 1 : 1;
        $groupCode = sprintf('GRP-%d-%04d', now()->year, $seq);

        // Convertir en centimes pour la base de données
        $depositAmount = $request->filled('deposit_amount') ? (int) $request->deposit_amount * 100 : 0;

        $group = GroupBooking::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'booker_id' => $bookerId,
            'group_code' => $groupCode,
            'total_deposit_paid' => $depositAmount,
            'status' => 'pending',
        ]));

        if ($depositAmount > 0 && $request->filled('payment_method')) {
            $payment = \App\Models\Payment::create([
                'tenant_id' => $tenantId,
                'booking_id' => null, // Ce n'est pas lié à une chambre spécifique
                'customer_id' => $group->contact_customer_id,
                'amount' => $depositAmount,
                'currency' => 'XAF',
                'method' => $request->payment_method,
                'status' => 'completed',
                'reference' => 'PAY-' . now()->year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'external_reference' => $request->payment_reference ?? '',
                'paid_at' => now(),
                'processed_by' => Auth::id(),
                'notes' => 'Acompte global groupe ' . $groupCode,
            ]);
            
            // On l'ajoute comme FolioItem pour qu'il soit sur la facture du groupe
            \App\Models\FolioItem::create([
                'tenant_id'    => $tenantId,
                'booking_id'   => null,
                'customer_id'  => $group->contact_customer_id,
                'type'         => \App\Models\FolioItem::TYPE_PAYMENT,
                'description'  => "Acompte global groupe ({$request->payment_method})",
                'quantity'     => 1,
                'unit_price'   => -$depositAmount,
                'total_price'  => -$depositAmount,
                'is_complimentary' => false,
                'earns_points' => false,
                'occurred_at'  => now(),
                'recorded_by'  => Auth::id(),
                'notes'        => 'Dossier groupe: ' . $group->id
            ]);
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('success', "Dossier groupe {$groupCode} créé." . ($depositAmount > 0 ? " Acompte enregistré." : ""));
    }

    // ===== DÉTAIL =====

    public function show(GroupBooking $groupBooking)
    {
        $groupBooking->load([
            'contactCustomer',
            'bookings.room.roomType',
            'bookings.customer',
            'bookings.payments',
        ]);

        // Chambres disponibles pour ajouter au groupe
        $availableRooms = Room::availableBetween(
            $groupBooking->start_date,
            $groupBooking->end_date
        )
            ->with('roomType')
            ->whereNotIn('id', $groupBooking->bookings->pluck('room_id'))
            ->get()
            ->groupBy('room_type_id');

        $roomTypes = RoomType::whereIn('id', $availableRooms->keys())->get();

        // Clients pour assigner à une chambre
        $customers = Customer::orderBy('last_name')->get();

        // Totaux du groupe
        $totals = [
            'rooms' => $groupBooking->bookings->count(),
            'nights' => $groupBooking->bookings->sum('total_nights'),
            'total' => $groupBooking->bookings->sum('total_amount'),
            'paid' => $groupBooking->bookings->sum('paid_amount'),
            'balance_due' => $groupBooking->bookings->sum('balance_due'),
        ];

        return view('groups.show', compact('groupBooking', 'availableRooms', 'roomTypes', 'customers', 'totals'));
    }

    // ===== AJOUTER UNE CHAMBRE AU GROUPE =====

    public function addRoom(Request $request, GroupBooking $groupBooking)
    {
        $tenantId = Auth::user()->tenant_id ?? Tenant::where('slug', 'villa-boutanga')->value('id');
        
        if ($request->filled('new_customer')) {
            $customerRules = [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
                'nationality' => ['nullable', 'string', 'max:5'],
                'id_document_type' => ['nullable', 'string'],
            ];
            $request->validate($customerRules);
            
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'nationality' => $request->nationality,
                'id_document_type' => $request->id_document_type,
            ]);
            
            $customerId = $customer->id;
        } else {
            $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
            $customerId = $request->customer_id;
        }

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $room = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn = $groupBooking->start_date;
        $checkOut = $groupBooking->end_date;
        $nights = $checkIn->diffInDays($checkOut);

        // Vérifie disponibilité
        $conflict = Booking::where('room_id', $room->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(fn ($sq) => $sq->where('check_in', '<=', $checkIn)
                        ->where('check_out', '>=', $checkOut));
            })->exists();

        if ($conflict) {
            return back()->withErrors(['room_id' => 'Cette chambre est déjà réservée sur cette période.']);
        }

        $tenantId = Auth::user()->tenant_id
            ?? Tenant::where('slug', 'villa-boutanga')->value('id');
        $pricePerNight = $room->roomType->base_price;
        $totalRoomAmount = $nights * $pricePerNight;
        $taxAmount = 0;
        $totalAmount = $totalRoomAmount;

        DB::transaction(function () use (
            $groupBooking,
            $room,
            $validated,
            $customerId,
            $tenantId,
            $checkIn,
            $checkOut,
            $nights,
            $pricePerNight,
            $totalRoomAmount,
            $taxAmount,
            $totalAmount
        ) {
            $booking = Booking::create([
                'tenant_id' => $tenantId,
                'group_booking_id' => $groupBooking->id,
                'room_id' => $room->id,
                'customer_id' => $customerId,
                'booker_id' => $groupBooking->booker_id,
                'status' => BookingStatus::CONFIRMED,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults_count' => $validated['adults_count'],
                'children_count' => $validated['children_count'] ?? 0,
                'total_nights' => $nights,
                'price_per_night' => $pricePerNight,
                'total_room_amount' => $totalRoomAmount,
                'extras_amount' => 0,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $totalAmount,
                'deposit_amount' => 0,
                'paid_amount' => 0,
                'balance_due' => $totalAmount,
                'source' => 'group',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Ligne folio hébergement
            FolioItem::create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'type' => FolioItem::TYPE_ROOM,
                'description' => "Hébergement {$nights} nuit(s) — Chambre {$room->number}",
                'quantity' => $nights,
                'unit_price' => $pricePerNight,
                'total_price' => $totalRoomAmount,
                'earns_points' => true,
                'occurred_at' => now(),
                'recorded_by' => Auth::id(),
            ]);
        });

        // Met à jour le statut du groupe si c'est la première chambre
        if ($groupBooking->status === 'pending') {
            $groupBooking->update(['status' => 'confirmed']);
        }

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', "Chambre {$room->number} ajoutée au groupe.");
    }

    // ===== RETIRER UNE CHAMBRE =====

    public function removeRoom(GroupBooking $groupBooking, Booking $booking)
    {
        if ($booking->group_booking_id !== $groupBooking->id) {
            abort(403);
        }

        if (! $booking->isEditable()) {
            return back()->withErrors(['remove' => 'Cette réservation ne peut plus être retirée.']);
        }

        $booking->update(['status' => BookingStatus::CANCELLED]);

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', 'Chambre retirée du groupe.');
    }

    // ===== CHECK-IN GLOBAL =====

    public function checkInAll(Request $request, GroupBooking $groupBooking)
    {
        $confirmed = $groupBooking->bookings
            ->where('status', BookingStatus::CONFIRMED);

        if ($confirmed->isEmpty()) {
            return back()->withErrors(['checkin' => 'Aucune réservation confirmée à checker in.']);
        }

        DB::transaction(function () use ($groupBooking, $confirmed) {
            foreach ($confirmed as $booking) {
                $booking->update([
                    'status' => BookingStatus::CHECKED_IN,
                    'actual_check_in' => now(),
                    'checked_in_by' => Auth::id(),
                ]);

                $booking->room->updateStatus(
                    RoomStatus::OCCUPIED,
                    "Check-in groupe {$groupBooking->group_code}",
                    Auth::id()
                );
            }

            $groupBooking->update(['status' => 'in_house']);
        });

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', "Check-in effectué pour {$confirmed->count()} chambre(s).");
    }

    // ===== CHECK-OUT GLOBAL =====

    public function checkOutAll(Request $request, GroupBooking $groupBooking)
    {
        $checkedIn = $groupBooking->bookings()
            ->where('status', BookingStatus::CHECKED_IN)
            ->get();

        if ($checkedIn->isEmpty()) {
            return back()->withErrors(['checkout' => 'Aucune réservation en cours à checker out.']);
        }

        // Vérifie que toutes les réservations sont soldées
        $unsettled = $checkedIn->where('balance_due', '>', 0);
        if ($unsettled->isNotEmpty()) {
            return back()->withErrors([
                'checkout' => "{$unsettled->count()} chambre(s) ont un solde impayé. Réglez avant le check-out.",
            ]);
        }

        $errors = [];
        foreach ($checkedIn as $booking) {
            try {
                $this->checkOutService->process($booking);
            } catch (\Exception $e) {
                $errors[] = "Chambre {$booking->room->number} : {$e->getMessage()}";
            }
        }

        if (! empty($errors)) {
            return back()->withErrors(['checkout' => implode(' | ', $errors)]);
        }

        $groupBooking->update(['status' => 'completed']);

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', "Check-out effectué pour {$checkedIn->count()} chambre(s). Factures générées.");
    }

    public function addGroupFolioItem(Request $request, GroupBooking $groupBooking)
    {
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'is_complimentary' => ['boolean'],
            'split_mode' => ['required', 'in:per_room,per_person,global'],
            'notes' => ['nullable', 'string'],
        ]);

        // Uniquement si le groupe est en séjour
        if ($groupBooking->status !== 'in_house') {
            return back()->withErrors(['folio' => 'Les prestations ne peuvent être ajoutées qu\'en cours de séjour.']);
        }

        $tenantId = Auth::user()->tenant_id
            ?? Tenant::where('slug', 'villa-boutanga')->value('id');

        $isComplimentary = $validated['is_complimentary'] ?? false;
        $bookings = $groupBooking->bookings()
            ->where('status', BookingStatus::CHECKED_IN)
            ->with('room')
            ->get();

        if ($bookings->isEmpty()) {
            return back()->withErrors(['folio' => 'Aucune chambre en séjour actif.']);
        }

        DB::transaction(function () use (
            $validated,
            $groupBooking,
            $bookings,
            $tenantId,
            $isComplimentary
        ) {
            foreach ($bookings as $booking) {

                // Calcul du montant selon le mode de répartition
                $linePrice = match ($validated['split_mode']) {
                    // Par chambre : même prix pour chaque chambre
                    'per_room' => $isComplimentary ? 0 : (int) round($validated['quantity'] * $validated['unit_price'] * 100),

                    // Par personne : multiplié par le nb d'occupants de la chambre
                    'per_person' => $isComplimentary ? 0 : (int) round(
                        $validated['quantity'] *
                            $validated['unit_price'] * 100 *
                            ($booking->adults_count + $booking->children_count)
                    ),

                    // Global : réparti équitablement entre toutes les chambres
                    'global' => $isComplimentary ? 0 : (int) round(
                        ($validated['quantity'] * $validated['unit_price'] * 100) / $bookings->count()
                    ),
                };

                $unitForLine = match ($validated['split_mode']) {
                    'per_room' => $validated['unit_price'] * 100,
                    'per_person' => $validated['unit_price'] * 100,
                    'global' => (int) round(($validated['unit_price'] * 100) / $bookings->count()),
                };

                FolioItem::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'type' => $validated['type'],
                    'description' => $validated['description']." (groupe {$groupBooking->group_code})",
                    'quantity' => $validated['quantity'],
                    'unit_price' => $unitForLine,
                    'total_price' => $linePrice,
                    'is_complimentary' => $isComplimentary,
                    'earns_points' => ! $isComplimentary,
                    'occurred_at' => now(),
                    'recorded_by' => Auth::id(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Recalcule le solde de chaque booking
                if (! $isComplimentary) {
                    $extrasAmount = $booking->folioItems()
                        ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT, FolioItem::TYPE_DISCOUNT])
                        ->where('is_complimentary', false)
                        ->sum('total_price');

                    $taxAmount = 0;
                    $totalAmount = $booking->total_room_amount + $extrasAmount + $taxAmount;

                    $booking->update([
                        'extras_amount' => $extrasAmount,
                        'tax_amount' => $taxAmount,
                        'total_amount' => $totalAmount,
                        'balance_due' => max(0, $totalAmount - $booking->paid_amount),
                    ]);
                }
            }
        });

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', "Prestation ajoutée à {$bookings->count()} chambre(s).");
    }

    public function addGroupPayment(Request $request, GroupBooking $groupBooking)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:cash,stripe,orange_money,mtn_momo,bank_transfer'],
            'distribution' => ['required', 'in:proportional,equal'],
            'notes' => ['nullable', 'string'],
        ]);

        $bookings = $groupBooking->bookings()
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN])
            ->where('balance_due', '>', 0)
            ->get();

        if ($bookings->isEmpty()) {
            return back()->withErrors(['payment' => 'Aucune chambre avec un solde impayé.']);
        }

        $tenantId = Auth::user()->tenant_id
            ?? Tenant::where('slug', 'villa-boutanga')->value('id');
        $totalTargetBalance = 0;
        foreach ($bookings as $booking) {
            $booking->target_balance = max($booking->balance_due, $booking->getConsumedBalance());
            $totalTargetBalance += $booking->target_balance;
        }

        if ($totalCentimes > $totalTargetBalance + 100) {
            return back()->withErrors(['payment' => 'Le montant dépasse le solde total dû du groupe.']);
        }

        DB::transaction(function () use (
            $bookings,
            $validated,
            $totalCentimes,
            $totalTargetBalance,
            $tenantId,
            $groupBooking
        ) {
            $remaining = $totalCentimes;

            foreach ($bookings as $index => $booking) {
                if ($remaining <= 0) {
                    break;
                }

                // Calcul de la part de ce booking
                $share = match ($validated['distribution']) {
                    // Proportionnel : chaque chambre paie selon son solde relatif
                    'proportional' => (int) round($totalCentimes * ($booking->target_balance / $totalTargetBalance)),

                    // Égal : montant divisé équitablement
                    'equal' => (int) round($totalCentimes / $bookings->count()),
                };

                // Sur la dernière chambre on met le reste pour éviter les erreurs d'arrondi
                if ($index === $bookings->count() - 1) {
                    $share = $remaining;
                }

                $share = min($share, $booking->target_balance, $remaining);
                if ($share <= 0) {
                    continue;
                }

                // Génère référence paiement
                $lastPayment = Payment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->orderBy('id', 'desc')->first();
                $seq = $lastPayment ? (int) substr($lastPayment->reference, -6) + 1 : 1;
                $reference = sprintf('PAY-%d-%06d', now()->year, $seq);

                Payment::create([
                    'tenant_id' => $tenantId,
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'amount' => $share,
                    'currency' => 'XAF',
                    'method' => $validated['method'],
                    'status' => 'completed',
                    'reference' => $reference,
                    'paid_at' => now(),
                    'processed_by' => Auth::id(),
                    'notes' => ($validated['notes'] ?? '')." — Paiement groupe {$groupBooking->group_code}",
                ]);

                // Met à jour le solde du booking
                $newPaid = $booking->paid_amount + $share;
                $newBalanceDue = max(0, $booking->total_amount - $newPaid);

                $booking->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBalanceDue,
                ]);

                $remaining -= $share;
            }

            // Après avoir réparti le paiement, on vérifie si certaines chambres ont réglé leur solde consommé
            foreach ($bookings as $booking) {
                if ($booking->getConsumedBalance() <= 0 && $booking->status === BookingStatus::CHECKED_IN) {
                    // Le client a réglé l'intégralité du temps consommé -> on arrête le minuteur
                    if (!$booking->actual_check_out) {
                        $booking->update(['actual_check_out' => now()]);
                    }
                    
                    // Actualise le folio et les coûts pour correspondre exactement à la durée réelle passée
                    $this->checkOutService->syncDurationToNow($booking);
                }
            }
        });

        // Recharge pour avoir les totaux à jour
        $groupBooking->load('bookings');
        $newTotalBalance = $groupBooking->bookings->sum('balance_due');

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', 'Paiement groupe enregistré. Solde restant : '.
                number_format($newTotalBalance / 100, 0, ',', ' ').' FCFA');
    }

    public function invoice(GroupBooking $groupBooking)
    {
        $groupBooking->load([
            'contactCustomer',
            'bookings.room.roomType',
            'bookings.customer',
            'bookings.folioItems',
            'bookings.payments',
            'bookings.invoice',
        ]);

        $tenant = Tenant::find($groupBooking->tenant_id);

        $totals = [
            'rooms' => $groupBooking->bookings->count(),
            'nights' => $groupBooking->bookings->sum('total_nights'),
            'subtotal' => $groupBooking->bookings->sum('total_room_amount') + $groupBooking->bookings->sum('extras_amount'),
            'tax' => $groupBooking->bookings->sum('tax_amount'),
            'total' => $groupBooking->bookings->sum('total_amount'),
            'paid' => $groupBooking->bookings->sum('paid_amount'),
            'balance_due' => $groupBooking->bookings->sum('balance_due'),
        ];

        return view('groups.invoice', compact('groupBooking', 'tenant', 'totals'));
    }

    public function edit(GroupBooking $groupBooking)
    {
        if (in_array($groupBooking->status, ['completed', 'cancelled'])) {
            return redirect()
                ->route('groups.show', $groupBooking)
                ->withErrors(['edit' => 'Ce dossier ne peut plus être modifié.']);
        }

        $groupBooking->load('contactCustomer');

        $customers = Customer::orderBy('last_name')->get();

        return view('groups.edit', compact('groupBooking', 'customers'));
    }

    public function update(Request $request, GroupBooking $groupBooking)
    {
        if (in_array($groupBooking->status, ['completed', 'cancelled'])) {
            return redirect()
                ->route('groups.show', $groupBooking)
                ->withErrors(['edit' => 'Ce dossier ne peut plus être modifié.']);
        }

        $validated = $request->validate([
            'contact_customer_id' => ['required', 'exists:customers,id'],
            'group_name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'in:family,corporate,wedding,tour_group'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ]);

        // Si des chambres sont déjà en checked_in, on bloque le changement de dates
        $hasCheckedIn = $groupBooking->bookings()
            ->where('status', BookingStatus::CHECKED_IN)
            ->exists();

        if ($hasCheckedIn && (
            $validated['start_date'] !== $groupBooking->start_date->format('Y-m-d') ||
            $validated['end_date'] !== $groupBooking->end_date->format('Y-m-d')
        )) {
            return back()->withErrors([
                'start_date' => 'Les dates ne peuvent pas être modifiées car des chambres sont déjà en séjour.',
            ])->withInput();
        }

        $groupBooking->update($validated);

        return redirect()
            ->route('groups.show', $groupBooking)
            ->with('success', 'Dossier groupe mis à jour.');
    }

    public function cancel(Request $request, GroupBooking $groupBooking)
    {
        if (in_array($groupBooking->status, ['completed', 'cancelled', 'in_house'])) {
            return back()->withErrors(['cancel' => 'Ce dossier ne peut pas être annulé.']);
        }

        DB::transaction(function () use ($groupBooking) {
            // Annule toutes les réservations éditables
            foreach ($groupBooking->bookings as $booking) {
                if ($booking->isEditable()) {
                    $booking->update(['status' => BookingStatus::CANCELLED]);
                }
            }

            $groupBooking->update(['status' => 'cancelled']);
        });

        return redirect()
            ->route('groups.index')
            ->with('success', "Dossier {$groupBooking->group_code} annulé.");
    }
}
