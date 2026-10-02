<?php

use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\ServiceStore;
use App\Models\ServiceStoreStock;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Services\CountSheetService;
use App\Services\ServiceStoreCountService;
use App\Services\StockCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Fiches de comptage : une par service, imprimables à l'aveugle ou avec le
 * stock théorique, qui reprennent le théorique figé d'un inventaire ouvert.
 */

beforeEach(function () {
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

/** Un établissement qui détient du stock partout. */
function stocksDeTousLesServices(): ServiceStore
{
    $epicerie = StockCategory::create(['name' => 'Épicerie']);
    StockItem::create(['stock_category_id' => $epicerie->id, 'name' => 'Riz parfumé', 'reference' => 'RIZ-25', 'unit' => 'kg', 'current_stock' => 40]);

    $legumes = RestaurantPantryCategory::create(['name' => 'Légumes']);
    RestaurantPantryItem::create(['restaurant_pantry_category_id' => $legumes->id, 'name' => 'Tomate fraîche', 'unit' => 'kg', 'current_stock' => 6, 'is_active' => true]);

    ShopProduct::create([
        'shop_category_id' => ShopCategory::create(['name' => 'Souvenirs'])->id,
        'name' => 'Masque bamiléké', 'sku' => 'MSQ-01', 'price' => 1_500_000, 'stock_quantity' => 3, 'is_active' => true,
    ]);

    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);
    $eau = StockItem::create(['name' => 'Eau minérale', 'unit' => 'bouteille']);
    ServiceStoreStock::create(['service_store_id' => $minibar->id, 'stock_item_id' => $eau->id, 'current_stock' => 18, 'average_cost' => 25_000]);

    return $minibar;
}

test('chaque service a sa fiche, dépôts compris', function () {
    $minibar = stocksDeTousLesServices();

    $services = app(CountSheetService::class)->services();

    expect(array_keys($services))->toBe(['economat', 'garde-manger', 'boutique', 'depot-' . $minibar->id]);
});

test('la fiche d’un dépôt liste son stock', function () {
    $minibar = stocksDeTousLesServices();

    $fiche = app(CountSheetService::class)->sheet('depot-' . $minibar->id);

    expect($fiche['title'])->toBe('Mini-bar')
        ->and($fiche['groups']['Sans catégorie'][0])->toMatchArray(['name' => 'Eau minérale', 'theoretical' => 18.0]);
});

test('une fiche reprend le théorique figé de l’inventaire ouvert', function () {
    $minibar = stocksDeTousLesServices();
    $inventaire = app(ServiceStoreCountService::class)->open($minibar);

    // Le stock bouge après l'ouverture : la fiche garde le théorique figé.
    ServiceStoreStock::query()->update(['current_stock' => 50]);

    $fiche = app(CountSheetService::class)->sheet('depot-' . $minibar->id);

    expect($fiche['reference'])->toBe($inventaire->reference)
        ->and($fiche['groups']['Sans catégorie'][0]['theoretical'])->toBe(18.0);
});

test('la fiche économat suit l’inventaire ouvert et sa catégorie', function () {
    stocksDeTousLesServices();
    $epicerie = StockCategory::where('name', 'Épicerie')->sole();
    $inventaire = app(StockCountService::class)->open([]);

    $fiche = app(CountSheetService::class)->sheet('economat', $epicerie->id);

    expect($fiche['reference'])->toBe($inventaire->reference)
        ->and($fiche['subtitle'])->toBe('Épicerie')
        ->and(array_keys($fiche['groups']))->toBe(['Épicerie']);
});

test('la fiche à l’aveugle n’affiche pas le théorique', function () {
    stocksDeTousLesServices();

    $this->get(route('economat.count_sheets.print', ['service' => 'economat']))
        ->assertOk()
        ->assertSee('Riz parfumé')
        ->assertDontSee('Théorique');

    $this->get(route('economat.count_sheets.print', ['service' => 'economat', 'theorique' => 1]))
        ->assertOk()
        ->assertSee('Théorique')
        ->assertSee('40');
});

test('toutes les fiches s’impriment en une fois pour l’inventaire général', function () {
    stocksDeTousLesServices();

    $this->get(route('economat.count_sheets.print', ['service' => 'tout']))
        ->assertOk()
        ->assertSee('Riz parfumé')
        ->assertSee('Tomate fraîche')
        ->assertSee('Masque bamiléké')
        ->assertSee('Eau minérale')
        ->assertSee('4 fiche(s)');
});

test('un service inconnu est refusé', function () {
    $this->get(route('economat.count_sheets.print', ['service' => 'depot-999']))->assertSessionHasErrors('service');
});

test('l’écran des fiches liste les services', function () {
    stocksDeTousLesServices();

    $this->get(route('economat.count_sheets.index'))
        ->assertOk()
        ->assertSee('Économat — magasin central')
        ->assertSee('Mini-bar');
});
