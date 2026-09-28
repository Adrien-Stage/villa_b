<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrderLine;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantStockCount;
use App\Models\RestaurantStockCountLine;
use App\Models\RestaurantWasteLog;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\StockControlService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTestUser(string $role = 'econome'): User
{
    $u = User::factory()->create(['role' => $role]);
    test()->actingAs($u);
    return $u;
}

test('la valorisation consolidee calcule exactement la valeur CUMP du magasin central et du garde-manger restaurant', function () {
    createTestUser('econome');

    $cat1 = StockCategory::create(['name' => 'Épicerie']);
    $item1 = StockItem::create([
        'name'              => 'Farine T55 25kg',
        'unit'              => 'sac',
        'current_stock'     => 10,
        'min_stock'         => 4,
        'average_cost'      => 1500000, // 15 000 FCFA
        'stock_category_id' => $cat1->id,
        'is_active'         => true,
    ]);

    $item2 = StockItem::create([
        'name'              => 'Huile Végétale 5L',
        'unit'              => 'bidon',
        'current_stock'     => 5,
        'min_stock'         => 2,
        'average_cost'      => 600000, // 6 000 FCFA
        'stock_category_id' => $cat1->id,
        'is_active'         => true,
    ]);

    // Garde-manger restaurant
    $pantryItem = RestaurantPantryItem::create([
        'name'          => 'Tomates fraîches',
        'unit'          => 'kg',
        'current_stock' => 20,
        'min_stock'     => 5,
        'average_cost'  => 100000, // 1 000 FCFA / kg
        'is_active'     => true,
    ]);

    $service = app(StockControlService::class);
    $valuation = $service->getGlobalValuation();

    // 10 * 1500000 + 5 * 600000 = 15000000 + 3000000 = 18000000 (180 000 F)
    expect($valuation['economat_value'])->toBe(18000000);
    // 20 * 100000 = 2000000 (20 000 F)
    expect($valuation['pantry_value'])->toBe(2000000);
    // Total = 20000000 (200 000 F)
    expect($valuation['total_value'])->toBe(20000000)
        ->and($valuation['economat_items_count'])->toBe(2)
        ->and($valuation['pantry_items_count'])->toBe(1);
});

test('la detection des alertes identifie les articles sous seuil minimum', function () {
    createTestUser('econome');

    // Article en alerte
    StockItem::create([
        'name'          => 'Beurre de cuisine',
        'unit'          => 'kg',
        'current_stock' => 1,
        'min_stock'     => 5,
        'average_cost'  => 400000,
        'is_active'     => true,
    ]);

    // Article au-dessus du seuil
    StockItem::create([
        'name'          => 'Sucre en poudre',
        'unit'          => 'kg',
        'current_stock' => 10,
        'min_stock'     => 5,
        'average_cost'  => 80000,
        'is_active'     => true,
    ]);

    // Pantry en alerte
    RestaurantPantryItem::create([
        'name'          => 'Crème fraîche',
        'unit'          => 'L',
        'current_stock' => 0.5,
        'min_stock'     => 2,
        'average_cost'  => 250000,
        'is_active'     => true,
    ]);

    $service = app(StockControlService::class);
    $alerts = $service->getLowStockAlerts();

    expect($alerts['total'])->toBe(2)
        ->and($alerts['economat'])->toHaveCount(1)
        ->and($alerts['economat']->first()->name)->toBe('Beurre de cuisine')
        ->and($alerts['pantry'])->toHaveCount(1)
        ->and($alerts['pantry']->first()->name)->toBe('Crème fraîche');
});

