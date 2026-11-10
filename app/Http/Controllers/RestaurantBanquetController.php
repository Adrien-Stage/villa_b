<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CashRegisterActivity;
use App\Models\Customer;
use App\Models\PointOfSale;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBanquetPayment;
use App\Models\RestaurantMenuItem;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BanquetNotification;
use App\Services\CashRegisterCircuit;
use App\Services\Notifier;
use App\Services\RestaurantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Les banquets : des événements réservés dans l'un ou l'autre restaurant.
 *
 * Le responsable de restaurant ou la réception (accueil sur place ou téléphone)
 * vérifie la disponibilité des salles en temps réel, établit le devis — date,
 * salle, client CRM ou séjour résident, couverts, menu, acompte demandé —, le
 * confirme dès que l'acompte est encaissé (au restaurant ou en caisse
 * Réception), puis le marque réalisé et soldé.
 */
class RestaurantBanquetController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'transfer', 'other'];

    public function __construct(
        private readonly RestaurantContext $contexte,
        private readonly CashRegisterCircuit $circuit,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();

        $requete = RestaurantBanquet::query()
            ->visiblesPour($user)
            ->with([
                'pointOfSale:id,name',
                'space:id,name,capacity',
                'customer:id,first_name,last_name,phone',
                'booking:id,booking_number,room_id',
                'booking.room:id,number',
            ])
            ->withSum('payments as total_encaisse', 'amount');

        $sourceFilter = (string) $request->query('source', '');
        if ($sourceFilter !== '' && array_key_exists($sourceFilter, RestaurantBanquet::SOURCES)) {
            $requete->where('source', $sourceFilter);
        } else {
            $sourceFilter = '';
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $motif = '%' . mb_strtolower($search, 'UTF-8') . '%';
            $requete->where(function (Builder $q) use ($motif) {
                $q->whereRaw('LOWER(reference) LIKE ?', [$motif])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$motif])
                    ->orWhereRaw('LOWER(client_name) LIKE ?', [$motif])
                    ->orWhereRaw("LOWER(COALESCE(client_phone, '')) LIKE ?", [$motif])
                    ->orWhereRaw("LOWER(COALESCE(client_email, '')) LIKE ?", [$motif]);
            });
        }

        $vue = (string) $request->query('vue', 'a_venir');
        match ($vue) {
            'passes' => $requete->whereDate('event_date', '<', today())->orderByDesc('event_date'),
            'tous' => $requete->orderByDesc('event_date'),
            default => $requete->whereDate('event_date', '>=', today())->where('status', '!=', RestaurantBanquet::ANNULE)->orderBy('event_date'),
        };

        $restaurants = $this->contexte->accessibles($user);
        $salles = $this->salles($restaurants->pluck('id')->all());

        // Vérificateur de disponibilité en temps réel
        $checkParams = $this->extraireParametresDisponibilite($request);
        $disponibilites = $this->calculerDisponibilites($restaurants, $checkParams);

        return view('restaurant.banquets.index', [
            'banquets' => $requete->paginate(20)->withQueryString(),
            'vue' => $vue,
            'sourceFilter' => $sourceFilter,
            'search' => $search,
            'sources' => RestaurantBanquet::SOURCES,
            'defaultSource' => $this->defaultSourcePour($user),
            'restaurants' => $restaurants,
            'restaurantParDefaut' => $this->contexte->pourCreation($user),
            'salles' => $salles,
            'vueEnsemble' => $this->contexte->vueEnsemble($user),
            'checkParams' => $checkParams,
            'disponibilites' => $disponibilites,
            'customers' => $this->clientsCrm(),
            'activeBookings' => $this->sejoursActifs(),
            'menuCatalog' => $this->menuCatalogPour($restaurants),
            'menuSections' => RestaurantBanquet::MENU_SECTIONS,
            'cashRegisterContext' => $user && $user->exerce(['reception'])
                ? $this->circuit->receptionContextePour($user)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $valide = $this->valider($request, isCreate: true);
        $restaurant = $this->restaurantAccessible((int) $valide['point_of_sale_id']);
        $this->verifierSalle($valide, $restaurant);

        $banquet = DB::transaction(function () use ($valide, $restaurant, $user) {
            $clientInfos = $this->resoudreClientEtSejour($valide);

            return RestaurantBanquet::create($this->attributs($valide, $restaurant, $clientInfos) + [
                'reference' => $this->reference(),
                'status' => RestaurantBanquet::DEVIS,
                'source' => $valide['source'] ?? $this->defaultSourcePour($user),
                'created_by' => $user?->id,
            ]);
        });

        AuditLog::record(
            $user?->id,
            'banquet_created',
            "Banquet {$banquet->reference} — {$banquet->title} ({$restaurant->name}, {$banquet->event_date->format('d/m/Y')})",
            'restaurant',
            [
                'banquet_id' => $banquet->id,
                'source' => $banquet->source,
                'customer_id' => $banquet->customer_id,
                'booking_id' => $banquet->booking_id,
            ]
        );

        if ($user && $user->exerce(['reception']) && ($activeSession = $this->circuit->receptionActivePour($user))) {
            $this->circuit->enregistrerInterventionTiers(
                $activeSession,
                $user,
                CashRegisterActivity::ACTION_BANQUET_CREATE,
                "Devis banquet {$banquet->reference} — {$banquet->title} ({$banquet->client_name})",
                null,
                null,
                $banquet,
                ['banquet_id' => $banquet->id, 'reference' => $banquet->reference]
            );
        }

        $this->notifierEquipeRestaurant($banquet, BanquetNotification::EVENT_CREATED);

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', "Devis {$banquet->reference} enregistré.");
    }

    public function show(RestaurantBanquet $banquet): View
    {
        $user = Auth::user();
        $banquet->load([
            'pointOfSale',
            'space',
            'customer',
            'booking.room',
            'createdBy:id,name',
            'payments' => fn ($q) => $q->with(['paidBy:id,name', 'cashRegisterSession.user:id,name'])->latest('paid_at'),
        ]);
        $restaurants = $this->contexte->accessibles($user);

        return view('restaurant.banquets.show', [
            'banquet' => $banquet,
            'encaisse' => $banquet->encaisse(),
            'resteDu' => $banquet->resteDu(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'caisse' => $this->resoudreSessionOuverte($user),
            'cashRegisterContext' => $user && $user->exerce(['reception'])
                ? $this->circuit->receptionContextePour($user)
                : null,
            'restaurants' => $restaurants,
            'salles' => $this->salles($restaurants->pluck('id')->all()),
            'sources' => RestaurantBanquet::SOURCES,
            'defaultSource' => $banquet->source ?: $this->defaultSourcePour($user),
            'customers' => $this->clientsCrm(),
            'activeBookings' => $this->sejoursActifs(),
            'menuCatalog' => $this->menuCatalogPour($restaurants),
            'menuSections' => RestaurantBanquet::MENU_SECTIONS,
        ]);
    }

    public function print(RestaurantBanquet $banquet): View
    {
        $banquet->load([
            'pointOfSale',
            'space',
            'customer',
            'booking.room',
            'createdBy:id,name',
            'payments' => fn ($q) => $q->with(['paidBy:id,name', 'cashRegisterSession'])->orderBy('paid_at'),
        ]);

        return view('restaurant.banquets.print', [
            'banquet' => $banquet,
            'encaisse' => $banquet->encaisse(),
            'resteDu' => $banquet->resteDu(),
            'tenant' => Auth::user()?->tenant ?? Tenant::current() ?? Tenant::first(),
        ]);
    }

    public function update(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        if (! $banquet->estModifiable()) {
            return back()->withErrors(['banquet' => 'Un banquet réalisé, soldé ou annulé ne se modifie plus.']);
        }

        $user = Auth::user();
        $valide = $this->valider($request, isCreate: false);
        $restaurant = $this->restaurantAccessible((int) $valide['point_of_sale_id']);
        $this->verifierSalle($valide, $restaurant, $banquet);

        $previewTotal = RestaurantBanquet::totalPour(
            (int) $valide['covers'],
            (int) $valide['price_per_cover'] * 100,
            (int) ($valide['extras_amount'] ?? 0) * 100
        );

        if ($previewTotal < $banquet->encaisse()) {
            return back()->withInput()->withErrors(['covers' => 'Le nouveau total serait inférieur à ce qui est déjà encaissé.']);
        }

        DB::transaction(function () use ($valide, $restaurant, $banquet) {
            $clientInfos = $this->resoudreClientEtSejour($valide, $banquet);
            $attributs = $this->attributs($valide, $restaurant, $clientInfos);
            if (! empty($valide['source'])) {
                $attributs['source'] = $valide['source'];
            }
            $banquet->update($attributs);
        });

        AuditLog::record(
            $user?->id,
            'banquet_updated',
            "Banquet {$banquet->reference} mis à jour ({$banquet->title})",
            'restaurant',
            ['banquet_id' => $banquet->id]
        );

        if ($user && $user->exerce(['reception']) && ($activeSession = $this->circuit->receptionActivePour($user))) {
            $this->circuit->enregistrerInterventionTiers(
                $activeSession,
                $user,
                CashRegisterActivity::ACTION_BANQUET_UPDATE,
                "Modification banquet {$banquet->reference} — {$banquet->title}",
                null,
                null,
                $banquet,
                ['banquet_id' => $banquet->id]
            );
        }

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', 'Banquet mis à jour.');
    }

    /** Confirmer, marquer réalisé, annuler. */
    public function status(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        $user = Auth::user();
        $valide = $request->validate([
            'status' => ['required', Rule::in([RestaurantBanquet::CONFIRME, RestaurantBanquet::REALISE, RestaurantBanquet::ANNULE])],
        ]);

        $cible = $valide['status'];
        $permis = [
            RestaurantBanquet::CONFIRME => [RestaurantBanquet::DEVIS],
            RestaurantBanquet::REALISE => [RestaurantBanquet::CONFIRME],
            RestaurantBanquet::ANNULE => [RestaurantBanquet::DEVIS, RestaurantBanquet::CONFIRME],
        ];

        if (! in_array($banquet->status, $permis[$cible], true)) {
            return back()->withErrors(['status' => "Un banquet « {$banquet->libelleStatut()} » ne peut pas passer à « ".RestaurantBanquet::STATUTS[$cible].' ».']);
        }

        // La réservation tient quand l'acompte demandé est encaissé.
        if ($cible === RestaurantBanquet::CONFIRME && $banquet->encaisse() < $banquet->deposit_required) {
            return back()->withErrors(['status' => 'L\'acompte demandé n\'est pas encore encaissé : la réservation ne peut pas être confirmée.']);
        }

        $attributs = ['status' => $cible];
        if ($cible === RestaurantBanquet::CONFIRME) {
            $attributs['confirmed_at'] = now();
        }
        if ($cible === RestaurantBanquet::ANNULE) {
            $attributs['canceled_at'] = now();
        }
        // Réalisé et déjà tout payé : il est soldé.
        if ($cible === RestaurantBanquet::REALISE && $banquet->resteDu() === 0) {
            $attributs['status'] = RestaurantBanquet::SOLDE;
        }

        $banquet->update($attributs);

        AuditLog::record($user?->id, 'banquet_status', "Banquet {$banquet->reference} : {$banquet->libelleStatut()}", 'restaurant', [
            'banquet_id' => $banquet->id,
            'status' => $banquet->status,
        ]);

        if ($user && $user->exerce(['reception']) && ($activeSession = $this->circuit->receptionActivePour($user))) {
            $this->circuit->enregistrerInterventionTiers(
                $activeSession,
                $user,
                CashRegisterActivity::ACTION_BANQUET_STATUS,
                "Statut banquet {$banquet->reference} : {$banquet->libelleStatut()}",
                null,
                null,
                $banquet,
                ['banquet_id' => $banquet->id, 'status' => $banquet->status]
            );
        }

        if ($banquet->status === RestaurantBanquet::CONFIRME) {
            $this->notifierEquipeRestaurant($banquet, BanquetNotification::EVENT_CONFIRMED);
        }

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', "Banquet {$banquet->libelleStatut()}.");
    }

    /** Acompte ou solde, encaissé au restaurant ou à la caisse Réception. */
    public function storePayment(Request $request, RestaurantBanquet $banquet): RedirectResponse
    {
        if ($banquet->status === RestaurantBanquet::ANNULE || $banquet->status === RestaurantBanquet::SOLDE) {
            return back()->withErrors(['payment' => 'Ce banquet n\'attend plus de règlement.']);
        }

        $user = Auth::user();
        $valide = $request->validate([
            // Saisi en FCFA, stocké en centimes.
            'amount' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:255'],
            'confirm_now' => ['nullable', 'boolean'],
        ]);

        $montant = (int) $valide['amount'] * 100;

        if ($montant > $banquet->resteDu()) {
            return back()->withInput()->withErrors(['amount' => 'Le montant dépasse ce qui reste dû ('.number_format($banquet->resteDu() / 100, 0, ',', ' ').' FCFA).']);
        }

        $session = $this->resoudreSessionOuverte($user);

        if (! $session) {
            return back()->withInput()->withErrors(['cash_register' => 'Ouvrez votre caisse avant d\'encaisser.']);
        }

        if ($session->module === 'restaurant' && $session->point_of_sale_id && $session->point_of_sale_id !== $banquet->point_of_sale_id) {
            return back()->withInput()->withErrors(['cash_register' => 'Ce banquet est celui d\'un autre restaurant : il s\'encaisse à la caisse de ce restaurant.']);
        }

        $banquetPayment = DB::transaction(function () use ($banquet, $montant, $valide, $session, $user) {
            $kind = $banquet->status === RestaurantBanquet::REALISE
                ? RestaurantBanquetPayment::SOLDE
                : RestaurantBanquetPayment::ACOMPTE;

            $payment = RestaurantBanquetPayment::create([
                'restaurant_banquet_id' => $banquet->id,
                'reference' => $this->paymentReference(),
                // Avant l'événement, c'est un acompte ; après, le solde.
                'kind' => $kind,
                'amount' => $montant,
                'payment_method' => $valide['payment_method'],
                'cash_register_session_id' => $session->id,
                'paid_by' => $user?->id,
                'paid_at' => now(),
                'notes' => $valide['notes'] ?? null,
            ]);

            if ($banquet->status === RestaurantBanquet::REALISE && $banquet->resteDu() === 0) {
                $banquet->update(['status' => RestaurantBanquet::SOLDE]);
            }

            return $payment;
        });

        AuditLog::record($user?->id, 'banquet_payment', sprintf(
            'Règlement %s de %s FCFA pour le banquet %s (%s)',
            $banquetPayment->receiptReference(),
            number_format($montant / 100, 0, ',', ' '),
            $banquet->reference,
            $valide['payment_method']
        ), 'restaurant', [
            'banquet_id' => $banquet->id,
            'banquet_payment_id' => $banquetPayment->id,
            'amount' => $montant,
            'session_module' => $session->module,
        ]);

        // Confirmation directe demandée depuis l'écran dès que l'acompte requis est couvert.
        $autoConfirmed = false;
        if (
            $request->boolean('confirm_now')
            && $banquet->status === RestaurantBanquet::DEVIS
            && $banquet->encaisse() >= $banquet->deposit_required
        ) {
            $banquet->update([
                'status' => RestaurantBanquet::CONFIRME,
                'confirmed_at' => now(),
            ]);
            $autoConfirmed = true;

            AuditLog::record($user?->id, 'banquet_status', "Banquet {$banquet->reference} : Confirmé suite à l'encaissement de l'acompte", 'restaurant', [
                'banquet_id' => $banquet->id,
                'status' => RestaurantBanquet::CONFIRME,
            ]);
        }

        if ($session->module === 'reception' && $user) {
            $origin = $banquetPayment->kind === RestaurantBanquetPayment::SOLDE ? 'Solde banquet' : 'Acompte banquet';
            $this->circuit->enregistrerInterventionTiers(
                $session,
                $user,
                CashRegisterActivity::ACTION_BANQUET_PAYMENT,
                "{$origin} — {$banquet->reference} ({$banquet->title}, {$banquetPayment->receiptReference()})",
                $montant,
                $valide['payment_method'],
                $banquetPayment,
                [
                    'banquet_id' => $banquet->id,
                    'banquet_payment_id' => $banquetPayment->id,
                    'kind' => $banquetPayment->kind,
                ]
            );
        }

        if ($autoConfirmed) {
            $this->notifierEquipeRestaurant($banquet->fresh(), BanquetNotification::EVENT_CONFIRMED);
        }

        $message = $autoConfirmed
            ? "Règlement {$banquetPayment->receiptReference()} enregistré — banquet confirmé."
            : "Règlement {$banquetPayment->receiptReference()} enregistré.";

        return redirect()->route('restaurant.banquets.show', $banquet)->with('success', $message);
    }

    /**
     * Caisse restaurant si ouverte pour l'utilisateur ; sinon caisse Réception
     * active (y compris tiroir partagé supervisé par le chef de réception).
     */
    private function resoudreSessionOuverte(?User $user)
    {
        if (! $user) {
            return null;
        }

        $restaurantSession = $this->circuit->ouverte($user, 'restaurant');
        if ($restaurantSession) {
            return $restaurantSession;
        }

        if ($user->exerce(['reception'])) {
            return $this->circuit->receptionActivePour($user);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, bool $isCreate): array
    {
        $dateRules = $isCreate ? ['required', 'date', 'after_or_equal:today'] : ['required', 'date'];

        return $request->validate([
            'point_of_sale_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(array_keys(RestaurantBanquet::SOURCES))],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'create_customer' => ['nullable', 'boolean'],
            'quick_first_name' => ['nullable', 'string', 'max:255'],
            'quick_last_name' => ['nullable', 'string', 'max:255'],
            'client_name' => [
                Rule::requiredIf(fn () => ! $request->filled('customer_id')
                    && ! $request->filled('booking_id')
                    && ! ($request->boolean('create_customer') && ($request->filled('quick_first_name') || $request->filled('quick_last_name')))),
                'nullable',
                'string',
                'max:255',
            ],
            'client_phone' => ['nullable', 'string', 'max:40'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'event_date' => $dateRules,
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', Rule::when($request->filled('start_time'), ['after:start_time'])],
            'space_id' => ['nullable', 'integer'],
            'covers' => ['required', 'integer', 'min:1', 'max:100000'],
            // Saisis en FCFA, stockés en centimes.
            'price_per_cover' => ['required', 'integer', 'min:0', 'max:5000000'],
            'extras_amount' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'deposit_required' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'menu' => ['nullable', 'string', 'max:5000'],
            'menu_items' => ['nullable', 'array', 'max:60'],
            'menu_items.*.course' => ['nullable', Rule::in(array_keys(RestaurantBanquet::MENU_SECTIONS))],
            'menu_items.*.menu_item_id' => ['nullable', 'integer', Rule::exists('restaurant_menu_items', 'id')],
            'menu_items.*.name' => ['nullable', 'string', 'max:160'],
            'menu_items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:50000000'],
            'menu_items.*.notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'start_time.required_with' => 'Indiquez l\'heure de début lorsque l\'heure de fin est renseignée.',
            'end_time.after' => 'L\'heure de fin doit suivre l\'heure de début.',
            'event_date.after_or_equal' => 'La date de l\'événement ne peut pas être antérieure à aujourd\'hui.',
            'client_name.required' => 'Indiquez le nom du client ou sélectionnez une fiche CRM.',
        ]);
    }

    /** @return array{customer_id: int|null, booking_id: int|null, client_name: string, client_phone: string|null, client_email: string|null} */
    private function resoudreClientEtSejour(array $valide, ?RestaurantBanquet $existant = null): array
    {
        $customerId = ! empty($valide['customer_id']) ? (int) $valide['customer_id'] : null;
        $bookingId = ! empty($valide['booking_id']) ? (int) $valide['booking_id'] : null;
        $clientName = trim((string) ($valide['client_name'] ?? ''));
        $clientPhone = isset($valide['client_phone']) && trim((string) $valide['client_phone']) !== ''
            ? trim((string) $valide['client_phone'])
            : null;
        $clientEmail = isset($valide['client_email']) && trim((string) $valide['client_email']) !== ''
            ? trim((string) $valide['client_email'])
            : null;

        $booking = $bookingId ? Booking::with('customer')->find($bookingId) : null;
        if ($booking && ! $customerId && $booking->customer_id) {
            $customerId = (int) $booking->customer_id;
        }

        $customer = $customerId ? Customer::find($customerId) : null;

        if ($customer) {
            if ($clientName === '') {
                $clientName = trim($customer->first_name . ' ' . $customer->last_name);
            }
            $clientPhone ??= $customer->phone;
            $clientEmail ??= $customer->email;
        } elseif (! empty($valide['create_customer'])) {
            $first = trim((string) ($valide['quick_first_name'] ?? ''));
            $last = trim((string) ($valide['quick_last_name'] ?? ''));

            if ($first === '' && $last === '' && $clientName !== '') {
                $parts = preg_split('/\s+/', $clientName, 2) ?: [];
                $first = $parts[0] ?? 'Client';
                $last = $parts[1] ?? 'Banquet';
            }

            if ($first !== '' || $last !== '') {
                $customer = Customer::create([
                    'first_name' => $first !== '' ? $first : 'Client',
                    'last_name' => $last !== '' ? $last : 'Banquet',
                    'phone' => $clientPhone,
                    'email' => $clientEmail,
                    'tenant_id' => Auth::user()?->tenant_id ?? Tenant::current()?->id ?? Tenant::first()?->id,
                    'notes' => 'Fiche créée lors d\'une demande de banquet',
                ]);
                $customerId = $customer->id;
                if ($clientName === '') {
                    $clientName = trim($customer->first_name . ' ' . $customer->last_name);
                }
            }
        }

        return [
            'customer_id' => $customerId,
            'booking_id' => $booking?->id,
            'client_name' => $clientName,
            'client_phone' => $clientPhone,
            'client_email' => $clientEmail,
        ];
    }

    /** @return array<string, mixed> */
    private function attributs(array $valide, PointOfSale $restaurant, array $clientInfos): array
    {
        $prix = (int) $valide['price_per_cover'] * 100;
        $supplements = (int) ($valide['extras_amount'] ?? 0) * 100;
        $total = RestaurantBanquet::totalPour((int) $valide['covers'], $prix, $supplements);
        $resolvedMenuItems = $this->resolveMenuItems($valide['menu_items'] ?? [], $restaurant);
        $freeMenuText = trim((string) ($valide['menu'] ?? ''));

        $attributs = [
            'point_of_sale_id' => $restaurant->id,
            'title' => trim($valide['title']),
            'customer_id' => $clientInfos['customer_id'],
            'booking_id' => $clientInfos['booking_id'],
            'client_name' => $clientInfos['client_name'],
            'client_phone' => $clientInfos['client_phone'],
            'client_email' => $clientInfos['client_email'],
            'event_date' => $valide['event_date'],
            'start_time' => $valide['start_time'] ?? null,
            'end_time' => $valide['end_time'] ?? null,
            'space_id' => $valide['space_id'] ?? null,
            'covers' => (int) $valide['covers'],
            'price_per_cover' => $prix,
            'extras_amount' => $supplements,
            'total_amount' => $total,
            'deposit_required' => min($total, (int) ($valide['deposit_required'] ?? 0) * 100),
            'menu' => $freeMenuText !== '' ? $freeMenuText : null,
            'notes' => $valide['notes'] ?? null,
        ];

        if ($this->hasMenuItemsColumn()) {
            $attributs['menu_items'] = $resolvedMenuItems !== [] ? $resolvedMenuItems : null;
        } elseif ($resolvedMenuItems !== []) {
            $synthese = RestaurantBanquet::formatMenuText($resolvedMenuItems);
            $attributs['menu'] = $freeMenuText !== '' ? $synthese."\n\n".$freeMenuText : $synthese;
        }

        return $attributs;
    }

    private function hasMenuItemsColumn(): bool
    {
        return Schema::hasColumn('restaurant_banquets', 'menu_items');
    }

    /**
     * Valide et normalise la composition structurée du menu du banquet.
     *
     * @param  array<int, mixed>  $rawItems
     * @return array<int, array<string, mixed>>
     */
    private function resolveMenuItems(array $rawItems, PointOfSale $restaurant): array
    {
        if ($rawItems === []) {
            return [];
        }

        $catalogIds = collect($rawItems)
            ->pluck('menu_item_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $catalogById = $catalogIds !== []
            ? RestaurantMenuItem::query()
                ->where('point_of_sale_id', $restaurant->id)
                ->whereIn('id', $catalogIds)
                ->get()
                ->keyBy('id')
            : collect();

        $resolved = [];
        foreach ($rawItems as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $catalogId = ! empty($row['menu_item_id']) ? (int) $row['menu_item_id'] : null;
            $catalogItem = null;
            if ($catalogId !== null) {
                $catalogItem = $catalogById->get($catalogId);
                if (! $catalogItem) {
                    throw ValidationException::withMessages([
                        'menu_items' => "Un plat sélectionné n'appartient pas au restaurant {$restaurant->name}.",
                    ]);
                }
            }

            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' && $catalogItem) {
                $name = trim((string) $catalogItem->name);
            }
            if ($name === '') {
                throw ValidationException::withMessages([
                    "menu_items.{$index}.name" => 'Indiquez le nom de chaque plat ajouté au menu du banquet.',
                ]);
            }

            $course = (string) ($row['course'] ?? 'plat');
            if (! array_key_exists($course, RestaurantBanquet::MENU_SECTIONS)) {
                $course = 'plat';
            }

            $hasExplicitPrice = array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== '';
            $unitPriceCentimes = $hasExplicitPrice
                ? max(0, (int) $row['unit_price']) * 100
                : ($catalogItem ? max(0, (int) $catalogItem->price) : 0);

            $notes = trim((string) ($row['notes'] ?? ''));

            $resolved[] = [
                'course' => $course,
                'menu_item_id' => $catalogItem?->id,
                'name' => $name,
                'unit_price' => $unitPriceCentimes,
                'notes' => $notes !== '' ? $notes : null,
            ];
        }

        return $resolved;
    }

    /** Le banquet se tient dans un restaurant que la personne voit. */
    private function restaurantAccessible(int $id): PointOfSale
    {
        return $this->contexte->accessibles(Auth::user())->firstWhere('id', $id)
            ?? throw ValidationException::withMessages(['point_of_sale_id' => 'Choisissez un restaurant où vous travaillez.']);
    }

    /**
     * La salle est celle du restaurant, ou une salle polyvalente ; sa capacité
     * accueille les couverts demandés ; et elle n'est pas déjà retenue ce jour-là.
     */
    private function verifierSalle(array $valide, PointOfSale $restaurant, ?RestaurantBanquet $banquet = null): void
    {
        if (empty($valide['space_id'])) {
            return;
        }

        $salle = Space::query()->active()->whereKey((int) $valide['space_id'])
            ->where(fn (Builder $q) => $q->where('point_of_sale_id', $restaurant->id)->orWhereNull('point_of_sale_id'))
            ->first();

        if (! $salle) {
            throw ValidationException::withMessages(['space_id' => "Cette salle n'appartient pas à {$restaurant->name}."]);
        }

        if ($salle->capacity !== null && (int) $valide['covers'] > (int) $salle->capacity) {
            throw ValidationException::withMessages([
                'covers' => "La salle {$salle->name} a une capacité maximale de {$salle->capacity} couverts.",
            ]);
        }

        $autres = RestaurantBanquet::query()
            ->where('space_id', $salle->id)
            ->whereDate('event_date', $valide['event_date'])
            ->where('status', '!=', RestaurantBanquet::ANNULE)
            ->when($banquet, fn ($q) => $q->whereKeyNot($banquet->id))
            ->get();

        foreach ($autres as $autre) {
            // Deux banquets dans la même salle le même jour ne se tiennent
            // que s'ils ne se chevauchent pas.
            $chevauche = ! ($valide['start_time'] ?? null) || ! ($valide['end_time'] ?? null) || ! $autre->start_time || ! $autre->end_time
                || ($valide['start_time'] < $autre->end_time && $autre->start_time < $valide['end_time']);

            if ($chevauche) {
                throw ValidationException::withMessages(['space_id' => "La salle {$salle->name} est déjà retenue ce jour-là ({$autre->reference} — {$autre->title})."]);
            }
        }
    }

    /**
     * @return array{active: bool, check_date: string|null, check_start_time: string|null, check_end_time: string|null, check_covers: int|null, check_pos_id: int|null}
     */
    private function extraireParametresDisponibilite(Request $request): array
    {
        $rawDate = trim((string) $request->query('check_date', ''));
        $validDate = null;
        if ($rawDate !== '') {
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', $rawDate);
                if ($parsed && $parsed->format('Y-m-d') === $rawDate) {
                    $validDate = $rawDate;
                }
            } catch (\Throwable) {
                $validDate = null;
            }
        }

        $rawStart = trim((string) $request->query('check_start_time', ''));
        $rawEnd = trim((string) $request->query('check_end_time', ''));
        $startTime = preg_match('/^\d{2}:\d{2}$/', $rawStart) ? $rawStart : null;
        $endTime = preg_match('/^\d{2}:\d{2}$/', $rawEnd) ? $rawEnd : null;

        $covers = $request->filled('check_covers') && (int) $request->query('check_covers') > 0
            ? (int) $request->query('check_covers')
            : null;

        $posId = $request->filled('check_pos_id') && (int) $request->query('check_pos_id') > 0
            ? (int) $request->query('check_pos_id')
            : null;

        return [
            'active' => $validDate !== null,
            'check_date' => $validDate,
            'check_start_time' => $startTime,
            'check_end_time' => $endTime,
            'check_covers' => $covers,
            'check_pos_id' => $posId,
        ];
    }

    /**
     * Évalue en temps réel l'état de chaque salle active (libre, occupée ou capacité insuffisante).
     *
     * @param  Collection<int, PointOfSale>  $restaurants
     * @param  array<string, mixed>  $params
     * @return Collection<int, array<string, mixed>>
     */
    private function calculerDisponibilites(Collection $restaurants, array $params): Collection
    {
        if (! $params['active']) {
            return collect();
        }

        $restaurantIds = $restaurants->pluck('id')->all();
        if ($params['check_pos_id'] && in_array($params['check_pos_id'], $restaurantIds, true)) {
            $filteredPosIds = [$params['check_pos_id']];
        } else {
            $filteredPosIds = $restaurantIds;
        }

        $spaces = Space::query()
            ->active()
            ->with('pointOfSale:id,name')
            ->where(fn (Builder $q) => $q->whereIn('point_of_sale_id', $filteredPosIds)->orWhereNull('point_of_sale_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($spaces->isEmpty()) {
            return collect();
        }

        $banquetsJour = RestaurantBanquet::query()
            ->whereIn('space_id', $spaces->pluck('id')->all())
            ->whereDate('event_date', $params['check_date'])
            ->where('status', '!=', RestaurantBanquet::ANNULE)
            ->with('pointOfSale:id,name')
            ->get()
            ->groupBy('space_id');

        $startTime = $params['check_start_time'];
        $endTime = $params['check_end_time'];
        $covers = $params['check_covers'];
        $fallbackPosId = $params['check_pos_id'] ?? $restaurants->first()?->id;

        return $spaces->map(function (Space $space) use ($banquetsJour, $startTime, $endTime, $covers, $fallbackPosId) {
            $reservations = $banquetsJour->get($space->id, collect());
            $conflits = $reservations->filter(function (RestaurantBanquet $autre) use ($startTime, $endTime) {
                return ! $startTime || ! $endTime || ! $autre->start_time || ! $autre->end_time
                    || ($startTime < $autre->end_time && $autre->start_time < $endTime);
            })->values();

            $capaciteInsuffisante = $covers !== null && $space->capacity !== null && $covers > (int) $space->capacity;

            if ($conflits->isNotEmpty()) {
                $status = 'occupee';
            } elseif ($capaciteInsuffisante) {
                $status = 'capacite_insuffisante';
            } else {
                $status = 'libre';
            }

            return [
                'space' => $space,
                'status' => $status,
                'conflicts' => $conflits,
                'suggested_pos_id' => $space->point_of_sale_id ?? $fallbackPosId,
            ];
        })->values();
    }

    /** @param Collection<int, PointOfSale> $restaurants @return Collection<int, RestaurantMenuItem> */
    private function menuCatalogPour(Collection $restaurants): Collection
    {
        $posIds = $restaurants->pluck('id')->all();
        if ($posIds === []) {
            return collect();
        }

        return RestaurantMenuItem::query()
            ->whereIn('point_of_sale_id', $posIds)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'point_of_sale_id', 'restaurant_menu_category_id', 'name', 'price', 'type', 'description']);
    }

    /** @param list<int> $restaurants @return Collection<int, Space> */
    private function salles(array $restaurants): Collection
    {
        return Space::query()->active()
            ->where(fn (Builder $q) => $q->whereIn('point_of_sale_id', $restaurants)->orWhereNull('point_of_sale_id'))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'capacity', 'point_of_sale_id']);
    }

    private function clientsCrm(): Collection
    {
        return Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(300)
            ->get(['id', 'first_name', 'last_name', 'phone', 'email']);
    }

    private function sejoursActifs(): Collection
    {
        return Booking::query()
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN])
            ->with(['customer:id,first_name,last_name,phone,email', 'room:id,number'])
            ->orderBy('check_in')
            ->limit(150)
            ->get(['id', 'booking_number', 'customer_id', 'room_id', 'status', 'check_in', 'check_out']);
    }

    private function defaultSourcePour(?User $user): string
    {
        if (
            $user
            && $user->exerce(['reception'])
            && ! $user->exerce(['restaurant_manager', 'restaurant_chief', 'restaurant_staff'])
        ) {
            return RestaurantBanquet::SOURCE_SUR_PLACE;
        }

        return RestaurantBanquet::SOURCE_RESTAURANT;
    }

    private function notifierEquipeRestaurant(RestaurantBanquet $banquet, string $event): void
    {
        $rolesEquipe = ['restaurant_manager', 'restaurant_chief'];
        $auteurId = Auth::id();

        $destinataires = $this->contexte->equipe(
            User::query()->havingRole($rolesEquipe)->active(),
            $banquet->point_of_sale_id
        )
            ->when($auteurId, fn ($q) => $q->where('id', '!=', $auteurId))
            ->get();

        if ($destinataires->isEmpty()) {
            $destinataires = User::query()
                ->havingRole($rolesEquipe)
                ->active()
                ->when($auteurId, fn ($q) => $q->where('id', '!=', $auteurId))
                ->get();
        }

        if ($destinataires->isEmpty()) {
            $destinataires = User::query()
                ->havingRole(['manager'])
                ->active()
                ->when($auteurId, fn ($q) => $q->where('id', '!=', $auteurId))
                ->get();
        }

        $this->notifier->send(
            $destinataires,
            new BanquetNotification($banquet, $event, Auth::user()?->name)
        );
    }

    /** BQT-2026-001 : une série par année. */
    private function reference(): string
    {
        $annee = now()->year;
        $prefixe = "BQT-{$annee}-";
        $n = RestaurantBanquet::query()->where('reference', 'like', $prefixe.'%')->count() + 1;

        while (RestaurantBanquet::query()->where('reference', $prefixe.str_pad((string) $n, 3, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefixe.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** BQT-PAY-2026-0001 : référence unique de reçu de règlement banquet. */
    private function paymentReference(): string
    {
        $annee = now()->year;
        $prefixe = "BQT-PAY-{$annee}-";
        $n = RestaurantBanquetPayment::query()->where('reference', 'like', $prefixe.'%')->count() + 1;

        while (RestaurantBanquetPayment::query()->where('reference', $prefixe.str_pad((string) $n, 4, '0', STR_PAD_LEFT))->exists()) {
            $n++;
        }

        return $prefixe.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
