<?php

use App\Models\PointOfSale;
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
use App\Services\RestaurantStockService;
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

test('chaque restaurant a sa fiche de cuisine, sur le théorique figé de son inventaire', function () {
    $jardin = PointOfSale::create(['code' => 'JAR', 'slug' => 'jardin', 'name' => 'Le Jardin', 'kind' => PointOfSale::KIND_RESTAURATION, 'is_active' => true, 'sort_order' => 1]);
    $kotibe = PointOfSale::create(['code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe', 'kind' => PointOfSale::KIND_RESTAURATION, 'is_active' => true, 'sort_order' => 2]);
    $legumes = RestaurantPantryCategory::create(['name' => 'Légumes']);
    RestaurantPantryItem::create(['point_of_sale_id' => $jardin->id, 'restaurant_pantry_category_id' => $legumes->id, 'name' => 'Tomate fraîche', 'unit' => 'kg', 'current_stock' => 6, 'is_active' => true]);
    RestaurantPantryItem::create(['point_of_sale_id' => $kotibe->id, 'restaurant_pantry_category_id' => $legumes->id, 'name' => 'Gombo', 'unit' => 'kg', 'current_stock' => 2, 'is_active' => true]);

    $services = array_keys(app(CountSheetService::class)->services());

    expect($services)->toContain('garde-manger-' . $jardin->id, 'garde-manger-' . $kotibe->id)
        ->not->toContain('garde-manger');

    $inventaire = app(RestaurantStockService::class)->openStockCount(null, $kotibe);
    RestaurantPantryItem::where('name', 'Gombo')->update(['current_stock' => 9]);

    $fiche = app(CountSheetService::class)->sheet('garde-manger-' . $kotibe->id);

    expect($fiche['title'])->toBe('Cuisine — Kotibe')
        ->and($fiche['reference'])->toBe($inventaire->reference)
        ->and($fiche['groups']['Légumes'])->toHaveCount(1)
        ->and($fiche['groups']['Légumes'][0])->toMatchArray(['name' => 'Gombo', 'theoretical' => 2.0]);

    $this->get(route('economat.count_sheets.print', ['service' => 'garde-manger-' . $kotibe->id, 'theorique' => 1]))
        ->assertOk()
        ->assertSee('Gombo')
        ->assertDontSee('Tomate fraîche')
        ->assertSee('stock théorique figé le ' . $inventaire->created_at->format('d/m/Y à H:i'));
});

test('une fiche dit qui l’a imprimée et se signe page par page', function () {
    stocksDeTousLesServices();
    auth()->user()->update(['name' => 'Awa Ngono']);

    $this->get(route('economat.count_sheets.print', ['service' => 'economat']))
        ->assertOk()
        ->assertSee('par Awa Ngono')
        ->assertSee('Visa du compteur')
        ->assertSee('Écrire 0 pour un article absent');
});

// ── Articles à compter ──────────────────────────────────────────────────────

/**
 * Trois articles à l'économat : du riz en stock, du sel jamais bougé et à 0,
 * de l'huile vidée le 5 octobre.
 */
function magasinAvecArticleVide(): array
{
    test()->travelTo(\Carbon\CarbonImmutable::parse('2026-10-01 08:00'));
    $stock = app(\App\Services\StockService::class);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'kg', 'is_active' => true]);
    $sel = StockItem::create(['name' => 'Sel fin', 'unit' => 'kg', 'is_active' => true]);
    $huile = StockItem::create(['name' => 'Huile de palme', 'unit' => 'litre', 'is_active' => true]);
    $stock->recordOpening($riz, 40, 80000);
    $stock->recordOpening($huile, 5, 150000);

    test()->travelTo(\Carbon\CarbonImmutable::parse('2026-10-05 10:00'));
    $stock->recordOut($huile, 5, \App\Models\StockMovement::SOURCE_MANUAL, null, 'Service cuisine');

    test()->travelTo(\Carbon\CarbonImmutable::parse('2026-10-09 08:00'));

    return compact('riz', 'sel', 'huile');
}

function nomsDeLaFiche(array $fiche): array
{
    return collect($fiche['groups'])->flatten(1)->pluck('name')->sort()->values()->all();
}

test('la fiche liste tous les articles, ou seulement ceux en stock', function () {
    magasinAvecArticleVide();
    $fiches = app(CountSheetService::class);

    expect(nomsDeLaFiche($fiches->sheet('economat')))->toBe(['Huile de palme', 'Riz parfumé', 'Sel fin'])
        ->and(nomsDeLaFiche($fiches->sheet('economat', null, CountSheetService::ARTICLES_EN_STOCK)))->toBe(['Riz parfumé'])
        ->and($fiches->sheet('economat', null, CountSheetService::ARTICLES_EN_STOCK)['selection'])->toBe('Articles en stock uniquement');
});

