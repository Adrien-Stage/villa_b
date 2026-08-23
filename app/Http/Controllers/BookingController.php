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
        private CheckOutService $checkOutService,
        private \App\Services\Notifier $notifier,
        private \App\Services\TaxationService $taxation
    ) {}

    // ===== LISTE =====

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'active');
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
            $query->whereNotIn('status', [BookingStatus::COMPLETED, BookingStatus::CANCELLED]);
            $statusFilters = [
                'all'        => 'Toutes',
                'pending'    => 'En attente',
                'confirmed'  => 'Confirmées',
                'checked_in' => 'En séjour',
            ];
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
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();
        $isCashRegisterOpen = $activeSession !== null;

        return view('bookings.index', compact('bookings', 'stats', 'tab', 'statusFilters', 'status', 'isCashRegisterOpen'));
    }

    // ===== AGENDA =====

    /**
     * Agenda des séjours : le calendrier, servi par sa propre entrée de menu.
     *
     * L'agenda couvre le même ensemble que l'onglet « Réservations » de la
     * liste — séjours ni terminés ni annulés — car un calendrier qui
     * masquerait les demandes en attente ferait rater des arrivées.
     */
    public function agenda(Request $request)
    {
        $query = Booking::with(['customer', 'room.roomType'])
            ->whereNotIn('status', [BookingStatus::COMPLETED, BookingStatus::CANCELLED]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $statusFilters = [
            'all'        => 'Toutes',
            'pending'    => 'En attente',
            'confirmed'  => 'Confirmées',
            'checked_in' => 'En séjour',
        ];

        $status = $request->filled('status') ? $request->status : 'all';
        $calendarBookings = $this->agendaBookings($query);

        return view('bookings.agenda', compact('calendarBookings', 'statusFilters', 'status'));
    }

    /**
     * Teintes de l'agenda, dans cet ordre.
     *
     * L'ordre n'est pas décoratif : il a été retenu parce que deux teintes
     * voisines restent distinguables, y compris pour un daltonien (écart
     * OKLab ≥ 8 sur toutes les paires voisines, vision normale ≥ 19).
     * Réordonner ou remplacer une valeur casse cette garantie.
     */
    private const AGENDA_TEINTES = [
        '#2a78d6', // bleu
        '#eb6834', // orange
        '#1baf7a', // turquoise
        '#eda100', // jaune
        '#e87ba4', // magenta
        '#008300', // vert
        '#4a3aa7', // violet
        '#e34948', // rouge
    ];

    /**
     * Réservations affichées dans le calendrier, une couleur par séjour.
     *
     * L'agenda part du mois précédent — pour que les séjours déjà commencés
     * restent visibles — et couvre l'année à venir, au-delà de laquelle la
     * vue n'a plus d'usage. Sans cette fenêtre, la page chargerait toutes les
     * réservations ouvertes de l'établissement à chaque affichage.
     */
    private function agendaBookings($query)
    {
        $debut = now()->startOfMonth()->subMonth()->toDateString();
        $fin   = now()->startOfMonth()->addYear()->endOfMonth()->toDateString();

        $reservations = $query
            ->whereDate('check_out', '>=', $debut)
            ->whereDate('check_in', '<=', $fin)
            ->orderBy('check_in')
            ->orderBy('id')
            ->get();

        $occupees = [];   // teinte => date de fin du dernier séjour posé dessus

        return $reservations->map(function (Booking $booking) use (&$occupees) {
            $arrivee = $booking->check_in->format('Y-m-d');
            $depart  = $booking->check_out->format('Y-m-d');

            return [
                'id'             => $booking->id,
                'booking_number' => $booking->booking_number,
                'customer'       => $booking->customer->full_name,
                'room_number'    => $booking->room->number,
                'check_in'       => $arrivee,
                'check_out'      => $depart,
                'url'            => route('bookings.show', $booking),
                'status'         => $booking->status->value,
                'status_label'   => $booking->status->label(),
                'color'          => self::AGENDA_TEINTES[$this->teintePour($arrivee, $depart, $occupees)],
                // Une demande non confirmée s'affiche en pointillés : le
                // séjour n'est pas encore acquis.
                'is_firm'        => $booking->status !== BookingStatus::PENDING,
            ];
        });
    }

    /**
     * Attribue à un séjour la première teinte qu'aucun séjour affiché en même
     * temps n'occupe déjà. Deux réservations qui se chevauchent — ou qui se
     * touchent, le départ de l'une tombant le jour de l'arrivée de l'autre —
     * ne peuvent donc pas porter la même couleur dans une même case.
     *
     * Les séjours arrivant triés par date d'arrivée, il suffit de retenir la
     * date de fin du dernier séjour posé sur chaque teinte.
     *
     * @param  array<int,string>  $occupees  modifié sur place
     */
    private function teintePour(string $arrivee, string $depart, array &$occupees): int
    {
        foreach (array_keys(self::AGENDA_TEINTES) as $teinte) {
            if (!isset($occupees[$teinte]) || $occupees[$teinte] < $arrivee) {
                $occupees[$teinte] = $depart;

                return $teinte;
            }
        }

        // Plus de huit séjours simultanés : la palette est saturée. On reprend
        // celle libérée depuis le plus longtemps, pour éloigner au maximum les
        // deux séjours qui partagent la couleur. Chaque entrée porte de toute
        // façon le nom du client et le numéro de chambre — la couleur n'est
        // qu'un appui de lecture, jamais la seule identification.
        $teinte = array_search(min($occupees), $occupees, true);
        $occupees[$teinte] = max($occupees[$teinte], $depart);

        return $teinte;
    }

    // ===== WIZARD ÉTAPE 1 : Sélection client =====

    public function create(Request $request)
    {
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            
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

        // Conventions en cours de validité, pour rattacher un nouveau client
        // à son organisation dès sa création.
        $partnerOrganizations = \App\Models\PartnerOrganization::validOn()
            ->orderBy('name')
            ->get();

        $activeDraftsCount = \App\Models\BookingDraft::active()
            ->where('created_by', Auth::id())
            ->count();

        $latestDraft = $activeDraftsCount > 0 ? \App\Models\BookingDraft::active()
            ->where('created_by', Auth::id())
            ->latest('last_activity_at')
            ->first() : null;

        return view('bookings.create', compact('customer', 'booker', 'customers', 'partnerOrganizations', 'activeDraftsCount', 'latestDraft'));
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
                // L'email du client est obligatoire ; la pièce d'identité est
                // désormais collectée au check-in, plus à la réservation.
                'email'              => ['required', 'email', 'max:150'],
                'phone'              => ['nullable', 'string', 'max:30'],
                'nationality'        => ['nullable', 'string', 'max:100'],
                // Le pays de résidence est obligatoire : c'est le marché
                // émetteur, base de l'analyse géographique de la clientèle.
                'country'            => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::all()))],
                // Appartenance déclarée à la création : mémorisée sur la fiche
                // pour être reproposée aux séjours suivants.
                'partner_organization_id' => ['nullable', 'exists:partner_organizations,id'],
            ], [
                'email.required'   => "L'adresse email du client est obligatoire.",
                'country.required' => "Le pays de résidence du client est obligatoire.",
                'country.in'       => "Le pays sélectionné n'est pas reconnu.",
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
                    'booker_country'            => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::all()))],
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

        // Sauvegarde du brouillon à l'étape 1
        $draft = \App\Models\BookingDraft::upsertDraft($request->draft_token, Auth::id(), [
            'current_step' => 2,
            'customer_id'  => $customer->id,
            'booker_id'    => $bookerId,
            'tenant_id'    => Auth::user()->tenant_id,
        ]);

        return redirect()->route('bookings.create', [
            'customer_id' => $customer->id,
            'booker_id'   => $bookerId,
            'draft_token' => $draft->token,
            'step'        => 2,
        ]);
    }

    private function storeStep2(Request $request)
    {
        $request->validate([
            'customer_id'   => ['required', 'exists:customers,id'],
            'booker_id'     => ['nullable', 'exists:customers,id'],
            'check_in'      => ['required', 'date', 'after_or_equal:today'],
            'check_out'     => ['required', 'date', 'after:check_in'],
            'check_in_time' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}:\d{2}$/'],
            'adults'        => ['required', 'integer', 'min:1'],
            'children'      => ['nullable', 'integer', 'min:0'],
            'source'        => ['nullable', 'string'],
        ]);

        $customer    = Customer::findOrFail($request->customer_id);
        $bookerId    = $request->booker_id;
        $checkIn     = $request->check_in;
        $checkOut    = $request->check_out;
        $checkInTime = $request->check_in_time ?? '14:00';
        $adults      = $request->adults;
        $children    = $request->children ?? 0;
        $source      = $request->source ?? 'direct';
        $totalPeople = $adults + $children;
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $maxCapacityLimit = RoomType::max('max_capacity') ?? 4;

        // Sauvegarde du brouillon à l'étape 2
        $draft = \App\Models\BookingDraft::upsertDraft($request->draft_token, Auth::id(), [
            'current_step'  => 3,
            'customer_id'   => $customer->id,
            'booker_id'     => $bookerId,
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'check_in_time' => $checkInTime,
            'adults'        => $adults,
            'children'      => $children,
            'source'        => $source,
            'tenant_id'     => $tenantId,
        ]);
        $draftToken = $draft->token;

        $availabilityService = app(\App\Services\RoomAvailabilityService::class);
        $standardCheckOutTime = $availabilityService->checkOutTime();
        $isArrivalToday = \Carbon\Carbon::parse($checkIn)->isToday();

        // Chambres disponibles pour cette période avec capacité suffisante
        $candidateRooms = Room::availableBetween($checkIn, $checkOut)
            ->with(['roomType', 'statusHistory'])
            ->whereHas('roomType', fn($q) => $q->where('max_capacity', '>=', $totalPeople))
            ->get();

        $roomIds = $candidateRooms->pluck('id');

        // Récupérer les réservations actives aujourd'hui (pour les chambres actuellement occupées)
        $currentBookings = Booking::query()
            ->whereIn('room_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>', today())
            ->get()
            ->keyBy('room_id');

        // Récupérer les réservations dont le départ coïncide avec la date d'arrivée demandée
        $sameDayPriorBookings = Booking::query()
            ->whereIn('room_id', $roomIds)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('check_out', $checkIn)
            ->get()
            ->keyBy('room_id');

        foreach ($candidateRooms as $room) {
            $delay = $availabilityService->delayMinutesFor($room->roomType);
            $sameDayReadyTime = \Carbon\Carbon::parse($standardCheckOutTime)->addMinutes($delay)->format('H:i');

            // 1. Statut Housekeeping / Nettoyage actuel
            $isBeingCleaned = in_array($room->status, [
                \App\Enums\RoomStatus::DIRTY,
                \App\Enums\RoomStatus::CLEANING,
                \App\Enums\RoomStatus::CLEAN,
            ], true);

            $cleaningAvailableAt = $isBeingCleaned ? $availabilityService->availableAt($room) : null;
            $cleaningReadyTime = $cleaningAvailableAt ? $cleaningAvailableAt->format('H:i') : $sameDayReadyTime;
            $cleaningMinutesRemaining = $isBeingCleaned ? $availabilityService->minutesRemaining($room) : 0;
            $cleaningLabel = $isBeingCleaned ? $availabilityService->label($room) : null;

            // 2. Occupation actuelle (en temps réel)
            $currBooking = $currentBookings->get($room->id);
            $room->is_currently_occupied = ($room->status === \App\Enums\RoomStatus::OCCUPIED) || ($currBooking !== null);
            $room->current_checkout_date = $currBooking?->check_out?->format('Y-m-d');
            $room->current_checkout_formatted = $currBooking?->check_out?->locale('fr')->isoFormat('D MMM YYYY');
            $room->current_checkout_time = $standardCheckOutTime;
            $room->current_ready_time = $sameDayReadyTime;

            // 3. Départ du client précédent le jour de l'arrivée demandée ($checkIn)
            $sameDayBooking = $sameDayPriorBookings->get($room->id);
            $hasSameDayDeparture = ($sameDayBooking !== null);

            // 4. Détection des conflits d'horaires :
            // Conflit A : Chambre en nettoyage en cours et arrivée prévue aujourd'hui avant la fin du ménage
            $hasCleaningConflict = false;
            if ($isArrivalToday && $isBeingCleaned && $cleaningAvailableAt) {
                $requestedArrivalToday = today()->setTimeFromTimeString($checkInTime);
                $hasCleaningConflict = $cleaningAvailableAt->greaterThan($requestedArrivalToday) || ($checkInTime < $cleaningReadyTime);
            }

            // Conflit B : Rotation le jour même (départ précédent) et heure d'arrivée avant fin du ménage
            $hasRotationConflict = $hasSameDayDeparture && ($checkInTime < $sameDayReadyTime);

            // Conflit global et heure effective garantie
            $hasAnyConflict = $hasCleaningConflict || $hasRotationConflict;
            $effectiveReadyTime = $hasCleaningConflict 
                ? $cleaningReadyTime 
                : ($hasSameDayDeparture ? $sameDayReadyTime : ($isBeingCleaned ? $cleaningReadyTime : $checkInTime));

            $conflictType = match (true) {
                $hasCleaningConflict => 'cleaning_in_progress',
                $hasRotationConflict => 'same_day_rotation',
                default              => null,
            };

            // Renseignement des propriétés pour la vue
            $room->cleaning_delay_minutes     = $delay;
            $room->is_being_cleaned           = $isBeingCleaned;
            $room->cleaning_ready_time        = $cleaningReadyTime;
            $room->cleaning_minutes_remaining = $cleaningMinutesRemaining;
            $room->cleaning_label             = $cleaningLabel;

            $room->has_same_day_departure     = $hasSameDayDeparture;
            $room->same_day_prior_booking     = $sameDayBooking;
            $room->same_day_checkout_time     = $standardCheckOutTime;
            $room->same_day_ready_time        = $sameDayReadyTime;

            $room->has_cleaning_conflict      = $hasCleaningConflict;
            $room->has_rotation_conflict      = $hasRotationConflict;
            $room->has_any_conflict           = $hasAnyConflict;
            $room->effective_ready_time       = $effectiveReadyTime;
            $room->conflict_type              = $conflictType;
        }

        $availableRooms = $candidateRooms->groupBy('room_type_id');
        $roomTypes = RoomType::whereIn('id', $availableRooms->keys())->get();

        return view('bookings.select-room', compact(
            'customer',
            'bookerId',
            'checkIn',
            'checkOut',
            'checkInTime',
            'adults',
            'children',
            'source',
            'availableRooms',
            'roomTypes',
            'maxCapacityLimit',
            'draftToken'
        ));
    }

    private function storeStep3(Request $request)
    {

        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'booker_id'      => ['nullable', 'exists:customers,id'],
            'room_id'        => ['required', 'exists:rooms,id'],
            'check_in'       => ['required', 'date'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'check_in_time'  => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}:\d{2}$/'],
            'adults_count'   => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'         => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
        ]);

        $room = Room::with('roomType')->findOrFail($validated['room_id']);
        $checkIn = \Carbon\Carbon::parse($validated['check_in']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out']);
        $checkInTime = $validated['check_in_time'] ?? '14:00';
        $nights = $checkIn->diffInDays($checkOut);
        
        // base_price est en centimes en BDD, on divise par 100 pour l'affichage (FCFA), avec surcharge éventuelle
        $pricePerNight = $room->roomType->getCalculatedPricePerNight($validated['adults_count'], $validated['children_count'] ?? 0) / 100;
        $totalRoomAmount = $nights * $pricePerNight;

        // Récupérer le pourcentage d'acompte minimum depuis les paramètres du Tenant
        $tenantId = Auth::user()->tenant_id ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
        $tenantSettings = \App\Models\Tenant::where('id', $tenantId)->value('settings') ?? [];
        $minDepositPercentage = $tenantSettings['reception']['min_deposit_percentage'] ?? 30;
        $maxDiscountPercentage = $tenantSettings['reception']['max_discount_percentage'] ?? 10;

        // Convention du client, si elle est en cours de validité à l'arrivée.
        // La réception peut la retirer pour ce séjour (déplacement privé) via
        // le champ du formulaire de confirmation.
        $customer = Customer::with('partnerOrganization')->find($validated['customer_id']);
        $partnerOrganization = $customer?->partnerOrganization;
        if ($partnerOrganization && !$partnerOrganization->isValidOn($checkIn)) {
            $partnerOrganization = null;
        }

        // Packs proposables pour ce type de chambre. Le montant est calculé ici
        // pour le séjour demandé : la vue n'a plus qu'à l'afficher.
        $occupants = (int) $validated['adults_count'] + (int) ($validated['children_count'] ?? 0);
        $roomPackages = \App\Models\RoomPackage::active()
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($p) => $p->appliesToRoomType($room->room_type_id))
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'contents'    => $p->contentLabels(),
                'mode'        => $p->pricingModeLabel(),
                'amount'      => (int) ($p->amountFor($nights, $occupants) / 100),
                // Remise éventuelle sur la nuitée, calculée sur le tarif de base.
                'room_discount' => (int) ($p->roomDiscountFor((int) round($totalRoomAmount * 100), $nights) / 100),
                // Type et valeur bruts : la remise se recalcule à l'écran sur le
                // prix réellement négocié, sinon un pourcentage afficherait un
                // montant assis sur le tarif de base et divergerait du serveur.
                'discount_type'  => $p->room_discount_type,
                'discount_value' => $p->room_discount_type === \App\Models\RoomPackage::DISCOUNT_AMOUNT
                    ? (int) ($p->room_discount_value / 100)   // centimes → FCFA
                    : (int) $p->room_discount_value,          // pourcentage tel quel
            ])
            ->values()
            ->all();

        return view('bookings.confirm', [
            'customerId' => $validated['customer_id'],
            'bookerId' => $validated['booker_id'] ?? null,
            'partnerOrganization' => $partnerOrganization,
            'roomPackages' => $roomPackages,
            // Remise estimée sur le brut, pour l'afficher avant validation.
            'partnerRoomDiscount' => $partnerOrganization
                ? (int) ($partnerOrganization->roomDiscountFor((int) round($totalRoomAmount * 100), $nights) / 100)
                : 0,
            'room' => $room,
            'checkIn' => $validated['check_in'],
            'checkOut' => $validated['check_out'],
            'checkInTime' => $checkInTime,
            'nights' => $nights,
            'adultsCount' => $validated['adults_count'],
            'childrenCount' => $validated['children_count'] ?? 0,
            'source' => $validated['source'] ?? 'direct',
            'notes' => $validated['notes'] ?? '',
            'pricePerNight' => $pricePerNight,
            'totalRoomAmount' => $totalRoomAmount,
            'minDepositPercentage' => $minDepositPercentage,
            'maxDiscountPercentage' => $maxDiscountPercentage,
            'draftToken' => $this->upsertDraftStep3($request, $validated),
        ]);
    }

    /** Sauvegarde le brouillon à l'étape 3 (chambre sélectionnée). */
    private function upsertDraftStep3(Request $request, array $validated): string
    {
        $draft = \App\Models\BookingDraft::upsertDraft($request->draft_token, Auth::id(), [
            'current_step'  => 4,
            'customer_id'   => $validated['customer_id'],
            'booker_id'     => $validated['booker_id'] ?? null,
            'check_in'      => $validated['check_in'],
            'check_out'     => $validated['check_out'],
            'check_in_time' => $validated['check_in_time'] ?? '14:00',
            'adults'        => $validated['adults_count'],
            'children'      => $validated['children_count'] ?? 0,
            'source'        => $validated['source'] ?? 'direct',
            'room_id'       => $validated['room_id'],
            'notes'         => $validated['notes'] ?? null,
            'tenant_id'     => Auth::user()->tenant_id,
        ]);
        return $draft->token;
    }


    private function storeBooking(Request $request)
    {
        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $activeSession = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('bookings.cash_register.open')->with('warning', 'Veuillez ouvrir la caisse de réception avant d\'enregistrer une réservation.');
        }

        $priceRule = $request->boolean('is_offerte') ? 'min:0' : 'min:1';

        $validated = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'booker_id'      => ['nullable', 'exists:customers,id'],
            'room_id'        => ['required', 'exists:rooms,id'],
            'check_in'       => ['required', 'date'],
            'check_out'      => ['required', 'date', 'after:check_in'],
            'check_in_time'  => ['nullable', 'string', 'max:10', 'regex:/^\d{1,2}:\d{2}$/'],
            'adults_count'   => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'source'         => ['nullable', 'string'],
            'notes'          => ['nullable', 'string'],
            'custom_price'   => ['required', 'numeric', $priceRule],
            'payment_amount' => ['required', 'numeric', $priceRule],
            'payment_method' => ['required', 'string', 'in:orange_money,mtn_momo,cash'],
            'payment_reference' => ['nullable', 'string'],
            'is_offerte'     => ['nullable', 'boolean'],
            'offerte_reason' => [$request->boolean('is_offerte') ? 'required' : 'nullable', 'string', 'max:500'],
            // La réception peut écarter la convention pour un séjour privé.
            'apply_partner_privileges' => ['nullable', 'boolean'],
            'room_package_id' => ['nullable', 'exists:room_packages,id'],
            'draft_token'    => ['nullable', 'string', 'max:64'],
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

        // 1 bis. Convention partenaire du client. La remise se calcule sur le
        // prix négocié et vient en déduction : le brut reste affiché, la remise
        // apparaît en ligne distincte (folio et comptabilité).
        $partnerOrganization = null;
        $partnerDiscount     = 0;   // centimes
        if ($request->boolean('apply_partner_privileges') && !$request->boolean('is_offerte')) {
            $bookingCustomer = Customer::with('partnerOrganization')->find($validated['customer_id']);
            $organization    = $bookingCustomer?->partnerOrganization;

            // La convention doit couvrir la date d'arrivée : une convention
            // échue ne peut pas être appliquée rétroactivement à un séjour.
            if ($organization && $organization->isValidOn($checkIn)) {
                $partnerOrganization = $organization;
                $partnerDiscount = $organization->roomDiscountFor(
                    (int) round($validated['custom_price'] * 100),
                    $nights
                );
            }
        }

        // 1 ter. Formule d'hébergement retenue. Elle ajoute un montant au séjour
        // et peut elle aussi ouvrir droit à une remise sur la nuitée, qui se
        // cumule avec celle du partenaire.
        $roomPackage     = null;
        $packageAmount   = 0;   // centimes
        $packageDiscount = 0;   // centimes
        if (!empty($validated['room_package_id']) && !$request->boolean('is_offerte')) {
            $candidate = \App\Models\RoomPackage::find($validated['room_package_id']);

            // Le pack doit être actif et autorisé sur ce type de chambre : on ne
            // se fie pas au formulaire, qui peut avoir été forgé.
            if ($candidate && $candidate->is_active && $candidate->appliesToRoomType($room->room_type_id)) {
                $roomPackage   = $candidate;
                $occupants     = (int) $validated['adults_count'] + (int) ($validated['children_count'] ?? 0);
                $packageAmount = $candidate->amountFor($nights, $occupants);
                $packageDiscount = $candidate->roomDiscountFor(
                    (int) round($validated['custom_price'] * 100),
                    $nights
                );
            }
        }

        // 2. Si non offert, valider le dépôt minimum. Il porte sur le montant
        // réellement dû — formule comprise, remises déduites : exiger l'acompte
        // sur le brut ferait payer au client une part qu'il ne doit pas.
        if (!$request->boolean('is_offerte')) {
            $netPrice   = max(0, (float) $validated['custom_price']
                                 + $packageAmount / 100
                                 - ($partnerDiscount + $packageDiscount) / 100);
            $minDeposit = ceil($netPrice * ($minDepositPercentage / 100));
            if ($validated['payment_amount'] < $minDeposit) {
                return back()->withErrors([
                    'payment_amount' => "Le montant versé doit être au moins de {$minDeposit} FCFA (acompte de {$minDepositPercentage}%)."
                ])->withInput();
            }
        }

        // On convertit les montants (FCFA) en centimes pour la base de données
        $customPrice = (int) $validated['custom_price'] * 100;
        $paymentAmount = (int) $validated['payment_amount'] * 100;

        // Les prix saisis sont des montants TTC.
        $pricePerNight = $nights > 0 ? (int) round($customPrice / $nights) : 0;
        $totalRoomAmount = $pricePerNight * $nights;
        // Les remises cumulées ne peuvent pas dépasser l'hébergement facturé :
        // l'arrondi du prix par nuitée peut faire varier le brut de quelques
        // centimes par rapport au montant sur lequel elles ont été calculées.
        $totalDiscount = min($partnerDiscount + $packageDiscount, $totalRoomAmount);
        $totalAmount = $totalRoomAmount + $packageAmount - $totalDiscount;
        // La TVA est EXTRAITE du total, jamais ajoutée : le client paie le
        // même montant qu'avant sa mise en service, seule la décomposition
        // apparaît désormais sur la facture.
        $taxAmount = $this->taxation->breakdown($totalAmount)->vat;
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
            'room_id'         => $room->id,
            'customer_id'     => $validated['customer_id'],
            'booker_id'       => $validated['booker_id'] ?? null,
            'partner_organization_id' => $partnerOrganization?->id,
            'room_package_id' => $roomPackage?->id,
            'status'          => $status,
            'check_in'        => $validated['check_in'],
            'check_in_time'   => $validated['check_in_time'] ?? '14:00',
            'check_out'       => $validated['check_out'],
            'adults_count'    => $validated['adults_count'],
            'children_count'  => $validated['children_count'] ?? 0,
            'total_nights'    => $nights,
            'price_per_night' => $pricePerNight,
            'total_room_amount' => $totalRoomAmount,
            'extras_amount'   => 0,
            'package_amount'  => $packageAmount,
            'tax_amount'      => $taxAmount,
            'discount_amount' => $totalDiscount,
            'total_amount'    => $totalAmount,
            'deposit_amount'  => $paymentAmount,
            'paid_amount'     => $paymentAmount,
            'balance_due'     => $balanceDue,
            'source'          => $validated['source'] ?? 'direct',
            'notes'           => $notes,
            'created_by'      => Auth::id(),
            'checkin_code'    => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'tenant_id'       => $tenantId,
        ]);

        // Clôture du brouillon si présent
        if (!empty($validated['draft_token'] ?? $request->draft_token)) {
            \App\Models\BookingDraft::where('token', $validated['draft_token'] ?? $request->draft_token)
                ->where('created_by', Auth::id())
                ->update(['status' => 'completed']);
        }

        $needsApproval = $booking->status === BookingStatus::PENDING && $request->boolean('is_offerte');


        if ($needsApproval) {
            $this->notifier->toRoles(['manager'], new \App\Notifications\ComplimentaryBookingRequested($booking));
        }

        // Nouvelle réservation — in-app (cloche) et push système (même app
        // fermée). Le manager qui vient de recevoir la demande de validation
        // est écarté : deux cloches pour la même réservation noient le signal
        // actionnable sous l'information.
        $creatorName = Auth::user()->name ?? 'Réception';
        $this->notifier->toRoles(
            $needsApproval ? ['reception'] : ['manager', 'reception'],
            new \App\Notifications\NewBookingCreated($booking, $creatorName),
            Auth::id()
        );

        // Enregistrer le paiement (Acompte) si montant > 0
        if ($paymentAmount > 0) {
            $payment = \App\Models\Payment::create([
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

        // Ligne folio de la formule retenue, détaillant ce qu'elle comprend.
        if ($packageAmount > 0 && $roomPackage) {
            $contents = $roomPackage->contentLabels();

            FolioItem::create([
                'booking_id'   => $booking->id,
                'customer_id'  => $booking->customer_id,
                'type'         => FolioItem::TYPE_OTHER,
                'description'  => "Formule — {$roomPackage->name}",
                'quantity'     => 1,
                'unit_price'   => $packageAmount,
                'total_price'  => $packageAmount,
                'is_complimentary' => false,
                'earns_points' => true,
                'occurred_at'  => now(),
                'recorded_by'  => Auth::id(),
                'notes'        => $roomPackage->pricingModeLabel()
                    . (empty($contents) ? '' : ' — ' . implode(' · ', $contents)),
            ]);
        }

        // Lignes folio des remises (en négatif), une par origine : le dossier
        // montre le brut et chaque geste consenti plutôt qu'un net opaque.
        if ($partnerDiscount > 0 && $partnerOrganization) {
            FolioItem::create([
                'booking_id'   => $booking->id,
                'customer_id'  => $booking->customer_id,
                'type'         => FolioItem::TYPE_DISCOUNT,
                'description'  => "Remise partenaire — {$partnerOrganization->name}",
                'quantity'     => 1,
                'unit_price'   => -$partnerDiscount,
                'total_price'  => -$partnerDiscount,
                'is_complimentary' => false,
                'earns_points' => false,
                'occurred_at'  => now(),
                'recorded_by'  => Auth::id(),
                'notes'        => $partnerOrganization->roomDiscountLabel(),
            ]);
        }

        if ($packageDiscount > 0 && $roomPackage) {
            FolioItem::create([
                'booking_id'   => $booking->id,
                'customer_id'  => $booking->customer_id,
                'type'         => FolioItem::TYPE_DISCOUNT,
                'description'  => "Remise formule — {$roomPackage->name}",
                'quantity'     => 1,
                'unit_price'   => -$packageDiscount,
                'total_price'  => -$packageDiscount,
                'is_complimentary' => false,
                'earns_points' => false,
                'occurred_at'  => now(),
                'recorded_by'  => Auth::id(),
                'notes'        => $roomPackage->roomDiscountLabel(),
            ]);
        }

        // Ligne folio pour le paiement (en négatif) si montant > 0
        if ($paymentAmount > 0) {
            FolioItem::create([
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

        // Aucune action n'est possible tant que la caisse de l'utilisateur
        // n'est pas ouverte — la vue masque tous les contrôles d'action et
        // invite à ouvrir la caisse.
        $isCashRegisterOpen = \App\Models\CashRegisterSession::where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->exists();

        // Convention appliquée au séjour : elle marque les prestations offertes
        // dans le catalogue du folio.
        $partnerOrganization = $booking->partnerOrganization;

        return view('bookings.show', [
            'booking' => $booking,
            'isCashRegisterOpen' => $isCashRegisterOpen,
            'folioCatalog' => $this->folioCatalog($partnerOrganization),
            'partnerOrganization' => $partnerOrganization,
        ]);
    }

    /**
     * Catalogue des prestations proposées dans le formulaire d'ajout au folio,
     * indexé par type de ligne de folio.
     *
     * Chaque type renvoie une liste de groupes : [label, options[]], où chaque
     * option porte son libellé, son prix en FCFA et une précision facultative.
     * Le restaurant est groupé par service de repas (petit déj / déjeuner /
     * dîner) ; les autres types viennent du catalogue des prestations géré
     * dans Paramètres › Prestations.
     */
    private function folioCatalog(?\App\Models\PartnerOrganization $organization = null): array
    {
        $catalog = [];
        $grantsFree = $organization && $organization->isValidOn();

        // --- Restaurant : les plats, groupés par service de repas ---
        $menuItems = \App\Models\RestaurantMenuItem::query()
            ->active()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $restaurantGroups = [];

        foreach (\App\Models\RestaurantMenuItem::MEAL_SERVICES as $meal => $mealLabel) {
            $options = $menuItems
                ->filter(fn ($item) => $item->isServedAt($meal))
                ->map(fn ($item) => [
                    'label' => $item->name,
                    'price' => (int) ($item->price / 100),
                    'hint' => $item->category?->name,
                ])
                ->values()
                ->all();

            if ($options !== []) {
                $restaurantGroups[] = ['label' => $mealLabel, 'options' => $options];
            }
        }

        $catalog[FolioItem::TYPE_RESTAURANT] = $restaurantGroups;

        // --- Activités, spa, housekeeping, blanchisserie, minibar ---
        $services = \App\Models\ServiceItem::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        foreach (\App\Models\ServiceItem::CATEGORIES as $category => $categoryLabel) {
            $options = ($services[$category] ?? collect())
                ->map(fn ($service) => [
                    'id'    => $service->id,
                    'label' => $service->name,
                    'price' => $service->priceInFcfa(),
                    'hint'  => $service->duration_minutes ? $service->duration_minutes . ' min' : null,
                    // Prestation couverte par la convention : la réception n'a
                    // rien à calculer, la ligne part offerte.
                    'free'  => $grantsFree && $organization->grantsFreeServiceItem($service->id),
                ])
                ->values()
                ->all();

            $catalog[$category] = $options === []
                ? []
                : [['label' => $categoryLabel, 'options' => $options]];
        }

        return $catalog;
    }

    /**
     * Confirme une réservation reçue depuis le site vitrine (source
     * "website") : passe de PENDING à CONFIRMED. Contrairement à une
     * réservation offerte (validée par un manager, montant à 0), une
     * réservation web est payante — le solde reste dû et s'encaisse
     * normalement via le flux de paiement.
     */
    public function confirm(Booking $booking)
    {
        if (!Auth::user()->hasAnyRole(['reception', 'manager'])) {
            abort(403);
        }

        if ($booking->source !== 'website' || $booking->status !== BookingStatus::PENDING) {
            return back()->withErrors(['confirm' => "Cette réservation ne peut pas être confirmée ici."]);
        }

        $booking->update(['status' => BookingStatus::CONFIRMED]);

        return back()->with('success', "Réservation {$booking->booking_number} confirmée. Le solde est à encaisser.");
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

        // La pièce d'identité du client est relevée à l'arrivée (check-in),
        // plus à la réservation.
        $idValidated = $request->validate([
            'id_document_type'   => ['nullable', 'string', 'in:CNI,Passeport'],
            'id_document_number' => ['required', 'string', 'max:50'],
        ], [
            'id_document_number.required' => "Le numéro de pièce d'identité du client est requis pour le check-in.",
        ]);

        DB::transaction(function () use ($booking, $idValidated) {
            $booking->update([
                'status'         => BookingStatus::CHECKED_IN,
                'actual_check_in' => now(),
                'checked_in_by'  => Auth::id(),
                'checkin_attempts' => 0,
            ]);

            // Enregistre la pièce d'identité relevée sur le client séjournant.
            $booking->customer->update([
                'id_document_type'   => $idValidated['id_document_type'] ?: $booking->customer->id_document_type,
                'id_document_number' => $idValidated['id_document_number'],
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

        $this->notifier->send(
            \App\Models\User::find($booking->created_by),
            new \App\Notifications\ComplimentaryBookingApproved($booking)
        );

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
            // Prestation choisie au catalogue : permet de reconnaître celles
            // que la convention partenaire couvre.
            'service_item_id'  => ['nullable', 'integer', 'exists:service_items,id'],
        ]);

        $tenantId = Auth::user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');

        $isComplimentary = $validated['is_complimentary'] ?? false;
        $notes           = $validated['notes'] ?? null;

        // La gratuité conventionnelle est décidée côté serveur : se fier à la
        // case cochée par le navigateur permettrait d'offrir n'importe quoi.
        $organization = $booking->partnerOrganization;
        if (
            !empty($validated['service_item_id'])
            && $organization
            && $organization->isValidOn()
            && $organization->grantsFreeServiceItem((int) $validated['service_item_id'])
        ) {
            $isComplimentary = true;
            $notes = trim("Offert — convention {$organization->name}" . ($notes ? "\n" . $notes : ''));
        }

        $totalPrice = $isComplimentary
            ? 0
            : (int) round($validated['quantity'] * $validated['unit_price'] * 100);

        FolioItem::create([
            'booking_id'       => $booking->id,
            'customer_id'      => $booking->customer_id,
            'type'             => $validated['type'],
            'description'      => $validated['description'],
            'quantity'         => $validated['quantity'],
            'unit_price'       => $validated['unit_price'] * 100,
            'total_price'      => $totalPrice,
            'is_complimentary' => $isComplimentary,
            'earns_points'     => !$isComplimentary,
            'occurred_at'      => now(),
            'recorded_by'      => Auth::id(),
            'notes'            => $notes,
        ]);

        // Recalcule les extras et le solde du booking
        if (!$isComplimentary) {
            $extrasAmount = $booking->folioItems()
                ->whereNotIn('type', [FolioItem::TYPE_ROOM, FolioItem::TYPE_PAYMENT, FolioItem::TYPE_DISCOUNT])
                ->where('is_complimentary', false)
                ->sum('total_price');

            $totalAmount  = $booking->total_room_amount + $extrasAmount - $booking->discount_amount;
            // TVA extraite du total TTC, jamais ajoutée par dessus.
            $taxAmount    = $this->taxation->breakdown($totalAmount)->vat;
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
            'check_in_time'  => ['nullable', 'string', 'max:10'],
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
            // Même règle que le site : intervalles semi-ouverts et tampon de
            // ménage. Le séjour en cours de modification est ignoré, sinon il
            // se bloquerait lui-même.
            $refus = app(\App\Services\RoomAvailabilityService::class)
                ->conflictReason($room, $checkIn, $checkOut, $booking->id);

            if ($refus !== null) {
                return back()->withErrors(['room_id' => $refus])->withInput();
            }
        }

        $pricePerNight    = $room->roomType->getCalculatedPricePerNight($validated['adults_count'], $validated['children_count'] ?? 0);
        $totalRoomAmount  = $nights * $pricePerNight;
        $totalAmount      = $totalRoomAmount;
        // TVA extraite du total TTC, jamais ajoutée par dessus.
        $taxAmount        = $this->taxation->breakdown($totalAmount)->vat;

        $booking->update([
            'room_id'          => $room->id,
            'check_in'         => $validated['check_in'],
            'check_in_time'    => $validated['check_in_time'] ?? $booking->check_in_time,
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
