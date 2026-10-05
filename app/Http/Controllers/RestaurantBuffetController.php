<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\FolioItem;
use App\Models\PointOfSale;
use App\Models\RestaurantBuffetEntry;
use App\Models\RestaurantBuffetService;
use App\Models\RestaurantMenuItem;
use App\Services\CashRegisterCircuit;
use App\Services\CheckOutService;
use App\Services\RestaurantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Le buffet au forfait.
 *
 * Un restaurant qui sert au buffet ouvre un service pour un repas d'une
 * journée, à un prix d'entrée par adulte et par enfant. La caisse enregistre
 * les entrées au fil du service — combien d'adultes, combien d'enfants, payé
 * comment —, sans note par table. Un résident peut reporter son entrée sur
 * son séjour.
 *
 * L'autre forme du buffet, la formule au couvert, est un article de la carte
 * (type « buffet ») commandé comme le reste.
 */
class RestaurantBuffetController extends Controller
{
    private const PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'room_charge', 'other'];

    public function __construct(
        private readonly RestaurantContext $contexte,
        private readonly CashRegisterCircuit $circuit,
    ) {}

    public function index(): View
    {
        $user = Auth::user();

        $services = RestaurantBuffetService::query()
            ->visiblesPour($user)
            ->with(['pointOfSale:id,name', 'openedBy:id,name'])
            ->withSum('entries as total_encaisse', 'amount')
            ->withSum('entries as total_adultes', 'adults')
            ->withSum('entries as total_enfants', 'children')
            ->orderByDesc('service_date')
            ->orderByDesc('id')
            ->paginate(20);

        $restaurant = $this->contexte->pourSaisie($user);

        return view('restaurant.buffets.index', [
            'services' => $services,
            // On ouvre le buffet d'un restaurant qui sert au buffet.
            'restaurant' => $restaurant,
            // Le buffet se sert en salle, dans un restaurant qui sert au buffet.
            'peutOuvrirIci' => $restaurant !== null && $restaurant->sert(PointOfSale::MODE_BUFFET) && $restaurant->offre(PointOfSale::SERVICE_SALLE),
            'vueEnsemble' => $this->contexte->vueEnsemble($user),
            'repas' => RestaurantMenuItem::MEAL_SERVICES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurant = $this->contexte->exigerPourSaisie(Auth::user());

        if (! $restaurant->sert(PointOfSale::MODE_BUFFET)) {
            return back()->withErrors(['restaurant' => "{$restaurant->name} ne sert pas au buffet : ce mode s'active dans Paramètres › Restaurant."]);
        }

        $valide = $request->validate([
            'service_date' => ['required', 'date'],
            'meal_service' => ['required', Rule::in(array_keys(RestaurantMenuItem::MEAL_SERVICES))],
            // Saisis en FCFA, stockés en centimes.
            'adult_price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'child_price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Un repas d'une journée n'a qu'un buffet : deux services ouverts en
        // même temps partageraient les mêmes clients sur deux comptes.
        $doublon = RestaurantBuffetService::query()->duRestaurant($restaurant)
            ->whereDate('service_date', $valide['service_date'])
            ->where('meal_service', $valide['meal_service'])
            ->exists();

        if ($doublon) {
            return back()->withInput()->withErrors(['service_date' => 'Ce buffet est déjà ouvert pour ce repas et ce jour.']);
        }

        $service = RestaurantBuffetService::create([
            'point_of_sale_id' => $restaurant->id,
            'service_date' => $valide['service_date'],
            'meal_service' => $valide['meal_service'],
            'adult_price' => (int) $valide['adult_price'] * 100,
            'child_price' => (int) ($valide['child_price'] ?? 0) * 100,
            'status' => RestaurantBuffetService::OUVERT,
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'notes' => $valide['notes'] ?? null,
        ]);

        return redirect()->route('restaurant.buffets.show', $service)
            ->with('success', "Buffet ouvert : {$service->libelleRepas()} du {$service->service_date->format('d/m/Y')}.");
    }

    public function show(RestaurantBuffetService $buffet): View
    {
        $buffet->load(['pointOfSale', 'openedBy:id,name', 'entries' => fn ($q) => $q->with(['recordedBy:id,name', 'booking.room', 'booking.customer'])->latest('id')]);

        $parMode = $buffet->entries->groupBy('payment_method')
            ->map(fn ($entrees) => ['nombre' => $entrees->count(), 'montant' => (int) $entrees->sum('amount')]);

        return view('restaurant.buffets.show', [
            'buffet' => $buffet,
            'parMode' => $parMode,
            'paymentMethods' => self::PAYMENT_METHODS,
            'caisse' => $this->circuit->ouverte(Auth::user(), 'restaurant'),
            'residents' => Booking::query()->where('status', BookingStatus::CHECKED_IN)
                ->with(['room', 'customer'])->orderByDesc('id')->take(80)->get(),
        ]);
    }

    /** Une entrée au buffet : des adultes, des enfants, un encaissement. */
    public function storeEntry(Request $request, RestaurantBuffetService $buffet): RedirectResponse
    {
        if (! $buffet->estOuvert()) {
            return back()->withErrors(['buffet' => 'Ce buffet est clos : il n\'enregistre plus d\'entrées.']);
        }

        $valide = $request->validate([
            'adults' => ['required', 'integer', 'min:0', 'max:500'],
            'children' => ['nullable', 'integer', 'min:0', 'max:500'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'booking_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $adultes = (int) $valide['adults'];
        $enfants = (int) ($valide['children'] ?? 0);

        if ($adultes + $enfants === 0) {
            return back()->withInput()->withErrors(['adults' => 'Indiquez au moins une personne.']);
        }

        $montant = $adultes * (int) $buffet->adult_price + $enfants * (int) $buffet->child_price;
        $mode = $valide['payment_method'];

        // Tout encaissement passe par la caisse de celui qui encaisse, celle
        // du restaurant du buffet. Le report sur le séjour n'est pas un
        // encaissement : le folio le portera jusqu'au départ.
        $session = null;
        $sejour = null;

        if ($mode === 'room_charge') {
            $sejour = Booking::query()->whereKey((int) ($valide['booking_id'] ?? 0))
                ->where('status', BookingStatus::CHECKED_IN)->first();

            if (! $sejour) {
                return back()->withInput()->withErrors(['booking_id' => 'Choisissez un résident dont le séjour est en cours.']);
            }
        } else {
            $session = $this->circuit->ouverte(Auth::user(), 'restaurant');

            if (! $session) {
                return back()->withInput()->withErrors(['cash_register' => 'Ouvrez votre caisse avant d\'encaisser.']);
            }

            if ($session->point_of_sale_id && $session->point_of_sale_id !== $buffet->point_of_sale_id) {
                return back()->withInput()->withErrors(['cash_register' => 'Ce buffet est celui d\'un autre restaurant : il s\'encaisse à la caisse de ce restaurant.']);
            }
        }

        DB::transaction(function () use ($buffet, $adultes, $enfants, $montant, $mode, $session, $sejour, $valide) {
            $folio = null;

            if ($sejour) {
                $folio = FolioItem::create([
                    'booking_id' => $sejour->id,
                    'customer_id' => $sejour->customer_id,
                    'type' => FolioItem::TYPE_RESTAURANT,
                    'description' => sprintf('Buffet %s — %s (%d adulte(s)%s)',
                        mb_strtolower($buffet->libelleRepas()),
                        $buffet->pointOfSale?->name ?? 'Restaurant',
                        $adultes,
                        $enfants > 0 ? ", {$enfants} enfant(s)" : ''),
                    'quantity' => 1,
                    'unit_price' => $montant,
                    'total_price' => $montant,
                    'is_complimentary' => false,
                    'earns_points' => true,
                    'recorded_by' => Auth::id(),
                    'occurred_at' => now(),
                ]);
            }

            RestaurantBuffetEntry::create([
                'restaurant_buffet_service_id' => $buffet->id,
                'point_of_sale_id' => $buffet->point_of_sale_id,
                'adults' => $adultes,
                'children' => $enfants,
                'amount' => $montant,
                'payment_method' => $mode,
                'booking_id' => $sejour?->id,
                'folio_item_id' => $folio?->id,
                'cash_register_session_id' => $session?->id,
                'recorded_by' => Auth::id(),
                'notes' => $valide['notes'] ?? null,
            ]);
        });

        if ($sejour) {
            app(CheckOutService::class)->recalculateTotals($sejour->fresh());
        }

        return redirect()->route('restaurant.buffets.show', $buffet)->with('success', sprintf(
            'Entrée enregistrée : %s FCFA%s.',
            number_format($montant / 100, 0, ',', ' '),
            $sejour ? ' reportés sur le séjour' : ''
        ));
    }

    public function close(RestaurantBuffetService $buffet): RedirectResponse
    {
        if (! $buffet->estOuvert()) {
            return back();
        }

        $buffet->update([
            'status' => RestaurantBuffetService::CLOS,
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        $buffet->loadSum('entries as total', 'amount')->loadSum('entries as adultes', 'adults')->loadSum('entries as enfants', 'children');

        AuditLog::record(Auth::id(), 'buffet_closed', sprintf(
            'Buffet %s du %s clos (%s) : %d adulte(s), %d enfant(s), %s FCFA',
            mb_strtolower($buffet->libelleRepas()),
            $buffet->service_date->format('d/m/Y'),
            $buffet->pointOfSale?->name ?? 'restaurant',
            (int) $buffet->adultes,
            (int) $buffet->enfants,
            number_format(((int) $buffet->total) / 100, 0, ',', ' ')
        ), 'restaurant', ['buffet_id' => $buffet->id]);

        return redirect()->route('restaurant.buffets.show', $buffet)->with('success', 'Buffet clos.');
    }
}