test('la generation des propositions de commande calcule les quantites pour atteindre le stock cible', function () {
    createTestUser('econome');

    $supplier = Supplier::create([
        'name'     => 'Grossiste Alimentaire SARL',
        'currency' => 'XAF',
    ]);

    $item = StockItem::create([
        'name'                => 'Café grains Robusta 1kg',
        'unit'                => 'kg',
        'current_stock'       => 2,
        'min_stock'           => 10, // Target = 20 (2x min)
        'last_purchase_price' => 500000, // 5 000 FCFA
        'supplier_id'         => $supplier->id,
        'is_active'           => true,
    ]);

    $service = app(StockControlService::class);
    $suggestions = $service->generateOrderSuggestions();

    expect($suggestions)->toHaveCount(1);
    $sug = $suggestions[0];
    expect($sug['current_stock'])->toBe(2.0)
        ->and($sug['min_stock'])->toBe(10.0)
        ->and($sug['target_stock'])->toBe(20.0)
        ->and($sug['suggested_quantity'])->toBe(18.0) // 20 - 2 = 18
        ->and($sug['estimated_unit_price'])->toBe(500000)
        ->and($sug['estimated_total_cost'])->toBe(18 * 500000)
        ->and($sug['supplier']->id)->toBe($supplier->id);
});

test('la creation de demande d achat depuis les propositions genere une vraie PurchaseRequest', function () {
    $user = createTestUser('econome');

    $item1 = StockItem::create([
        'name'          => 'Riz Parfumé 25kg',
        'unit'          => 'sac',
        'current_stock' => 1,
        'min_stock'     => 5,
        'average_cost'  => 1800000,
        'is_active'     => true,
    ]);

    $item2 = StockItem::create([
        'name'          => 'Huile Olive 1L',
        'unit'          => 'bouteille',
        'current_stock' => 2,
        'min_stock'     => 6,
        'average_cost'  => 700000,
        'is_active'     => true,
    ]);

    $service = app(StockControlService::class);

    $purchaseRequest = $service->createPurchaseRequestFromSuggestions(
        selectedItems: [
            ['item_id' => $item1->id, 'quantity' => 9, 'notes' => 'Urgent banquet'],
            ['item_id' => $item2->id, 'quantity' => 10, 'notes' => null],
        ],
        user: $user,
        priority: PurchaseRequest::PRIORITY_URGENT
    );

    expect($purchaseRequest)->toBeInstanceOf(PurchaseRequest::class)
        ->and($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_PENDING)
        ->and($purchaseRequest->priority)->toBe(PurchaseRequest::PRIORITY_URGENT)
        ->and($purchaseRequest->department)->toBe('economat')
        ->and($purchaseRequest->lines)->toHaveCount(2);

    $expectedTotal = (9 * 1800000) + (10 * 700000);
    expect($purchaseRequest->total_estimated_amount)->toBe($expectedTotal);
});

test('l analyse des ecarts d inventaire calcule les pertes et surplus pour les inventaires clotures', function () {
    $user = createTestUser('econome');

    $item = StockItem::create([
        'name'          => 'Champagne Brut 75cl',
        'unit'          => 'btl',
        'current_stock' => 10,
        'min_stock'     => 5,
        'average_cost'  => 2500000,
        'is_active'     => true,
    ]);

    $stockCount = StockCount::create([
        'reference' => 'INV-TEST-001',
        'status'    => StockCount::STATUS_CLOSED,
        'closed_at' => now(),
        'closed_by' => $user->id,
        'opened_by' => $user->id,
    ]);

    StockCountLine::create([
        'stock_count_id'       => $stockCount->id,
        'stock_item_id'        => $item->id,
        'unit'                 => 'btl',
        'theoretical_quantity' => 12,
        'counted_quantity'     => 10,
        'variance_quantity'    => -2, // 2 manquants
        'unit_cost'            => 2500000,
        'variance_value'       => -5000000, // -50 000 FCFA
        'variance_reason'      => 'casse',
    ]);

    $service = app(StockControlService::class);
    $variances = $service->getInventoryVariancesSummary(now()->startOfDay(), now()->endOfDay());

    expect($variances['economat']['count'])->toBe(1)
        ->and($variances['economat']['loss_value'])->toBe(5000000)
        ->and($variances['economat']['variance_value'])->toBe(-5000000)
        ->and($variances['total_loss_value'])->toBe(5000000);
});

