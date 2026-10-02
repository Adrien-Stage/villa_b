<?php

use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\StockRequisitionService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createEconomeUser(): User
{
    $u = User::factory()->create(['role' => 'econome']);
    test()->actingAs($u);
    return $u;
}

test('un transfert economat vers restaurant credite automatiquement le garde-manger et recalcule le CUMP', function () {
    createEconomeUser();

    // 1. Article Économat : 100 kg à 500 F (50 000 centimes / kg)
    $stockItem = StockItem::create([
        'name' => 'Farine de Blé',
        'unit' => 'kg',
        'current_stock' => 100,
        'average_cost' => 50000,
    ]);

    // 2. Article Garde-manger existant lié : 10 kg à 400 F (40 000 centimes / kg)
    $pantryCategory = RestaurantPantryCategory::create(['name' => 'Épicerie']);
    $pantryItem = RestaurantPantryItem::create([
        'stock_item_id' => $stockItem->id,
        'restaurant_pantry_category_id' => $pantryCategory->id,
        'name' => 'Farine de Blé',
        'unit' => 'kg',
        'current_stock' => 10.0,
        'average_cost' => 40000.0,
        'min_stock' => 2.0,
        'is_active' => true,
    ]);

    // 3. Demande de 20 kg pour le restaurant
    $requisition = StockRequisition::create(['department' => 'restaurant']);
    $line = $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 20,
    ]);

    $service = app(StockRequisitionService::class);
    $service->approve($requisition);
    $service->deliver($requisition);

    // Vérification Économat : 100 - 20 = 80 kg
    expect((float) $stockItem->fresh()->current_stock)->toBe(80.0)
        ->and($requisition->fresh()->status)->toBe(StockRequisition::STATUS_DELIVERED);

    $outMovement = StockMovement::where('source_type', 'requisition')
        ->where('source_id', $requisition->id)
        ->first();
    expect($outMovement)->not->toBeNull()
        ->and($outMovement->type)->toBe(StockMovement::TYPE_OUT)
        ->and((float) $outMovement->quantity)->toBe(-20.0)
        ->and($outMovement->unit_cost)->toBe(50000);

    // Vérification Garde-manger : 10 + 20 = 30 kg
    $pantryItem->refresh();
    expect((float) $pantryItem->current_stock)->toBe(30.0);

    // CUMP Garde-manger : (10 * 40000 + 20 * 50000) / 30 = (400 000 + 1 000 000) / 30 = 1 400 000 / 30 = 46666.6667
    expect(round((float) $pantryItem->average_cost, 2))->toBe(round(1400000 / 30, 2));

    // Mouvement d'entrée dans le garde-manger
    $pantryMovement = RestaurantPantryMovement::where('restaurant_pantry_item_id', $pantryItem->id)
        ->where('stock_requisition_id', $requisition->id)
        ->first();

    expect($pantryMovement)->not->toBeNull()
        ->and($pantryMovement->type)->toBe(RestaurantPantryMovement::TYPE_IN)
        ->and($pantryMovement->reason)->toBe(RestaurantPantryMovement::REASON_TRANSFER_IN)
        ->and((float) $pantryMovement->quantity)->toBe(20.0)
        ->and((int) $pantryMovement->total_cost)->toBe(1000000); // 20 * 50 000 centimes
});

test('un transfert avec conversion d unites convertit correctement les quantites et les couts unitaires', function () {
    createEconomeUser();

    // Économat : en kg, coût 2 000 F (200 000 centimes / kg)
    $stockItem = StockItem::create([
        'name' => 'Poivre Blanc de Penja',
        'unit' => 'kg',
        'current_stock' => 10,
        'average_cost' => 200000,
    ]);

    // Garde-manger : géré en grammes (g), purchase_unit = kg, purchase_conversion = 1000
    $pantryItem = RestaurantPantryItem::create([
        'stock_item_id' => $stockItem->id,
        'name' => 'Poivre Blanc',
        'unit' => 'g',
        'purchase_unit' => 'kg',
        'purchase_conversion' => 1000.0,
        'current_stock' => 500.0, // 500 g
        'average_cost' => 200.0,  // 200 centimes / g (2 F / g)
        'is_active' => true,
    ]);

    // Demande de 2 kg
    $requisition = StockRequisition::create(['department' => 'restaurant']);
    $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 2.0,
    ]);

    $service = app(StockRequisitionService::class);
    $service->approve($requisition);
    $service->deliver($requisition);

    $pantryItem->refresh();
    // 500 g + 2 000 g = 2 500 g
    expect((float) $pantryItem->current_stock)->toBe(2500.0);

    // Coût unitaire pour 2 kg à 200 000 = 400 000 centimes. Pour 2 000 g, coût unitaire = 200 centimes / g.
    expect((float) $pantryItem->average_cost)->toBe(200.0);

    $pantryMovement = RestaurantPantryMovement::where('restaurant_pantry_item_id', $pantryItem->id)
        ->where('stock_requisition_id', $requisition->id)
        ->first();

    expect((float) $pantryMovement->quantity)->toBe(2000.0)
        ->and((int) $pantryMovement->total_cost)->toBe(400000);
});

