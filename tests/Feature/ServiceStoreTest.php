<?php

use App\Models\JournalEntry;
use App\Models\RestaurantPantryItem;
use App\Models\ServiceStore;
use App\Models\ServiceStoreStock;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\StockRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Dépôts de service : ce que l'économat livre à un dépôt y entre en stock,
 * au coût de sortie du magasin, sans charge. Centimes FCFA.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 10:00');
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

afterEach(fn () => Carbon::setTestNow());

function eauMinerale(): StockItem
{
    $boissons = StockCategory::create(['name' => 'Boissons', 'stock_account' => '311000']);

    return StockItem::create([
        'stock_category_id' => $boissons->id, 'name' => 'Eau minérale 50 cl', 'unit' => 'bouteille',
        'current_stock' => 100, 'average_cost' => 25_000,
    ]);
}

function livrerAuDepot(ServiceStore $depot, StockItem $article, float $quantite): StockRequisition
{
    $demande = StockRequisition::create([
        'department' => $depot->department, 'service_store_id' => $depot->id,
        'status' => StockRequisition::STATUS_APPROVED,
    ]);
    $demande->lines()->create(['stock_item_id' => $article->id, 'quantity_requested' => $quantite]);

    app(StockRequisitionService::class)->deliver($demande);

    return $demande;
}

test('une livraison destinée à un dépôt y entre en stock au coût de l’économat', function () {
    $eau = eauMinerale();
    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);

    livrerAuDepot($minibar, $eau, 24);

    $stock = ServiceStoreStock::where('service_store_id', $minibar->id)->sole();
    expect((float) $stock->current_stock)->toBe(24.0)
        ->and($stock->average_cost)->toBe(25_000)
        ->and((float) $eau->fresh()->current_stock)->toBe(76.0)
        ->and($minibar->movements()->sole()->stock_account)->toBe('311000');
});

test('deux livraisons à des coûts différents donnent le coût moyen du dépôt', function () {
    $eau = eauMinerale();
    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);

    livrerAuDepot($minibar, $eau, 10);
    $eau->update(['average_cost' => 35_000]);
    livrerAuDepot($minibar, $eau, 10);

    expect(ServiceStoreStock::where('service_store_id', $minibar->id)->value('average_cost'))->toBe(30_000);
});

test('un dépôt du restaurant reçoit à la place du garde-manger', function () {
    $eau = eauMinerale();
    $bar = ServiceStore::create(['name' => 'Bar', 'department' => 'restaurant']);

    livrerAuDepot($bar, $eau, 12);

    expect(RestaurantPantryItem::count())->toBe(0)
        ->and((float) ServiceStoreStock::where('service_store_id', $bar->id)->value('current_stock'))->toBe(12.0);
});

test('la livraison à un dépôt ne passe aucune charge', function () {
    $eau = eauMinerale();
    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);

    livrerAuDepot($minibar, $eau, 24);

    expect(app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15')))->toBeNull()
        ->and(JournalEntry::count())->toBe(0);
});

test('sans dépôt, la livraison aux étages reste une charge', function () {
    $eau = eauMinerale();
    $demande = StockRequisition::create(['department' => 'housekeeping', 'status' => StockRequisition::STATUS_APPROVED]);
    $demande->lines()->create(['stock_item_id' => $eau->id, 'quantity_requested' => 4]);

    app(StockRequisitionService::class)->deliver($demande);

    expect(app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15')))->not->toBeNull();
});

test('le dépôt choisi doit appartenir au service qui demande', function () {
    $eau = eauMinerale();
    $bar = ServiceStore::create(['name' => 'Bar', 'department' => 'restaurant']);
    $gouvernante = User::factory()->create(['role' => 'housekeeping_leader']);

    $this->actingAs($gouvernante)
        ->post(route('economat.requisitions.store'), [
            'department'       => 'housekeeping',
            'service_store_id' => $bar->id,
            'lines'            => [['stock_item_id' => $eau->id, 'quantity' => 2]],
        ])
        ->assertSessionHasErrors('service_store_id');

    expect(StockRequisition::count())->toBe(0);
});

test('le service demande pour son dépôt', function () {
    $eau = eauMinerale();
    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);
    $gouvernante = User::factory()->create(['role' => 'housekeeping_leader']);

    $this->actingAs($gouvernante)->get(route('economat.requisitions.create'))->assertOk()->assertSee('Mini-bar');

    $this->actingAs($gouvernante)
        ->post(route('economat.requisitions.store'), [
            'department'       => 'housekeeping',
            'service_store_id' => $minibar->id,
            'lines'            => [['stock_item_id' => $eau->id, 'quantity' => 2]],
        ])
        ->assertRedirect();

    expect(StockRequisition::sole()->service_store_id)->toBe($minibar->id);
});

test('l’économe gère les dépôts et consulte leur stock', function () {
    $this->post(route('economat.stores.store'), ['name' => 'Pâtisserie', 'department' => 'restaurant'])
        ->assertSessionHas('success');

    $patisserie = ServiceStore::where('name', 'Pâtisserie')->sole();
    livrerAuDepot($patisserie, eauMinerale(), 6);

    $this->get(route('economat.stores.index'))->assertOk()->assertSee('Pâtisserie');
    $this->get(route('economat.stores.show', $patisserie))->assertOk()->assertSee('Eau minérale 50 cl');

    // Un dépôt avec historique ne se supprime pas et ne change plus de service.
    $this->delete(route('economat.stores.destroy', $patisserie))->assertSessionHas('error');
    $this->put(route('economat.stores.update', $patisserie), ['name' => 'Pâtisserie', 'department' => 'housekeeping'])
        ->assertSessionHas('error');
    expect($patisserie->fresh()->department)->toBe('restaurant');
});

test('le magasinier consulte les dépôts sans les gérer', function () {
    $magasinier = User::factory()->create(['role' => 'storekeeper']);

    $this->actingAs($magasinier)->get(route('economat.stores.index'))->assertOk()->assertDontSee('Nouveau dépôt');
    $this->actingAs($magasinier)->postJson(route('economat.stores.store'), ['name' => 'X', 'department' => 'housekeeping'])
        ->assertForbidden();
});
