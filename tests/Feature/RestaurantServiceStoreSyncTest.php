<?php

use App\Models\PointOfSale;
use App\Models\Role;
use App\Models\ServiceStore;
use App\Models\ServiceStoreStock;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\CountSheetService;
use App\Services\StockRequisitionService;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Carbon\Carbon;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    activerModules(['restaurant', 'economat', 'comptabilite', 'accounting']);
    $this->seed(TenantSeeder::class);
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();

    // Administrateur pour la configuration
    $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Test', 'is_active' => true]);
    $this->admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    // Économe
    $this->econome = User::factory()->create(['role' => 'econome', 'name' => 'Econome Test', 'is_active' => true]);
    $this->econome->roles()->sync(Role::where('slug', 'econome')->pluck('id'));

    // Chef restaurant
    $this->chef = User::factory()->create(['role' => 'restaurant_chief', 'name' => 'Chef Test', 'is_active' => true]);
    $this->chef->roles()->sync(Role::where('slug', 'restaurant_chief')->pluck('id'));
});

afterEach(fn () => Carbon::setTestNow());

test('la création d’un restaurant avec cuisine et bar crée automatiquement ses dépôts de service pour l’économat', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'KOT',
        'name'          => 'Kotibe',
        'series_prefix' => 'KOT-',
        'service_modes' => ['carte', 'buffet'],
        'services'      => ['salle', 'cuisine', 'bar', 'stock'],
    ]);

    $response->assertSessionHasNoErrors();
    $restaurant = PointOfSale::where('code', 'KOT')->firstOrFail();

    $depots = ServiceStore::where('point_of_sale_id', $restaurant->id)->get();
    expect($depots)->toHaveCount(2);

    $depotCuisine = $depots->firstWhere('restaurant_service', 'cuisine');
    expect($depotCuisine)->not->toBeNull()
        ->and($depotCuisine->name)->toBe('Cuisine - Kotibe')
        ->and($depotCuisine->department)->toBe('restaurant')
        ->and($depotCuisine->is_active)->toBeTrue();

    $depotBar = $depots->firstWhere('restaurant_service', 'bar');
    expect($depotBar)->not->toBeNull()
        ->and($depotBar->name)->toBe('Bar - Kotibe')
        ->and($depotBar->department)->toBe('restaurant')
        ->and($depotBar->is_active)->toBeTrue();
});

test('un restaurant sans bar ne crée que le dépôt cuisine', function () {
    $this->actingAs($this->admin);

    $response = $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'SNK',
        'name'          => 'Snack Gourmand',
        'series_prefix' => 'SNK-',
        'service_modes' => ['carte'],
        'services'      => ['salle', 'cuisine'],
    ]);

    $response->assertSessionHasNoErrors();
    $restaurant = PointOfSale::where('code', 'SNK')->firstOrFail();

    $depots = ServiceStore::where('point_of_sale_id', $restaurant->id)->get();
    expect($depots)->toHaveCount(1)
        ->and($depots->first()->name)->toBe('Cuisine - Snack Gourmand')
        ->and($depots->first()->restaurant_service)->toBe('cuisine');
});

test('la mise à jour du nom du restaurant renomme synchroniquement ses dépôts', function () {
    $this->actingAs($this->admin);

    $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'LOU',
        'name'          => 'Lounge Bar',
        'series_prefix' => 'LOU-',
        'service_modes' => ['carte'],
        'services'      => ['salle', 'cuisine', 'bar'],
    ])->assertSessionHasNoErrors();

    $restaurant = PointOfSale::where('code', 'LOU')->firstOrFail();

    // Renommage
    $this->put(route('restaurant.restaurants.update', $restaurant), [
        'code'          => 'LOU',
        'name'          => 'Lounge & Spa',
        'series_prefix' => 'LOU-',
        'service_modes' => ['carte'],
        'services'      => ['salle', 'cuisine', 'bar'],
        'is_active'     => '1',
    ])->assertSessionHasNoErrors();

    $depots = ServiceStore::where('point_of_sale_id', $restaurant->id)->get();
    expect($depots->firstWhere('restaurant_service', 'cuisine')->name)->toBe('Cuisine - Lounge & Spa')
        ->and($depots->firstWhere('restaurant_service', 'bar')->name)->toBe('Bar - Lounge & Spa');
});

