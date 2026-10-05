<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PointOfSale;
use App\Models\RestaurantBuffetService;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantStockCount;
use App\Models\Space;
use App\Models\User;
use App\Services\RestaurantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Les restaurants de l'hôtel, réglés dans Paramètres › Restaurant.
 *
 * L'hôtel en crée autant qu'il en exploite. Chacun active les services qu'il
 * a — salle, cuisine, bar, stock —, choisit ses modes de service — à la
 * carte, au buffet, ou les deux — et ses salles, où se tiennent aussi les
 * banquets ; chacun a sa carte, sa caisse et son équipe.
 *
 * La direction crée les restaurants et leurs salles ; le responsable de
 * restaurant compose l'équipe des restaurants où il est affecté.
 */
class RestaurantSetupController extends Controller
{
    public function __construct(private readonly RestaurantContext $contexte) {}

    /** L'écran est l'onglet Restaurant des paramètres ; l'ancienne adresse y mène. */
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'restaurant']);
    }

    /**
     * Ce que l'onglet Restaurant des paramètres affiche.
     *
     * @return array<string, mixed>
     */
    public static function donneesDeLOnglet(?User $user): array
    {
        $contexte = app(RestaurantContext::class);

        // La direction voit tous les restaurants, même fermés ; un responsable,
        // ceux où il est affecté.
        $restaurants = $contexte->vueGlobale($user)
            ? PointOfSale::query()->restaurants()->orderBy('sort_order')->orderBy('name')->get()
            : $contexte->accessibles($user);

        $restaurants->load(['users' => fn ($q) => $q->orderBy('name'), 'spaces' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')]);

        return [
            'restaurants' => $restaurants,
            'modes' => PointOfSale::MODES_SERVICE,
            'servicesRestaurant' => PointOfSale::SERVICES,
            // Le personnel qu'on peut affecter à une équipe.
            'personnel' => User::query()->active()->havingRole(RestaurantContext::ROLES_DU_RESTAURANT)
                ->with('roles:id,slug,name')->orderBy('name')->get(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $valide = $this->valider($request);

        $restaurant = PointOfSale::create([
            'kind' => PointOfSale::KIND_RESTAURATION,
            'code' => Str::upper($valide['code']),
            'name' => $valide['name'],
            'slug' => $this->slugLibre($valide['name']),
            'series_prefix' => $valide['series_prefix'] ?? null,
            'service_modes' => array_values($valide['service_modes']),
            'services' => $this->servicesDansLOrdre($valide['services']),
            'is_active' => true,
            'sort_order' => (int) (PointOfSale::max('sort_order') ?? 0) + 1,
        ]);

        $this->contexte->oublier();

        AuditLog::record(Auth::id(), 'restaurant_created', "Restaurant « {$restaurant->name} » créé", 'restaurant', [
            'point_of_sale_id' => $restaurant->id,
        ]);

        return $this->retour()
            ->with('success', "Restaurant « {$restaurant->name} » créé. Composez maintenant son équipe.");
    }

    public function update(Request $request, PointOfSale $restaurant): RedirectResponse
    {
        abort_unless($restaurant->kind === PointOfSale::KIND_RESTAURATION, 404);

        $valide = $this->valider($request, $restaurant);

        // Fermer le dernier restaurant ouvert laisserait la salle sans carte.
        if (! $request->boolean('is_active') && $restaurant->is_active
            && PointOfSale::query()->restaurants()->active()->count() <= 1) {
            return back()->withErrors(['is_active' => 'C\'est le seul restaurant ouvert : il ne se ferme pas.']);
        }

        $services = $this->servicesDansLOrdre($valide['services']);

        if ($refus = $this->serviceEncoreEnCours($restaurant, $services)) {
            return back()->withInput()->withErrors(['services' => $refus]);
        }

        $avant = $restaurant->services;

        $restaurant->update([
            'code' => Str::upper($valide['code']),
            'name' => $valide['name'],
            'series_prefix' => $valide['series_prefix'] ?? null,
            'service_modes' => array_values($valide['service_modes']),
            'services' => $services,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->contexte->oublier();

        if ($avant !== $services) {
            AuditLog::record(Auth::id(), 'restaurant_services', "Services du restaurant « {$restaurant->name} » : ".implode(', ', $restaurant->libellesServices()), 'restaurant', [
                'point_of_sale_id' => $restaurant->id,
                'avant' => $avant,
                'apres' => $services,
            ]);
        }

        return $this->retour()
            ->with('success', "Restaurant « {$restaurant->name} » mis à jour.");
    }

    /**
     * Compose l'équipe d'un restaurant. Le personnel peut appartenir à
     * plusieurs restaurants ; il ne voit que ceux-là.
     */
    public function updateTeam(Request $request, PointOfSale $restaurant): RedirectResponse
    {
        abort_unless($restaurant->kind === PointOfSale::KIND_RESTAURATION, 404);

        // Un responsable ne compose que l'équipe d'un restaurant où il travaille.
        $user = Auth::user();
        abort_unless($this->contexte->vueGlobale($user) || $this->contexte->peutVoir($user, $restaurant), 403);

        $valide = $request->validate([
            'users' => ['nullable', 'array'],
            'users.*' => ['integer'],
        ]);

        $equipe = User::query()->active()->havingRole(RestaurantContext::ROLES_DU_RESTAURANT)
            ->whereIn('id', $valide['users'] ?? [])->pluck('id');

        // Les comptes hors du personnel de restaurant (inactifs, autres
        // services) gardent leur affectation : l'écran ne les montre pas.
        $horsEcran = $restaurant->users()
            ->where(fn ($q) => $q->where('is_active', false)
                ->orWhereNotIn('users.id', User::query()->havingRole(RestaurantContext::ROLES_DU_RESTAURANT)->select('id')))
            ->pluck('users.id');

        $restaurant->users()->sync($equipe->merge($horsEcran)->unique()->all());
        $this->contexte->oublier();

        AuditLog::record($user->id, 'restaurant_team', "Équipe du restaurant « {$restaurant->name} » mise à jour", 'restaurant', [
            'point_of_sale_id' => $restaurant->id,
            'users' => $equipe->values()->all(),
        ]);

        return $this->retour()
            ->with('success', "Équipe du restaurant « {$restaurant->name} » enregistrée.");
    }

    /** Une salle du restaurant : on y sert, on y tient des banquets. */
    public function storeSpace(Request $request, PointOfSale $restaurant): RedirectResponse
    {
        abort_unless($restaurant->kind === PointOfSale::KIND_RESTAURATION, 404);

        // Une salle n'existe que dans un restaurant qui sert en salle.
        $restaurant->exiger(PointOfSale::SERVICE_SALLE);

        $valide = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        Space::create([
            'name' => trim($valide['name']),
            'slug' => $this->slugLibre($restaurant->slug.' '.$valide['name'], Space::class),
            'capacity' => $valide['capacity'] ?? null,
            'point_of_sale_id' => $restaurant->id,
            'is_active' => true,
            'sort_order' => (int) ($restaurant->spaces()->max('sort_order') ?? 0) + 1,
        ]);

        return $this->retour()->with('success', 'Salle ajoutée.');
    }

    public function updateSpace(Request $request, Space $space): RedirectResponse
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $space->update([
            'name' => trim($valide['name']),
            'capacity' => $valide['capacity'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return $this->retour()->with('success', 'Salle mise à jour.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?PointOfSale $restaurant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:16', 'alpha_dash', Rule::unique('points_of_sale', 'code')->ignore($restaurant?->id)],
            'series_prefix' => ['nullable', 'string', 'max:8'],
            'service_modes' => ['required', 'array', 'min:1'],
            'service_modes.*' => [Rule::in(array_keys(PointOfSale::MODES_SERVICE))],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [Rule::in(array_keys(PointOfSale::SERVICES))],
        ], [
            'service_modes.required' => 'Choisissez au moins un mode de service : à la carte, au buffet, ou les deux.',
            'services.required' => 'Activez au moins un service : salle, cuisine, bar ou stock.',
            'code.unique' => 'Ce code est déjà celui d\'un autre point de vente.',
        ]);
    }

    /**
     * Services cochés, dans l'ordre du catalogue. Une salle sans cuisine ni
     * bar prendrait des commandes que personne ne préparerait.
     *
     * @param  list<string>  $coches
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function servicesDansLOrdre(array $coches): array
    {
        $services = array_values(array_intersect(array_keys(PointOfSale::SERVICES), $coches));

        if (in_array(PointOfSale::SERVICE_SALLE, $services, true)
            && array_intersect([PointOfSale::SERVICE_CUISINE, PointOfSale::SERVICE_BAR], $services) === []) {
            throw ValidationException::withMessages([
                'services' => 'Une salle a besoin d\'une cuisine ou d\'un bar pour préparer ce qu\'elle commande.',
            ]);
        }

        return $services;
    }

    /**
     * Un service qu'on retire ne doit rien laisser en plan : des plats en
     * attente en cuisine, des boissons au bar, une table servie, un
     * inventaire ouvert.
     *
     * @param  list<string>  $services  services retenus
     */
    private function serviceEncoreEnCours(PointOfSale $restaurant, array $services): ?string
    {
        $retires = array_filter(
            array_keys(PointOfSale::SERVICES),
            fn (string $service): bool => $restaurant->offre($service) && ! in_array($service, $services, true)
        );

        $enCours = RestaurantCustomerOrder::query()->duRestaurant($restaurant)
            ->whereIn('status', [RestaurantCustomerOrder::STATUS_PENDING, RestaurantCustomerOrder::STATUS_CONFIRMED, RestaurantCustomerOrder::STATUS_PREPARING, RestaurantCustomerOrder::STATUS_READY]);

        foreach ($retires as $service) {
            $refus = match ($service) {
                PointOfSale::SERVICE_SALLE => (clone $enCours)->exists()
                    || RestaurantBuffetService::query()->duRestaurant($restaurant)->where('status', RestaurantBuffetService::OUVERT)->exists()
                    ? 'Des commandes ou un buffet sont en cours en salle : servez-les ou clôturez-les avant de retirer la salle.' : null,
                PointOfSale::SERVICE_CUISINE => (clone $enCours)->whereIn('status', [RestaurantCustomerOrder::STATUS_CONFIRMED, RestaurantCustomerOrder::STATUS_PREPARING])
                    ->whereHas('items', fn ($q) => $q->enCuisine())->exists()
                    ? 'Des plats attendent en cuisine : servez-les avant de retirer la cuisine.' : null,
                PointOfSale::SERVICE_BAR => (clone $enCours)->whereHas('items', fn ($q) => $q->auBar()->whereNull('ready_at'))->exists()
                    ? 'Des boissons attendent au bar : servez-les avant de retirer le bar.' : null,
                PointOfSale::SERVICE_STOCK => RestaurantStockCount::query()->duRestaurant($restaurant)->where('status', RestaurantStockCount::STATUS_DRAFT)->exists()
                    ? 'Un inventaire du garde-manger est ouvert : clôturez-le avant de retirer le stock.' : null,
                default => null,
            };

            if ($refus !== null) {
                return $refus;
            }
        }

        return null;
    }

    /** Les réglages des restaurants se font dans l'onglet Restaurant des paramètres. */
    private function retour(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'restaurant']);
    }

    /** @param class-string<Model> $modele */
    private function slugLibre(string $nom, string $modele = PointOfSale::class): string
    {
        $base = Str::limit(Str::slug($nom), 56, '') ?: 'restaurant';
        $slug = $base;
        $n = 2;

        while ($modele::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