test('un article economat non present dans le garde-manger est cree automatiquement a la livraison', function () {
    createEconomeUser();

    $stockItem = StockItem::create([
        'name' => 'Huile de Palme Rouge',
        'unit' => 'litre',
        'current_stock' => 50,
        'average_cost' => 120000, // 1 200 F / L
    ]);

    expect(RestaurantPantryItem::where('name', 'Huile de Palme Rouge')->exists())->toBeFalse();

    $requisition = StockRequisition::create(['department' => 'restaurant']);
    $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 10,
    ]);

    $service = app(StockRequisitionService::class);
    $service->approve($requisition);
    $service->deliver($requisition);

    $pantryItem = RestaurantPantryItem::where('stock_item_id', $stockItem->id)->first();
    expect($pantryItem)->not->toBeNull()
        ->and($pantryItem->name)->toBe('Huile de Palme Rouge')
        ->and((float) $pantryItem->current_stock)->toBe(10.0)
        ->and((float) $pantryItem->average_cost)->toBe(120000.0);
});

test('une livraison pour la boutique incremente le stock du produit de la boutique', function () {
    createEconomeUser();

    $stockItem = StockItem::create([
        'name' => 'Savon Artisanal Moringa',
        'reference' => 'SAV-MOR-01',
        'unit' => 'pièce',
        'current_stock' => 50,
        'average_cost' => 100000,
    ]);

    $shopCategory = ShopCategory::create(['name' => 'Cosmétiques']);
    $product = ShopProduct::create([
        'shop_category_id' => $shopCategory->id,
        'name' => 'Savon Artisanal Moringa',
        'sku' => 'SAV-MOR-01',
        'price' => 250000,
        'stock_quantity' => 5,
        'reorder_level' => 3,
        'is_active' => true,
    ]);

    $requisition = StockRequisition::create(['department' => 'boutique']);
    $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 15,
    ]);

    $service = app(StockRequisitionService::class);
    $service->approve($requisition);
    $service->deliver($requisition);

    expect((float) $stockItem->fresh()->current_stock)->toBe(35.0)
        ->and((int) $product->fresh()->stock_quantity)->toBe(20); // 5 + 15
});

test('une livraison pour le housekeeping destocke l economat sans anomalie', function () {
    createEconomeUser();

    $stockItem = StockItem::create([
        'name' => 'Désinfectant multi-surfaces',
        'unit' => 'litre',
        'current_stock' => 30,
        'average_cost' => 80000,
    ]);

    $requisition = StockRequisition::create(['department' => 'housekeeping']);
    $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 5,
    ]);

    $service = app(StockRequisitionService::class);
    $service->approve($requisition);
    $service->deliver($requisition);

    expect((float) $stockItem->fresh()->current_stock)->toBe(25.0)
        ->and($requisition->fresh()->status)->toBe(StockRequisition::STATUS_DELIVERED);
});

test('la transaction est atomique : un echec a la reception annule le destockage economat', function () {
    createEconomeUser();

    $stockItem = StockItem::create([
        'name' => 'Sucre en poudre',
        'unit' => 'kg',
        'current_stock' => 50,
        'average_cost' => 60000,
    ]);

    $requisition = StockRequisition::create(['department' => 'restaurant']);
    $requisition->lines()->create([
        'stock_item_id' => $stockItem->id,
        'quantity_requested' => 10,
    ]);

    $mockRestaurantStock = Mockery::mock(\App\Services\RestaurantStockService::class);
    $mockRestaurantStock->shouldReceive('receiveFromEconomat')
        ->andThrow(new RuntimeException('Erreur simulée lors du transfert garde-manger'));

    $service = new StockRequisitionService(app(StockService::class), $mockRestaurantStock);
    $service->approve($requisition);

    try {
        $service->deliver($requisition);
        test()->fail('La méthode deliver aurait dû lever une exception');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Erreur simulée lors du transfert garde-manger');
    }

    // Le stock économat n'a pas bougé et la demande reste approuvée (non livrée)
    expect((float) $stockItem->fresh()->current_stock)->toBe(50.0)
        ->and($requisition->fresh()->status)->toBe(StockRequisition::STATUS_APPROVED)
        ->and(StockMovement::where('source_id', $requisition->id)->exists())->toBeFalse();
});