test('la désactivation d’un restaurant désactive ses dépôts de service', function () {
    $this->actingAs($this->admin);

    // Créer d'abord un 2e restaurant pour pouvoir désactiver le premier sans bloquer la règle du dernier restaurant ouvert
    $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'R2',
        'name'          => 'Restaurant 2',
        'services'      => ['cuisine'],
        'service_modes' => ['carte'],
    ]);

    $resto = PointOfSale::where('code', 'R2')->firstOrFail();

    $this->put(route('restaurant.restaurants.update', $resto), [
        'code'          => 'R2',
        'name'          => 'Restaurant 2',
        'services'      => ['cuisine'],
        'service_modes' => ['carte'],
        'is_active'     => '0',
    ])->assertSessionHasNoErrors();

    $depot = ServiceStore::where('point_of_sale_id', $resto->id)->firstOrFail();
    expect($depot->is_active)->toBeFalse();
});

test('l’économat peut approvisionner un dépôt de restaurant via un bon de réquisition', function () {
    $this->actingAs($this->admin);

    $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'KOT',
        'name'          => 'Kotibe',
        'series_prefix' => 'KOT-',
        'service_modes' => ['carte'],
        'services'      => ['salle', 'cuisine', 'bar', 'stock'],
    ])->assertSessionHasNoErrors();

    $restaurant = PointOfSale::where('code', 'KOT')->firstOrFail();
    $depotBar = ServiceStore::where('point_of_sale_id', $restaurant->id)
        ->where('restaurant_service', 'bar')
        ->firstOrFail();

    // Créer un article en magasin central
    $cat = StockCategory::create(['name' => 'Boissons', 'stock_account' => '311000']);
    $biere = StockItem::create([
        'stock_category_id' => $cat->id,
        'name'              => 'Bière Blonde 33cl',
        'unit'              => 'bouteille',
        'current_stock'     => 100,
        'average_cost'      => 80000,
    ]);

    // Réquisition demandée pour le bar du restaurant
    $demande = StockRequisition::create([
        'department'       => 'restaurant',
        'service_store_id' => $depotBar->id,
        'point_of_sale_id' => $restaurant->id,
        'status'           => StockRequisition::STATUS_APPROVED,
        'requested_by'     => $this->chef->id,
    ]);
    $demande->lines()->create([
        'stock_item_id'      => $biere->id,
        'quantity_requested' => 20,
    ]);

    // Livraison par l'économe
    app(StockRequisitionService::class)->deliver($demande);

    // Vérifier le stock entré dans le dépôt de service du restaurant
    $stockDepot = ServiceStoreStock::where('service_store_id', $depotBar->id)
        ->where('stock_item_id', $biere->id)
        ->firstOrFail();

    expect((float) $stockDepot->current_stock)->toBe(20.0)
        ->and($stockDepot->average_cost)->toBe(80000)
        ->and((float) $biere->fresh()->current_stock)->toBe(80.0);
});

test('les dépôts des restaurants sont pris en compte dans les fiches de comptage de l’économat', function () {
    $this->actingAs($this->admin);

    $this->post(route('restaurant.restaurants.store'), [
        'code'          => 'PAL',
        'name'          => 'Palais Gourmet',
        'services'      => ['cuisine', 'bar'],
        'service_modes' => ['carte'],
    ])->assertSessionHasNoErrors();

    $restaurant = PointOfSale::where('code', 'PAL')->firstOrFail();
    $depots = ServiceStore::where('point_of_sale_id', $restaurant->id)->get();

    $countSheetService = app(CountSheetService::class);
    $servicesFiches = $countSheetService->services();

    foreach ($depots as $depot) {
        $cle = CountSheetService::STORE_PREFIX . $depot->id;
        expect(array_key_exists($cle, $servicesFiches))->toBeTrue();
        expect($servicesFiches[$cle])->toContain($depot->name);
    }
});
