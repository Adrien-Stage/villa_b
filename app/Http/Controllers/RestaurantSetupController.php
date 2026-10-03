<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PointOfSale;
use App\Models\Space;
use App\Models\User;
use App\Services\RestaurantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Les restaurants de l'hôtel.
 *
 * L'hôtel en crée autant qu'il en exploite. Chacun a sa carte, sa cuisine et
 * son bar, son garde-manger, sa caisse et son équipe ; chacun choisit ses
 * modes de service — à la carte, au buffet, ou les deux — et ses salles, où
 * se tiennent aussi les banquets.
 *
 * La direction crée les restaurants et leurs salles ; le responsable de
 * restaurant compose l'équipe des restaurants où il est affecté.
 */
class RestaurantSetupController extends Controller
{
    public function __construct(private readonly RestaurantContext $contexte) {}

    public function index(): View
    {
        $user = Auth::user();

        // La direction voit tous les restaurants, même fermés ; un responsable,
        // ceux où il est affecté.
        $restaurants = $this->contexte->vueGlobale($user)
            ? PointOfSale::query()->restaurants()->orderBy('sort_order')->orderBy('name')->get()
            : $this->contexte->accessibles($user);

        $restaurants->load(['users' => fn ($q) => $q->orderBy('name'), 'spaces' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')]);

        return view('restaurant.restaurants.index', [
            'restaurants' => $restaurants,
            'modes' => PointOfSale::MODES_SERVICE,
            // Le personnel qu'on peut affecter à une équipe.
            'personnel' => User::query()->active()->havingRole(RestaurantContext::ROLES_DU_RESTAURANT)
                ->with('roles:id,slug,name')->orderBy('name')->get(),
        ]);
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
            'is_active' => true,
            'sort_order' => (int) (PointOfSale::max('sort_order') ?? 0) + 1,
        ]);

        $this->contexte->oublier();

        AuditLog::record(Auth::id(), 'restaurant_created', "Restaurant « {$restaurant->name} » créé", 'restaurant', [
            'point_of_sale_id' => $restaurant->id,
        ]);

        return redirect()->route('restaurant.restaurants.index')
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

        $restaurant->update([
            'code' => Str::upper($valide['code']),
            'name' => $valide['name'],
            'series_prefix' => $valide['series_prefix'] ?? null,
            'service_modes' => array_values($valide['service_modes']),
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->contexte->oublier();

        return redirect()->route('restaurant.restaurants.index')
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

        return redirect()->route('restaurant.restaurants.index')
            ->with('success', "Équipe du restaurant « {$restaurant->name} » enregistrée.");
    }

    /** Une salle du restaurant : on y sert, on y tient des banquets. */
    public function storeSpace(Request $request, PointOfSale $restaurant): RedirectResponse
    {
        abort_unless($restaurant->kind === PointOfSale::KIND_RESTAURATION, 404);

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

        return redirect()->route('restaurant.restaurants.index')->with('success', 'Salle ajoutée.');
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

        return redirect()->route('restaurant.restaurants.index')->with('success', 'Salle mise à jour.');
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
        ], [
            'service_modes.required' => 'Choisissez au moins un mode de service : à la carte, au buffet, ou les deux.',
            'code.unique' => 'Ce code est déjà celui d\'un autre point de vente.',
        ]);
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
