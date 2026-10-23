<?php

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\DutySegregation;
use App\Support\Navigation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use App\Support\SettingsTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Le responsable financier dirige la comptabilité et les finances : il fixe
 * les tarifs et les prix de tous les services, en plus de ceux qui les
 * fixaient déjà, et consulte les chiffres de tous les services. Il encadre
 * les comptables (dont il détient les droits), le contrôle de gestion et
 * l'économat — sans faire le travail du magasin ni prendre le rôle du
 * contrôleur.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'restaurant', 'shop', 'housekeeping', 'accounting', 'comptabilite', 'analytics']);
});

function financier(): User
{
    $user = User::factory()->create(['name' => 'Ruth Ngo', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'finance_manager')->pluck('id'));

    return $user->fresh();
}

test("le rôle s'appelle Responsable financier et ne porte à lui seul aucun cumul interdit", function () {
    expect(Role::where('slug', 'finance_manager')->value('name'))->toBe('Responsable financier')
        ->and(RoleCatalog::developper(['finance_manager']))->toContain('accountant')
        ->not->toContain('econome')->not->toContain('storekeeper')->not->toContain('controller')
        ->and(DutySegregation::conflictsFor(['finance_manager']))->toBe([]);
});

test('il consulte tout ce que consulte le contrôle de gestion', function () {
    $manquants = [];
    foreach (PermissionCatalog::all() as $droit => $roles) {
        if (in_array('controller', $roles, true) && PermissionCatalog::estLecture($droit) && ! in_array('finance_manager', $roles, true)) {
            $manquants[] = $droit;
        }
    }

    expect($manquants)->toBe([]);
});

test("il fixe les prix de tous les services et valide les achats, sans tenir le magasin", function () {
    $droits = app(PermissionResolver::class);
    $user = financier();

    foreach ([
        'rooms.rates.creer', 'rooms.rates.modifier', 'rooms.rates.types.modifier',
        'settings.packages.modifier', 'settings.partners.modifier', 'settings.services.modifier', 'settings.modifier',
        'restaurant.menus.items.modifier', 'shop.products.modifier', 'housekeeping.minibar.articles.modifier',
        'economat.purchase_requests.approve', 'economat.purchase_requests.reject', 'economat.requisitions.endorse',
        'accounting.voir', 'planning.affectations.creer',
    ] as $droit) {
        expect($droits->allows($user, $droit))->toBeTrue("{$droit} devrait être permis");
    }

    foreach ([
        'economat.orders.creer', 'economat.receipts.creer', 'economat.items.adjust', 'economat.requisitions.deliver',
        'restaurant.menus.items.creer', 'shop.products.creer', 'rooms.types.supprimer', 'users.creer',
    ] as $droit) {
        expect($droits->allows($user, $droit))->toBeFalse("{$droit} ne devrait pas être permis");
    }
});

test('ses tuiles mènent à ses écrans, et chaque tuile s\'ouvre', function () {
    $user = financier();
    $tuiles = collect(Navigation::getLauncherGroups($user))->mapWithKeys(fn ($g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)])->all();

    expect($tuiles)->toHaveKeys(['hotel', 'restaurant', 'economat', 'accounting', 'gestion', 'shop', 'settings', 'planning'])
        ->and($tuiles['hotel'])->toBe('/hebergement/tarifs')
        ->and($tuiles['restaurant'])->toBe(parse_url(route('restaurant.billing.index'), PHP_URL_PATH));

    $this->actingAs($user);
    foreach ($tuiles as $id => $chemin) {
        $statut = $this->get($chemin)->status();
        expect($statut)->toBe(200, "la tuile {$id} ({$chemin}) répond {$statut}");
    }

    $this->get(route('rooms.rates.index'))->assertOk()->assertSee('Tarifs');
});

test('Paramètres lui ouvre les onglets des prix', function () {
    $user = financier();

    expect(SettingsTabs::parDefaut($user))->toBe('hebergement');
    foreach (['hebergement', 'taxes', 'services', 'partners'] as $onglet) {
        expect(SettingsTabs::peutRegler($user, $onglet))->toBeTrue($onglet);
    }
    foreach (['general', 'housekeeping', 'restaurant', 'shop', 'inventaire', 'quarts', 'economat'] as $onglet) {
        expect(SettingsTabs::peutRegler($user, $onglet))->toBeFalse($onglet);
    }

    $this->actingAs($user)->get(route('settings.index'))->assertOk()
        ->assertSee('Tarification & Réductions', false)
        ->assertSee('Taxes & Tarifs', false);

    $this->post(route('settings.update', ['tab' => 'reception']), ['settings' => ['capacity_surcharge_percentage' => 15]])->assertRedirect();
    expect(\App\Models\Tenant::first()->settings['reception']['capacity_surcharge_percentage'])->toBe(15);
});

test('il voit tous les restaurants et reçoit les alertes de stock', function () {
    $user = financier();

    expect(app(\App\Services\RestaurantContext::class)->vueGlobale($user))->toBeTrue();

    \Illuminate\Support\Facades\Notification::fake();
    $article = \App\Models\StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 0, 'min_stock' => 5, 'is_active' => true]);
    app(\App\Services\StockService::class)->recordOpening($article, 10, 100000);
    app(\App\Services\StockService::class)->recordOut($article->fresh(), 6);

    \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\StockItemBelowThreshold::class);
});
