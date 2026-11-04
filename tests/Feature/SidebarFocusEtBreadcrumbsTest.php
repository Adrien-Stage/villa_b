<?php

use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Support\Breadcrumbs;
use App\Support\Navigation;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules([
        'economat', 'hebergement', 'reservations', 'comptabilite', 'technique',
    ]);
});

function creerAdminTest(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    return $user->fresh();
}

test('Navigation::isRouteActive active correctement les routes exactes et les sous-pages', function () {
    // 1. Route exacte
    $request = Request::create('/economat/articles', 'GET');
    $route = new Route(['GET'], '/economat/articles', ['as' => 'economat.items.index']);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    expect(Navigation::isRouteActive('economat.items.index'))->toBeTrue()
        ->and(Navigation::isRouteActive('economat.suppliers.index'))->toBeFalse();

    // 2. Sous-page d'une ressource (show active le lien de l'index parent)
    $requestShow = Request::create('/economat/articles/21', 'GET');
    $routeShow = new Route(['GET'], '/economat/articles/{item}', ['as' => 'economat.items.show']);
    $routeShow->bind($requestShow);
    $requestShow->setRouteResolver(fn () => $routeShow);
    app()->instance('request', $requestShow);

    expect(Navigation::isRouteActive('economat.items.index'))->toBeTrue()
        ->and(Navigation::isRouteActive('economat.suppliers.index'))->toBeFalse();

    // 3. Distinction des sous-menus dédiés (ex: grille tarifaire vs liste des chambres)
    $requestRates = Request::create('/rooms/rates', 'GET');
    $routeRates = new Route(['GET'], '/rooms/rates', ['as' => 'rooms.rates.index']);
    $routeRates->bind($requestRates);
    $requestRates->setRouteResolver(fn () => $routeRates);
    app()->instance('request', $requestRates);

    expect(Navigation::isRouteActive('rooms.rates.index'))->toBeTrue()
        ->and(Navigation::isRouteActive('rooms.index'))->toBeFalse();
});

test('Breadcrumbs::shouldShow masque le fil d\'Ariane sur le lanceur d\'applications et l\'affiche sur les pages métier', function () {
    // Sur le lanceur d'applications
    $reqDashboard = Request::create('/dashboard', 'GET');
    $routeDash = new Route(['GET'], '/dashboard', ['as' => 'dashboard']);
    $routeDash->bind($reqDashboard);
    $reqDashboard->setRouteResolver(fn () => $routeDash);
    app()->instance('request', $reqDashboard);

    expect(Breadcrumbs::shouldShow())->toBeFalse();

    // Sur une page d'application
    $reqArticles = Request::create('/economat/articles', 'GET');
    $routeArticles = new Route(['GET'], '/economat/articles', ['as' => 'economat.items.index']);
    $routeArticles->bind($reqArticles);
    $reqArticles->setRouteResolver(fn () => $routeArticles);
    app()->instance('request', $reqArticles);

    expect(Breadcrumbs::shouldShow())->toBeTrue();
});

test('Breadcrumbs::generate résout la hiérarchie et le nom du modèle pour le dernier élément', function () {
    $item = StockItem::factory()->create([
        'name' => 'Savon liquide 5L',
    ]);

    $request = Request::create("/economat/articles/{$item->id}", 'GET');
    $route = new Route(['GET'], '/economat/articles/{item}', ['as' => 'economat.items.show']);
    $route->bind($request);
    $route->setParameter('item', $item);
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);

    $crumbs = Breadcrumbs::generate();

    expect($crumbs)->toHaveCount(4)
        ->and($crumbs[0]['label'])->toBe('Accueil')
        ->and($crumbs[1]['label'])->toBe('Économat')
        ->and($crumbs[2]['label'])->toBe('Articles')
        ->and($crumbs[3]['label'])->toBe('Savon liquide 5L')
        ->and($crumbs[3]['url'])->toBeNull();
});

test('la page des articles affiche le fil d\'Ariane et le focus actif dans la barre latérale', function () {
    $admin = creerAdminTest();

    $response = $this->actingAs($admin)->get(route('economat.items.index'));

    $response->assertOk();

    // Widget breadcrumbs présent
    $response->assertSee('breadcrumbs-widget', false);
    $response->assertSee('Accueil');
    $response->assertSee('Économat');
    $response->assertSee('Articles');

    // Classe active de focus et attribut aria-current présents dans la barre latérale
    $response->assertSee('sidebar-lien-active', false);
    $response->assertSee('aria-current="page"', false);

    // Script de défilement automatique vers l'élément actif présent
    $response->assertSee('scrollIntoView({ block: \'nearest\', behavior: \'smooth\' })', false);
});

test('le tableau de bord lanceur d\'applications ne montre pas le fil d\'Ariane', function () {
    $admin = creerAdminTest();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('breadcrumbs-widget', false);
});
