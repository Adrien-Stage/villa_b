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
        $tab = $request->get('tab', 'active');
        $viewMode = $request->get('view', 'list');
        $status = $request->filled('status') ? $request->status : 'all';

        $query = Booking::with(['customer', 'room.roomType']);

        if ($tab === 'archive') {
            $query->whereIn('status', [BookingStatus::COMPLETED, BookingStatus::CANCELLED]);
            $statusFilters = [
                'all'       => 'Toutes',
                'completed' => 'Terminées',
                'cancelled' => 'Annulées',
            ];
        } else {
            if ($viewMode === 'calendar') {
                $query->where('status', BookingStatus::CONFIRMED);
                $statusFilters = [
                    'confirmed' => 'Confirmées',
                ];
                $status = 'confirmed';
            } else {
                $query->whereNotIn('status', [BookingStatus::COMPLETED, BookingStatus::CANCELLED]);
                $statusFilters = [
                    'all'        => 'Toutes',
                    'pending'    => 'En attente',
                    'confirmed'  => 'Confirmées',
                    'checked_in' => 'En séjour',
                ];
            }
        }

        // Filtre statut
        if ($request->filled('status') && $status !== 'all') {
            $query->where('status', $status);
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

        $calendarBookings = null;
        if ($tab === 'active' && $viewMode === 'calendar') {
            $calendarBookings = (clone $query)
                ->orderBy('check_in')
                ->get()
                ->map(fn($booking) => [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'customer' => $booking->customer->full_name,
                    'room_number' => $booking->room->number,
                    'check_in' => $booking->check_in->format('Y-m-d'),
                    'check_out' => $booking->check_out->format('Y-m-d'),
                    'url' => route('bookings.show', $booking),
                    'status' => $booking->status->value,
                    'status_label' => $booking->status->label(),
                ]);
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

        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('tenant_id', $tenantId)
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();
        $isCashRegisterOpen = $activeSession !== null;

        return view('bookings.index', compact('bookings', 'stats', 'tab', 'statusFilters', 'viewMode', 'status', 'calendarBookings', 'isCashRegisterOpen'));
    }

    // ===== WIZARD ÉTAPE 1 : Sélection client =====

    public function create(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('tenant_id', $tenantId)
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')->with('warning', 'Vous devez ouvrir votre caisse avant de pouvoir enregistrer une réservation.');
        }

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

        $booker = null;
        if ($request->filled('booker_id')) {
            $booker = Customer::find($request->booker_id);
        }

        return view('bookings.create', compact('customer', 'booker', 'customers'));
    }

    // ===== WIZARD ÉTAPE 2 : Choix chambre + dates =====

    public function store(Request $request)
    {
        if ($request->filled('action_back')) {
            if ($request->has('adults_count')) {
                $request->merge(['adults' => $request->adults_count]);
            }
            if ($request->has('children_count')) {
                $request->merge(['children' => $request->children_count]);
            }
            return $this->storeStep2($request);
        }

        // Étape 1 → on stocke le client et on passe à l'étape 2
        if ($request->step === '1') {
            return $this->storeStep1($request);
        }

        // Étape 2 → on cherche les chambres disponibles
        if ($request->step === '2') {
            return $this->storeStep2($request);
        }

        // Étape 3 → confirmation et paiement d'acompte
        if ($request->step === '3') {
            return $this->storeStep3($request);
        }

        // Étape finale → on crée la réservation et le paiement
        return $this->storeBooking($request);
    }

    private function storeStep1(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        // 1. GESTION DU CLIENT FINAL (Celui qui séjourne)
        if ($request->filled('new_customer')) {
            $validated = $request->validate([
                'first_name'         => ['required', 'string', 'max:100'],
                'last_name'          => ['required', 'string', 'max:100'],
                'email'              => ['nullable', 'email'],
                'phone'              => ['nullable', 'string', 'max:30'],
                'nationality'        => ['nullable', 'string', 'max:100'],
                'country'            => ['nullable', 'string', 'max:5'],
                'id_document_type'   => ['nullable', 'string'],
                'id_document_number' => ['nullable', 'string', 'max:50'],
            ]);

            $customer = Customer::create(array_merge($validated, ['tenant_id' => $tenantId]));
        } else {
            $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
            $customer = Customer::findOrFail($request->customer_id);
        }

        // 2. GESTION DU MANDATAIRE (Booker) si l'option est cochée
        $bookerId = null;
        if ($request->is_booker === 'other') {
            if ($request->filled('new_booker')) {
                // Création d'un nouveau profil pour le mandataire
                $validatedBooker = $request->validate([
                    'booker_first_name'         => ['required', 'string', 'max:100'],
                    'booker_last_name'          => ['required', 'string', 'max:100'],
                    'booker_email'              => ['nullable', 'email'],
                    'booker_phone'              => ['nullable', 'string', 'max:30'],
                    'booker_nationality'        => ['nullable', 'string', 'max:100'],
                    'booker_country'            => ['nullable', 'string', 'max:5'],
                    'booker_id_document_type'   => ['nullable', 'string'],
                    'booker_id_document_number' => ['nullable', 'string', 'max:50'],
                ]);
                
                // Mappage des champs préfixés 'booker_' vers les colonnes normales
                $bookerData = [];
                foreach($validatedBooker as $key => $value) {
                    $bookerData[str_replace('booker_', '', $key)] = $value;
                }
                $bookerData['tenant_id'] = $tenantId;
                
                $booker = Customer::create($bookerData);
                $bookerId = $booker->id;
            } else {
                // Mandataire existant sélectionné
                $request->validate(['booker_id' => ['required', 'exists:customers,id']]);
                $bookerId = $request->booker_id;
            }
        }

        return redirect()->route('bookings.create', [
            'customer_id' => $customer->id,
            'booker_id'   => $bookerId,
            'step'        => 2,
        ]);
    }

    private function storeStep2(Request $request)
    {
        $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'booker_id'   => ['nullable', 'exists:customers,id'],
            'check_in'    => ['required', 'date', 'after_or_equal:today'],
            'check_out'   => ['required', 'date', 'after:check_in'],
            'adults'      => ['required', 'integer', 'min:1'],
            'source'      => ['nullable', 'string'],
        ]);

        $customer    = Customer::findOrFail($request->customer_id);
        $bookerId    = $request->booker_id;
        $checkIn     = $request->check_in;
        $checkOut    = $request->check_out;
        $adults      = $request->adults;
        $children    = $request->children ?? 0;
        $source      = $request->source ?? 'direct';
        $totalPeople = $adults + $children;
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $maxCapacityLimit = RoomType::where('tenant_id', $tenantId)->max('max_capacity') ?? 4;

        // Chambres disponibles pour cette période avec capacité suffisante
        $availableRooms = Room::availableBetween($checkIn, $checkOut)
            ->with('roomType')
            ->whereHas('roomType', fn($q) => $q->where('max_capacity', '>=', $totalPeople))
            ->get()
            ->groupBy('room_type_id');

        $roomTypes = RoomType::whereIn('id', $availableRooms->keys())->get();

        return view('bookings.select-room', compact(
            'customer',
            'bookerId',
            'checkIn',
            'checkOut',
            'adults',
            'children',
            'source',
            'availableRooms',
            'roomTypes',
            'maxCapacityLimit'
        ));
    }

    private function storeStep3(Request $request)
    {
        $validated = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'booker_id'    => ['nullable', 'exists:customers,id'],
            'room_id'      => ['required', 'exists:rooms,id'],
            'check_in'     => ['required', 'date'],
            'check_out'    => ['required', 'date', 'after:check_in'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'       => ['nullable', 'string'],
            'notes'        => ['nullable', 'string'],
        ]);

        $room = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn = \Carbon\Carbon::parse($validated['check_in']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out']);
        $nights = $checkIn->diffInDays($checkOut);
        
        // base_price est en centimes en BDD, on divise par 100 pour l'affichage (FCFA), avec surcharge éventuelle
        $pricePerNight = $room->roomType->getCalculatedPricePerNight($validated['adults_count'], $validated['children_count'] ?? 0) / 100;
        $totalRoomAmount = $nights * $pricePerNight;

        // Récupérer le pourcentage d'acompte minimum depuis les paramètres du Tenant
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $tenantSettings = \App\Models\Tenant::where('id', $tenantId)->value('settings') ?? [];
        $minDepositPercentage = $tenantSettings['reception']['min_deposit_percentage'] ?? 30;
        $maxDiscountPercentage = $tenantSettings['reception']['max_discount_percentage'] ?? 10;

        return view('bookings.confirm', [
            'customerId' => $validated['customer_id'],
            'bookerId' => $validated['booker_id'] ?? null,
            'room' => $room,
            'checkIn' => $validated['check_in'],
            'checkOut' => $validated['check_out'],
            'nights' => $nights,
            'adultsCount' => $validated['adults_count'],
            'childrenCount' => $validated['children_count'] ?? 0,
            'source' => $validated['source'] ?? 'direct',
            'notes' => $validated['notes'] ?? '',
            'pricePerNight' => $pricePerNight,
            'totalRoomAmount' => $totalRoomAmount,
            'minDepositPercentage' => $minDepositPercentage,
            'maxDiscountPercentage' => $maxDiscountPercentage
        ]);
    }

    private function storeBooking(Request $request)
    {
        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('tenant_id', $tenantId)
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')->with('warning', 'Veuillez ouvrir la caisse de réception avant d\'enregistrer une réservation.');
        }

        $priceRule = $request->boolean('is_offerte') ? 'min:0' : 'min:1';

        $validated = $request->validate([
            'customer_id'  => ['required', 'exists:customers,id'],
            'booker_id'    => ['nullable', 'exists:customers,id'],
            'room_id'      => ['required', 'exists:rooms,id'],
            'check_in'     => ['required', 'date'],
            'check_out'    => ['required', 'date', 'after:check_in'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'       => ['nullable', 'string'],
            'notes'        => ['nullable', 'string'],
            'custom_price' => ['required', 'numeric', $priceRule],
            'payment_amount' => ['required', 'numeric', $priceRule],
            'payment_method' => ['required', 'string', 'in:orange_money,mtn_momo,cash'],
            'payment_reference' => ['nullable', 'string'],
            'is_offerte'   => ['nullable', 'boolean'],
            'offerte_reason' => [$request->boolean('is_offerte') ? 'required' : 'nullable', 'string', 'max:500'],
        ]);

        $room     = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn  = \Carbon\Carbon::parse($validated['check_in']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out']);
        $nights   = $checkIn->diffInDays($checkOut);

        // Validations spécifiques pour le prix négocié et l'acompte (avec surcharge éventuelle)
        $basePricePerNight = $room->roomType->getCalculatedPricePerNight($validated['adults_count'], $validated['children_count'] ?? 0) / 100;
        $baseTotalRoomAmount = $nights * $basePricePerNight;
        $tenantSettings = \App\Models\Tenant::where('id', $tenantId)->value('settings') ?? [];
        $maxDiscountPercentage = $tenantSettings['reception']['max_discount_percentage'] ?? 10;
        $minDepositPercentage = $tenantSettings['reception']['min_deposit_percentage'] ?? 30;

        // 1. Si réceptionniste, valider que custom_price correspond à une remise autorisée
        if (Auth::user()->hasRole('reception') && !$request->boolean('is_offerte')) {
            $allowedDiscounts = [];
            for ($i = 0; $i <= $maxDiscountPercentage; $i += 5) {
                $allowedDiscounts[] = $i;
            }
            if ($maxDiscountPercentage % 5 !== 0) {
                $allowedDiscounts[] = $maxDiscountPercentage;
            }

            $submittedPrice = (float) $validated['custom_price'];
            $isValidPrice = false;
            $possiblePrices = [];
            foreach ($allowedDiscounts as $discount) {
                $expected = round($baseTotalRoomAmount * (1 - $discount / 100));
                $possiblePrices[] = $expected;
                if (abs($submittedPrice - $expected) < 1.0) {
                    $isValidPrice = true;
                    break;
                }
            }

            if (!$isValidPrice) {
                return back()->withErrors([
                    'custom_price' => "Le prix négocié saisi n'est pas autorisé pour votre rôle. Vous devez utiliser une remise autorisée (jusqu'à {$maxDiscountPercentage}%)."
                ])->withInput();
            }
        }

        // 2. Si non offert, valider le dépôt minimum
        if (!$request->boolean('is_offerte')) {
            $minDeposit = ceil($validated['custom_price'] * ($minDepositPercentage / 100));
            if ($validated['payment_amount'] < $minDeposit) {
                return back()->withErrors([
                    'payment_amount' => "Le montant versé doit être au moins de {$minDeposit} FCFA (acompte de {$minDepositPercentage}%)."
                ])->withInput();
            }
        }

        // On convertit les montants (FCFA) en centimes pour la base de données
        $customPrice = (int) $validated['custom_price'] * 100;
        $paymentAmount = (int) $validated['payment_amount'] * 100;

        // Prix nets sans taxe
        $pricePerNight = $nights > 0 ? (int) round($customPrice / $nights) : 0;
        $totalRoomAmount = $pricePerNight * $nights;
        $taxAmount = 0;
        $totalAmount = $totalRoomAmount;
        $balanceDue = max(0, $totalAmount - $paymentAmount);

        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $status = BookingStatus::CONFIRMED;
        if ($request->boolean('is_offerte') && Auth::user()->hasRole('reception')) {
            $status = BookingStatus::PENDING;
        }

        $notes = $validated['notes'] ?? null;
        if ($request->boolean('is_offerte')) {
            $notes = trim("Offerte - Motif : " . ($validated['offerte_reason'] ?? 'Non spécifié') . ($notes ? "\n" . $notes : ''));
        }

        $booking = Booking::create([
            'tenant_id'       => $tenantId,
            'room_id'         => $room->id,
            'customer_id'     => $validated['customer_id'],
            'booker_id'       => $validated['booker_id'] ?? null,
            'status'          => $status,
            'check_in'        => $validated['check_in'],
            'check_out'       => $validated['check_out'],
            'adults_count'    => $validated['adults_count'],
            'children_count'  => $validated['children_count'] ?? 0,
            'total_nights'    => $nights,
            'price_per_night' => $pricePerNight,
            'total_room_amount' => $totalRoomAmount,
            'extras_amount'   => 0,
            'tax_amount'      => $taxAmount,
            'discount_amount' => 0,
            'total_amount'    => $totalAmount,
            'deposit_amount'  => $paymentAmount,
            'paid_amount'     => $paymentAmount,
            'balance_due'     => $balanceDue,
            'source'          => $validated['source'] ?? 'direct',
            'notes'           => $notes,
            'created_by'      => Auth::id(),
            'checkin_code'    => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
        ]);

        if ($booking->status === BookingStatus::PENDING && $request->boolean('is_offerte')) {
            try {
                $managers = \App\Models\User::where('tenant_id', $tenantId)
                    ->where('role', 'manager')
                    ->get();
                \Illuminate\Support\Facades\Notification::send($managers, new \App\Notifications\ComplimentaryBookingRequested($booking));
                \Illuminate\Support\Facades\Log::info("Notification chambre offerte envoyée aux managers pour la réservation #{$booking->booking_number}");
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Erreur notification chambre offerte #{$booking->booking_number} : " . $e->getMessage());
            }
        }

        // Enregistrer le paiement (Acompte) si montant > 0
        if ($paymentAmount > 0) {
            $payment = \App\Models\Payment::create([
                'tenant_id' => $tenantId,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'amount' => $paymentAmount,
                'currency' => 'XAF',
                'method' => $validated['payment_method'],
                'status' => 'completed',
                'reference' => 'PAY-' . now()->year . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'external_reference' => $validated['payment_reference'] ?? ($validated['payment_method'] === 'cash' ? 'Espèces' : null),
                'paid_at' => now(),
                'processed_by' => Auth::id(),
                'notes' => 'Acompte versé à la réservation',
                'cash_register_session_id' => $activeSession->id,
            ]);
        }

        // Ligne folio hébergement
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

        // Ligne folio pour le paiement (en négatif) si montant > 0
        if ($paymentAmount > 0) {
            FolioItem::create([
                'tenant_id'    => $tenantId,
                'booking_id'   => $booking->id,
                'customer_id'  => $booking->customer_id,
                'type'         => FolioItem::TYPE_PAYMENT,
                'description'  => "Acompte à la réservation ({$validated['payment_method']})",
                'quantity'     => 1,
                'unit_price'   => -$paymentAmount,
                'total_price'  => -$paymentAmount,
                'is_complimentary' => false,
                'earns_points' => false,
                'occurred_at'  => now(),
                'recorded_by'  => Auth::id(),
            ]);
        }

        // Envoi du mail de confirmation avec le code de check-in à 6 chiffres
        try {
            // Charger les relations nécessaires pour le template email
            $booking->load(['customer', 'booker', 'room.roomType']);

            // Envoyer au client final s'il a un e-mail
            if ($booking->customer && !empty($booking->customer->email)) {
                \Illuminate\Support\Facades\Mail::to($booking->customer->email)->send(new \App\Mail\CheckinCodeMail($booking));
                \Illuminate\Support\Facades\Log::info("Mail de checkin envoyé au client {$booking->customer->email} pour la réservation #{$booking->booking_number}");
            }

            // Envoyer au mandataire s'il est présent et a un e-mail
            if ($booking->booker && !empty($booking->booker->email)) {
                \Illuminate\Support\Facades\Mail::to($booking->booker->email)->send(new \App\Mail\CheckinCodeMail($booking));
                \Illuminate\Support\Facades\Log::info("Mail de checkin envoyé au mandataire {$booking->booker->email} pour la réservation #{$booking->booking_number}");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erreur d'envoi du mail de checkin pour la réservation #{$booking->booking_number} : " . $e->getMessage(), [
                'exception' => $e,
                'from' => config('mail.from.address'),
                'mailer' => config('mail.default'),
            ]);
        }

        $successMsg = $booking->status === BookingStatus::PENDING
            ? "Réservation {$booking->booking_number} créée et en attente d'autorisation par le manager."
            : "Réservation {$booking->booking_number} créée et acompte enregistré.";

        return redirect()
            ->route('bookings.show', $booking)
            ->with('success', $successMsg)
            ->with('checkin_code', $booking->checkin_code);
    }

    // ===== DÉTAIL =====

    public function show(Booking $booking)
    {
        $booking->load([
            'customer',
            'booker',
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
        $isAjax = $request->expectsJson();

        if ($booking->status !== BookingStatus::CONFIRMED) {
            $msg = 'Cette réservation ne peut pas être mise en check-in.';
            return $isAjax
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['status' => $msg]);
        }

        // Vérification de sécurité (Code OTP généré à la réservation)
        if ($booking->checkin_code) {
            if ($booking->checkin_attempts >= 3) {
                $msg = 'Nombre maximum de tentatives atteint. Veuillez contacter le manager pour débloquer.';
                return $isAjax
                    ? response()->json(['success' => false, 'message' => $msg, 'locked' => true, 'remaining' => 0], 422)
                    : back()->withErrors(['checkin_code' => $msg]);
            }

            $request->validate(['checkin_code' => 'required|string']);

            if ($request->checkin_code !== $booking->checkin_code) {
                $booking->increment('checkin_attempts');
                $remaining = 3 - $booking->checkin_attempts;

                if ($remaining <= 0) {
                    $msg = 'Nombre maximum de tentatives atteint. Veuillez contacter le manager pour débloquer.';
                    return $isAjax
                        ? response()->json(['success' => false, 'message' => $msg, 'locked' => true, 'remaining' => 0], 422)
                        : back()->withErrors(['checkin_code' => $msg]);
                }

                $msg = "Code de sécurité invalide. Il vous reste {$remaining} tentative(s).";
                return $isAjax
                    ? response()->json(['success' => false, 'message' => $msg, 'locked' => false, 'remaining' => $remaining], 422)
                    : back()->withErrors(['checkin_code' => $msg]);
            }
        }

        DB::transaction(function () use ($booking) {
            $booking->update([
                'status'         => BookingStatus::CHECKED_IN,
                'actual_check_in' => now(),
                'checked_in_by'  => Auth::id(),
                'checkin_attempts' => 0,
            ]);

            $booking->room->updateStatus(
                RoomStatus::OCCUPIED,
                "Check-in {$booking->booking_number}",
                Auth::id()
            );
        });

        $successMsg = "Check-in effectué pour {$booking->customer->full_name}.";
        return $isAjax
            ? response()->json(['success' => true, 'message' => $successMsg])
            : back()->with('success', $successMsg);
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

        \App\Models\AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Annulation de la réservation #{$booking->booking_number} pour {$booking->customer->full_name}",
            'bookings',
            ['booking_id' => $booking->id, 'booking_number' => $booking->booking_number]
        );

        return back()->with('success', 'Réservation annulée.');
    }

    public function approve(Booking $booking)
    {
        if (!Auth::user()->hasRole('manager')) {
            abort(403, 'Seul le manager peut valider cette réservation.');
        }

        $booking->update([
            'status' => BookingStatus::CONFIRMED,
        ]);

        $creator = \App\Models\User::find($booking->created_by);
        if ($creator) {
            $creator->notify(new \App\Notifications\ComplimentaryBookingApproved($booking));
        }

        return back()->with('success', 'La réservation offerte a été validée avec succès.');
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

            $taxAmount    = 0;
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

        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('tenant_id', $tenantId)
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return back()->withErrors(['payment' => 'Veuillez ouvrir la caisse de réception pour enregistrer un paiement.']);
        }

        // Montant saisi en FCFA → on stocke en centimes
        $amountCentimes = $validated['amount'] * 100;

        // Autorise le paiement du solde initialement prévu ou du solde consommé réel (en cas de dépassement)
        $maxAllowed = max($booking->balance_due, $booking->getConsumedBalance());
        if ($amountCentimes > $maxAllowed + 100) {
            return back()->withErrors(['payment' => 'Le montant dépasse le solde dû ou consommé.']);
        }

        // Génère le numéro de paiement de manière robuste pour éviter les collisions
        $payments = \App\Models\Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('reference', 'like', 'PAY-' . now()->year . '-%')
            ->get(['reference']);

        $maxSeq = 0;
        foreach ($payments as $payment) {
            $parts = explode('-', $payment->reference);
            $lastPart = end($parts);
            if (is_numeric($lastPart)) {
                $maxSeq = max($maxSeq, (int) $lastPart);
            }
        }
        $seq = $maxSeq + 1;
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
            'cash_register_session_id' => $activeSession->id,
        ]);

        $this->checkOutService->recalculateTotals($booking);
        $booking->refresh();

        \App\Models\AuditLog::record(
            Auth::id(),
            'sensitive_action',
            "Paiement de " . number_format($amountCentimes / 100, 0, ',', ' ') . " FCFA enregistré pour la réservation #{$booking->booking_number}",
            'bookings',
            ['booking_id' => $booking->id, 'amount' => $amountCentimes, 'reference' => $reference]
        );

        if ($booking->getConsumedBalance() <= 0 && $booking->status === BookingStatus::CHECKED_IN) {
            // Le client a réglé l'intégralité du temps consommé -> on arrête le minuteur
            if (!$booking->actual_check_out) {
                $booking->update(['actual_check_out' => now()]);
            }
            
            // Actualise le folio et les coûts pour correspondre exactement à la durée réelle passée
            $this->checkOutService->syncDurationToNow($booking);
        }

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

        $pricePerNight    = $room->roomType->getCalculatedPricePerNight($validated['adults_count'], $validated['children_count'] ?? 0);
        $totalRoomAmount  = $nights * $pricePerNight;
        $taxAmount        = 0;
        $totalAmount      = $totalRoomAmount;

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
