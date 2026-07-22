<?php

use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseOrderService;
use App\Services\StockRequisitionService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function econome(): User
{
    $u = User::factory()->create(['role' => 'econome']);
    test()->actingAs($u);
    return $u;
}

test('le coût moyen pondéré est correct après deux réceptions à des prix différents', function () {
    econome();
    $item = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 0, 'average_cost' => 0]);
    $stock = app(StockService::class);

    $stock->recordIn($item, 100, 50000);   // 100 kg à 500 F
    $stock->recordIn($item, 100, 70000);   // 100 kg à 700 F

    $item->refresh();
    // CMP = (100*500 + 100*700) / 200 = 600 F
    expect($item->average_cost)->toBe(60000)
        ->and((float) $item->current_stock)->toBe(200.0);
});

test('une sortie ne peut pas dépasser le stock disponible', function () {
    econome();
    $item = StockItem::create(['name' => 'Draps', 'unit' => 'pièce', 'current_stock' => 5, 'average_cost' => 10000]);

    app(StockService::class)->recordOut($item, 10);
})->throws(RuntimeException::class);

test('le bon de commande est envoyé au fournisseur par email', function () {
    Mail::fake();
    econome();

    $supplier = Supplier::create(['name' => 'Grossiste', 'email' => 'grossiste@example.com', 'is_active' => true]);
    $item = StockItem::create(['name' => 'Savon', 'unit' => 'pièce', 'current_stock' => 0, 'average_cost' => 0]);

    $order = PurchaseOrder::create(['supplier_id' => $supplier->id]);
    $order->lines()->create(['stock_item_id' => $item->id, 'quantity_ordered' => 50, 'unit_price' => 20000]);

    $sent = app(PurchaseOrderService::class)->send($order->fresh());

    expect($sent)->toBeTrue()
        ->and($order->fresh()->status)->toBe(PurchaseOrder::STATUS_SENT);
    Mail::assertSent(\App\Mail\PurchaseOrderMail::class);
});

test('un bon sans email fournisseur ne peut pas être envoyé', function () {
    econome();
    $supplier = Supplier::create(['name' => 'Sans email', 'is_active' => true]);
    $item = StockItem::create(['name' => 'X', 'unit' => 'pièce']);
    $order = PurchaseOrder::create(['supplier_id' => $supplier->id]);
    $order->lines()->create(['stock_item_id' => $item->id, 'quantity_ordered' => 1, 'unit_price' => 100]);

    app(PurchaseOrderService::class)->send($order->fresh());
})->throws(RuntimeException::class);

test('la réception partielle puis totale met à jour le stock et le statut', function () {
    Mail::fake();
    econome();

    $supplier = Supplier::create(['name' => 'Fournisseur', 'email' => 'f@example.com', 'is_active' => true]);
    $item = StockItem::create(['name' => 'Serviettes', 'unit' => 'pièce', 'current_stock' => 0, 'average_cost' => 0]);
    $order = PurchaseOrder::create(['supplier_id' => $supplier->id]);
    $line = $order->lines()->create(['stock_item_id' => $item->id, 'quantity_ordered' => 100, 'unit_price' => 30000]);

    $service = app(PurchaseOrderService::class);
    $service->send($order->fresh());

    // Réception partielle
    $service->receive($order->fresh(), [$line->id => 60]);
    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and((float) $item->fresh()->current_stock)->toBe(60.0);

    // Solde
    $service->receive($order->fresh(), [$line->id => 40]);
    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and((float) $item->fresh()->current_stock)->toBe(100.0);
});

test('une demande ne peut pas être livrée avant d\'être validée', function () {
    econome();
    $item = StockItem::create(['name' => 'Article', 'unit' => 'pièce', 'current_stock' => 50]);
    $req = StockRequisition::create(['department' => 'housekeeping']);
    $req->lines()->create(['stock_item_id' => $item->id, 'quantity_requested' => 10]);

    app(StockRequisitionService::class)->deliver($req->fresh());
})->throws(RuntimeException::class);

test('le cycle complet validation puis livraison déstocke les articles', function () {
    econome();
    $item = StockItem::create(['name' => 'Produit', 'unit' => 'litre', 'current_stock' => 50, 'average_cost' => 20000]);
    $req = StockRequisition::create(['department' => 'restaurant']);
    $line = $req->lines()->create(['stock_item_id' => $item->id, 'quantity_requested' => 30]);

    $service = app(StockRequisitionService::class);
    $service->approve($req->fresh(), 'validé');
    expect($req->fresh()->status)->toBe(StockRequisition::STATUS_APPROVED);

    $service->deliver($req->fresh());
    expect($req->fresh()->status)->toBe(StockRequisition::STATUS_DELIVERED)
        ->and((float) $item->fresh()->current_stock)->toBe(20.0)       // 50 - 30
        ->and((float) $line->fresh()->quantity_issued)->toBe(30.0);

    // Le déstockage a créé un mouvement de sortie valorisé au CMP.
    $movement = StockMovement::where('source_type', 'requisition')->where('source_id', $req->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(StockMovement::TYPE_OUT)
        ->and($movement->unit_cost)->toBe(20000);
});

test('une demande supérieure au stock ne sert que le disponible sans passer en négatif', function () {
    econome();
    $item = StockItem::create(['name' => 'Rare', 'unit' => 'pièce', 'current_stock' => 8, 'average_cost' => 15000]);
    $req = StockRequisition::create(['department' => 'boutique']);
    $line = $req->lines()->create(['stock_item_id' => $item->id, 'quantity_requested' => 20]);

    $service = app(StockRequisitionService::class);
    $service->approve($req->fresh());
    $service->deliver($req->fresh());

    expect((float) $item->fresh()->current_stock)->toBe(0.0)
        ->and((float) $line->fresh()->quantity_issued)->toBe(8.0);
});

test('un ajustement d\'inventaire cale le stock et journalise l\'écart', function () {
    econome();
    $item = StockItem::create(['name' => 'À compter', 'unit' => 'pièce', 'current_stock' => 100, 'average_cost' => 5000]);

    $movement = app(StockService::class)->adjust($item, 92, 'inventaire mensuel');

    expect((float) $item->fresh()->current_stock)->toBe(92.0)
        ->and($movement->type)->toBe(StockMovement::TYPE_ADJUSTMENT)
        ->and((float) $movement->quantity)->toBe(-8.0);
});

test('un département accède au module demandes mais pas à la gestion du magasin', function () {
    $keeper = User::factory()->create(['role' => 'housekeeping_leader']);
    $this->actingAs($keeper);

    // Peut créer une demande…
    $this->get(route('economat.requisitions.create'))->assertOk();
    // …mais pas gérer les articles du magasin.
    $this->get(route('economat.items.index'))->assertRedirect();
});
