<?php

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\Navigation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 * Le contrôleur de gestion voit tous les services et n'écrit nulle part. Ce
 * qu'il a le droit de consulter doit s'ouvrir : une tuile ou un écran qui
 * répond par une erreur lui retire sa vue d'ensemble sans que personne ne
 * s'en aperçoive.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'restaurant', 'shop', 'housekeeping', 'economat',
        'accounting', 'comptabilite', 'ledger', 'analytics', 'technique', 'discussions']);
});

function controleur(): User
{
    $user = User::factory()->create(['name' => 'Paul Essomba', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'controller')->pluck('id'));

    return $user->fresh();
}

test("chacune de ses tuiles s'ouvre", function () {
    $user = controleur();
    $tuiles = collect(Navigation::getLauncherGroups($user))->mapWithKeys(fn ($g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)])->all();

    expect($tuiles)->toHaveKeys(['hotel', 'restaurant', 'economat', 'accounting', 'gestion', 'shop', 'editions', 'planning', 'analytics']);

    $this->actingAs($user);
    foreach ($tuiles as $id => $chemin) {
        $statut = $this->get($chemin)->status();
        expect($statut === 200 || ($id === 'settings' && $statut === 302))->toBeTrue("la tuile {$id} ({$chemin}) répond {$statut}");
    }
});

test("chaque écran qu'il peut consulter s'ouvre sans erreur", function () {
    $user = controleur();
    $droits = app(PermissionResolver::class);
    $this->actingAs($user);

    $enErreur = [];
    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();
        // Écrans sans paramètre, servis en GET et gardés par un droit qu'il détient.
        if (! $nom || ! in_array('GET', $route->methods(), true) || $route->parameterNames() !== []
            || ! in_array('permission', $route->gatherMiddleware(), true)
            || str_contains($nom, 'export') || str_contains($nom, 'print') || str_contains($nom, 'document') || $nom === 'test-popup') {
            continue;
        }
        $droit = PermissionCatalog::permissionForRoute($nom);
        if (! $droits->allows($user, $droit)) {
            continue;
        }

        // Une redirection doit mener à un écran qui s'ouvre (Restaurants → Paramètres).
        $statut = $this->followingRedirects()->get(route($nom))->status();
        if ($statut !== 200) {
            $enErreur[] = "{$nom} → {$statut}";
        }
    }

    expect($enErreur)->toBe([]);
});

test("ses écritures sont strictement limitées au pilotage, aux demandes d'achat et au référentiel / ajustement (Lot 1)", function () {
    $ecritures = collect(PermissionCatalog::all())
        ->filter(fn ($roles, $droit) => in_array('controller', $roles, true) && ! PermissionCatalog::estLecture($droit))
        ->keys()->sort()->values()->all();

    // Lot 1 (Constat S7) : Le contrôleur de gestion est seul habilité à modifier/supprimer
    // les articles et fournisseurs, et à ajuster le stock. Il tient également le budget
    // et approuve les demandes d'achat. Il n'achète ni ne réceptionne.
    expect($ecritures)->toBe([
        'economat.items.adjust', 'economat.items.modifier', 'economat.items.opening', 'economat.items.supprimer',
        'economat.purchase_requests.approve', 'economat.purchase_requests.modifier', 'economat.purchase_requests.reject',
        'economat.suppliers.modifier', 'economat.suppliers.supprimer',
        'pilotage.budget.modifier', 'pilotage.seuils.modifier',
    ]);
});

test('ses menus mènent à ce qu\'il consulte, sans bouton pour écrire', function () {
    $this->actingAs(controleur());

    $this->get(route('bookings.index'))->assertOk()
        ->assertSee(route('agenda.index'), false)->assertSee(route('housekeeping.index'), false)->assertSee(route('rooms.rates.index'), false);
    $this->get(route('restaurant.billing.index'))->assertOk()
        ->assertSee(route('restaurant.consumption.index'), false)->assertSee(route('restaurant.recipes.index'), false)->assertSee(route('restaurant.cash_register.index'), false);
    $this->get(route('shop.orders.index'))->assertOk()->assertSee(route('shop.cash_register.index'), false);
    $this->get(route('accounting.index'))->assertOk()->assertSee(route('rooms.cost_sheets.index'), false);

    $this->get(route('rooms.rates.index'))->assertOk()->assertDontSee('Ajouter un tarif');
    $this->post(route('rooms.rates.store'), [])->assertSessionHas('access_denied_popup', true);
});
