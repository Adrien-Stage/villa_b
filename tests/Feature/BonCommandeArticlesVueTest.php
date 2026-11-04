<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

test('la carte Articles du bon de commande ne contient plus le champ reçu maintenant ni le bouton valider la réception', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'econome')->pluck('id'));

    $supplier = Supplier::create(['name' => 'Graphik Pro', 'is_active' => true]);
    $item = StockItem::create([
        'name' => 'Blocs-notes à en-tête',
        'unit' => 'pcs',
        'current_stock' => 10,
        'is_active' => true,
    ]);

    $order = PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_SENT,
        'created_by' => $user->id,
    ]);

    PurchaseOrderLine::create([
        'purchase_order_id' => $order->id,
        'stock_item_id' => $item->id,
        'quantity_ordered' => 4,
        'quantity_received' => 0,
        'unit_price' => 70000,
    ]);

    $response = $this->actingAs($user)->get(route('economat.orders.show', $order));

    $response->assertOk();

    // Vérifie la présence des colonnes standard
    $response->assertSee('Articles');
    $response->assertSee('Article');
    $response->assertSee('Unité');
    $response->assertSee('Commandé');
    $response->assertSee('Déjà reçu');
    $response->assertSee('P.U.');
    $response->assertSee('Total');
    $response->assertSee('Total Général');

    // Vérifie le retrait effectif du champ "reçu maintenant" et du bouton "Valider la réception"
    $response->assertDontSee('Reçu maintenant');
    $response->assertDontSee('Valider la réception');
    $response->assertDontSee('name="received[', false);
    $response->assertDontSee('name="received_packaging[', false);
});