test('un article tombé à 0 après un mouvement de la période reste sur la fiche', function () {
    magasinAvecArticleVide();
    $fiches = app(CountSheetService::class);

    // Sans inventaire clôturé : depuis le début du mois. L'huile a bougé le 5, le sel jamais.
    $fiche = $fiches->sheet('economat', null, CountSheetService::ARTICLES_EN_STOCK_ET_MOUVEMENTES);
    expect(nomsDeLaFiche($fiche))->toBe(['Huile de palme', 'Riz parfumé'])
        ->and($fiche['selection'])->toContain('depuis le 01/10/2026');

    // Une période qui commence après le mouvement l'écarte.
    $fiche = $fiches->sheet('economat', null, CountSheetService::ARTICLES_EN_STOCK_ET_MOUVEMENTES, \Carbon\CarbonImmutable::parse('2026-10-06'));
    expect(nomsDeLaFiche($fiche))->toBe(['Riz parfumé']);
});

test('par défaut, la période part du dernier inventaire clôturé du service', function () {
    $articles = magasinAvecArticleVide();

    // Inventaire clôturé le 7 : la sortie d'huile du 5 le précède.
    test()->travelTo(\Carbon\CarbonImmutable::parse('2026-10-07 18:00'));
    $service = app(StockCountService::class);
    $service->close($service->open([]));
    test()->travelTo(\Carbon\CarbonImmutable::parse('2026-10-09 08:00'));

    $fiche = app(CountSheetService::class)->sheet('economat', null, CountSheetService::ARTICLES_EN_STOCK_ET_MOUVEMENTES);
    expect(nomsDeLaFiche($fiche))->toBe(['Riz parfumé'])
        ->and($fiche['selection'])->toContain('depuis le 07/10/2026 (dernier inventaire)');
});

test('au garde-manger, un stock négatif se compte aussi', function () {
    RestaurantPantryItem::create(['name' => 'Oignon', 'unit' => 'kg', 'current_stock' => -2, 'is_active' => true]);
    RestaurantPantryItem::create(['name' => 'Ail', 'unit' => 'kg', 'current_stock' => 0, 'is_active' => true]);

    $fiche = app(CountSheetService::class)->sheet('garde-manger', null, CountSheetService::ARTICLES_EN_STOCK);

    expect(nomsDeLaFiche($fiche))->toBe(['Oignon']);
});

test('la fiche imprimée dit quels articles elle liste', function () {
    magasinAvecArticleVide();

    $this->get(route('economat.count_sheets.index'))->assertOk()->assertSee('Articles à compter');

    $this->get(route('economat.count_sheets.print', ['service' => 'economat', 'articles' => 'mouvementes', 'depuis' => '2026-10-02']))
        ->assertOk()
        ->assertSee('Articles en stock, et articles tombés à 0 après un mouvement depuis le 02/10/2026')
        ->assertSee('Huile de palme')
        ->assertDontSee('Sel fin');

    $this->get(route('economat.count_sheets.print', ['service' => 'economat', 'articles' => 'n_importe_quoi']))->assertSessionHasErrors('articles');
});

test('toutes les fiches appliquent le même choix, boutique et dépôts compris', function () {
    $minibar = stocksDeTousLesServices();
    ShopProduct::create(['shop_category_id' => ShopCategory::first()->id, 'name' => 'Carte postale', 'sku' => 'CP-01', 'price' => 50_000, 'stock_quantity' => 0, 'is_active' => true]);
    ServiceStoreStock::create(['service_store_id' => $minibar->id, 'stock_item_id' => StockItem::create(['name' => 'Soda', 'unit' => 'canette'])->id, 'current_stock' => 0, 'average_cost' => 30_000]);

    $fiches = collect(app(CountSheetService::class)->sheets(CountSheetService::ALL, null, CountSheetService::ARTICLES_EN_STOCK_ET_MOUVEMENTES))->keyBy('key');

    expect(nomsDeLaFiche($fiches['boutique']))->toBe(['Masque bamiléké'])
        ->and(nomsDeLaFiche($fiches['depot-' . $minibar->id]))->toBe(['Eau minérale']);

    $this->get(route('economat.count_sheets.print', ['service' => 'tout', 'articles' => 'en_stock']))->assertOk()->assertDontSee('Carte postale');
});