test('le rapport consolide calcule le ratio food cost reel avec CA et pertes', function () {
    $user = createTestUser('manager');

    $menuItem = RestaurantMenuItem::create([
        'name'          => 'Steak Frites',
        'price'         => 1000000, // 10 000 FCFA
        'internal_cost' => 300000,  // 3 000 FCFA
        'is_active'     => true,
    ]);

    // Commande restaurant : CA = 20 000 F, coût théorique = 6 000 F
    $order = RestaurantCustomerOrder::create([
        'order_number' => 'CMD-001',
        'status'       => RestaurantCustomerOrder::STATUS_SERVED,
        'placed_at'    => now(),
        'total_amount' => 2000000,
        'food_cost'    => 600000,
    ]);

    $pantryItem = RestaurantPantryItem::create([
        'name'          => 'Sauce béarnaise',
        'unit'          => 'portion',
        'current_stock' => 5,
        'min_stock'     => 1,
        'average_cost'  => 100000,
        'is_active'     => true,
    ]);

    // Perte restaurant enregistrée : 1 000 F
    RestaurantWasteLog::create([
        'reference'                 => 'PERTE-TEST-001',
        'restaurant_pantry_item_id' => $pantryItem->id,
        'department'                => RestaurantWasteLog::DEPT_KITCHEN,
        'quantity'                  => 1,
        'unit_cost'                 => 100000,
        'total_cost'                => 100000, // 1 000 F
        'reason'                    => RestaurantWasteLog::REASON_SPOILAGE,
        'occurred_at'               => now(),
        'recorded_by'               => $user->id,
    ]);

    $service = app(StockControlService::class);
    $report = $service->getExecutiveReport(now()->startOfDay(), now()->endOfDay());

    expect($report['restaurant_revenue'])->toBe(2000000)
        ->and($report['theoretical_food_cost'])->toBe(600000)
        ->and($report['total_waste_value'])->toBe(100000);

    // Coût matière réel = 600 000 + 100 000 = 700 000
    // Food Cost % = (700 000 / 2 000 000) * 100 = 35.0 %
    expect($report['real_kitchen_cost'])->toBe(700000)
        ->and($report['food_cost_percent'])->toBe(35.0);
});

test('les ecrans HTTP de controle des stocks sont accessibles aux roles autorises et proteges', function () {
    // 1. Économe a accès au dashboard de contrôle
    $econome = createTestUser('econome');

    $item = StockItem::create([
        'name'          => 'Pâtes Penne 5kg',
        'unit'          => 'sac',
        'current_stock' => 1,
        'min_stock'     => 10,
        'average_cost'  => 500000,
        'is_active'     => true,
    ]);

    $res = test()->get(route('economat.control.index'));
    $res->assertOk();
    $res->assertSee('Contrôle général des stocks &amp; Food Cost', false);

    // Propositions
    $resS = test()->get(route('economat.control.suggestions.index'));
    $resS->assertOk();
    $resS->assertSee('Propositions automatiques de commande');

    // Génération de la demande d'achat via POST
    $postRes = test()->post(route('economat.control.suggestions.store'), [
        'items' => [
            ['item_id' => $item->id, 'quantity' => 19, 'notes' => 'Test réapprovisionnement'],
        ],
        'priority' => 'normal',
    ]);
    $postRes->assertRedirect();

    // Écarts
    $resV = test()->get(route('economat.control.variances.index'));
    $resV->assertOk();

    // Impression
    $resP = test()->get(route('economat.control.print'));
    $resP->assertOk();
    $resP->assertSee('RAPPORT OFFICIEL DE CONTRÔLE');

    // 2. Un rôle non autorisé (ex: réception) est refusé (redirection vers dashboard avec popup de refus)
    createTestUser('reception');
    test()->from(route('dashboard'))->get(route('economat.control.index'))->assertRedirect(route('dashboard'))->assertSessionHas('access_denied_popup');
    test()->from(route('dashboard'))->get(route('economat.control.suggestions.index'))->assertRedirect(route('dashboard'))->assertSessionHas('access_denied_popup');
    test()->from(route('dashboard'))->get(route('economat.control.variances.index'))->assertRedirect(route('dashboard'))->assertSessionHas('access_denied_popup');
});
