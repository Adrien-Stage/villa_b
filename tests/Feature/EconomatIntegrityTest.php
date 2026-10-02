<?php

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\StockControlService;
use App\Services\StockCountService;
use App\Services\StockRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Intégrité du stock de l'économat : rejeu d'une opération, annulation d'une
 * réception, chiffres du rapport consolidé. Montants en centimes FCFA.
 */

beforeEach(function () {
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

function bonEnvoye(StockItem $article, float $quantite, int $prix): PurchaseOrder
{
    $bon = PurchaseOrder::create([
        'supplier_id' => Supplier::create(['name' => 'Grossiste', 'is_active' => true])->id,
        'status'      => PurchaseOrder::STATUS_SENT,
    ]);

    PurchaseOrderLine::create([
        'purchase_order_id' => $bon->id,
        'stock_item_id'     => $article->id,
        'quantity_ordered'  => $quantite,
        'unit_price'        => $prix,
    ]);

    return $bon;
}

function receptionner(PurchaseOrder $bon, float $quantite): GoodsReceipt
{
    $ligne = $bon->lines()->first();

    return app(GoodsReceiptService::class)->receive($bon, [
        'lines' => [$ligne->id => ['quantity_delivered' => $quantite]],
    ], auth()->user());
}

function demandeValidee(string $service, StockItem $article, float $quantite): StockRequisition
{
    $demande = StockRequisition::create(['department' => $service, 'status' => StockRequisition::STATUS_APPROVED]);
    $demande->lines()->create(['stock_item_id' => $article->id, 'quantity_requested' => $quantite]);

    return $demande;
}

// ── Annulation d'une réception ──────────────────────────────────────────────

test('annuler une réception rend au CUMP la valeur d’avant la réception', function () {
    // 10 kg à 1 000 F, puis 10 kg reçus à 3 000 F : CUMP à 2 000 F.
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 10, 'average_cost' => 100_000]);
    $reception = receptionner(bonEnvoye($riz, 10, 300_000), 10);
    expect($riz->fresh()->average_cost)->toBe(200_000);

    app(GoodsReceiptService::class)->cancel($reception, auth()->user());

    $riz->refresh();
    expect((float) $riz->current_stock)->toBe(10.0)
        ->and($riz->average_cost)->toBe(100_000)
        ->and($riz->movements()->latest('id')->first()->unit_cost)->toBe(300_000);
});

test('un bon dont toutes les réceptions sont annulées redevient envoyé', function () {
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg']);
    $bon = bonEnvoye($riz, 10, 100_000);
    $reception = receptionner($bon, 10);
    expect($bon->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);

    app(GoodsReceiptService::class)->cancel($reception, auth()->user());

    $bon->refresh();
    expect($bon->status)->toBe(PurchaseOrder::STATUS_SENT)
        ->and($bon->received_at)->toBeNull()
        ->and($bon->canBeReceived())->toBeTrue();
});

test('une réception annulée depuis un écran périmé ne sort pas deux fois le stock', function () {
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 5, 'average_cost' => 100_000]);
    $reception = receptionner(bonEnvoye($riz, 10, 100_000), 10);

    $ecranA = GoodsReceipt::find($reception->id);
    $ecranB = GoodsReceipt::find($reception->id);

    app(GoodsReceiptService::class)->cancel($ecranA, auth()->user());

    expect(fn () => app(GoodsReceiptService::class)->cancel($ecranB, auth()->user()))
        ->toThrow(RuntimeException::class, 'déjà annulé');
    expect((float) $riz->fresh()->current_stock)->toBe(5.0);
});

test('une réception sur un bon devenu soldé est refusée', function () {
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg']);
    $bon = bonEnvoye($riz, 10, 100_000);
    $ecranPerime = PurchaseOrder::find($bon->id);

    receptionner($bon, 10);

    expect(fn () => receptionner($ecranPerime, 10))->toThrow(RuntimeException::class);
    expect((float) $riz->fresh()->current_stock)->toBe(10.0);
});

// ── Livraison d'une demande ─────────────────────────────────────────────────

test('une demande livrée depuis deux écrans ne déstocke qu’une fois', function () {
    $javel = StockItem::create(['name' => 'Javel', 'unit' => 'litre', 'current_stock' => 20, 'average_cost' => 50_000]);
    $demande = demandeValidee('housekeeping', $javel, 5);

    $ecranA = StockRequisition::find($demande->id);
    $ecranB = StockRequisition::find($demande->id);

    app(StockRequisitionService::class)->deliver($ecranA);

    expect(fn () => app(StockRequisitionService::class)->deliver($ecranB))
        ->toThrow(RuntimeException::class, 'validée');
    expect((float) $javel->fresh()->current_stock)->toBe(15.0)
        ->and($ecranA->status)->toBe(StockRequisition::STATUS_DELIVERED);
});

test('une demande déjà validée ne peut pas être refusée depuis un écran périmé', function () {
    $javel = StockItem::create(['name' => 'Javel', 'unit' => 'litre', 'current_stock' => 20]);
    $demande = StockRequisition::create(['department' => 'housekeeping']);
    $ecranPerime = StockRequisition::find($demande->id);

    app(StockRequisitionService::class)->approve($demande);

    expect(fn () => app(StockRequisitionService::class)->reject($ecranPerime))
        ->toThrow(RuntimeException::class, 'déjà été traitée');
    expect($demande->fresh()->status)->toBe(StockRequisition::STATUS_APPROVED);
});

// ── Inventaire ──────────────────────────────────────────────────────────────

test('une feuille d’inventaire annulée ne peut plus régulariser le stock', function () {
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 10, 'average_cost' => 100_000]);
    $service = app(StockCountService::class);

    $feuille = $service->open([]);
    $ligne = $feuille->lines->first();
    $service->updateCounts($feuille, [$ligne->id => ['counted_quantity' => 4]]);
    $ecranPerime = StockCount::find($feuille->id);

    $service->cancel($feuille);

    expect(fn () => $service->close($ecranPerime))->toThrow(RuntimeException::class, 'plus en cours');
    expect((float) $riz->fresh()->current_stock)->toBe(10.0);
});

// ── Livraison à la boutique ─────────────────────────────────────────────────

function produitBoutique(string $nom, string $sku, int $stock): ShopProduct
{
    return ShopProduct::create([
        'shop_category_id' => ShopCategory::create(['name' => 'Cosmétiques'])->id,
        'name'             => $nom,
        'sku'              => $sku,
        'price'            => 250_000,
        'stock_quantity'   => $stock,
        'is_active'        => true,
    ]);
}

test('la boutique retrouve son produit par la référence même si le nom diffère', function () {
    $savon = StockItem::create(['name' => 'Savon moringa 100 g', 'reference' => 'SAV-01', 'unit' => 'pièce', 'current_stock' => 50]);
    $produit = produitBoutique('Savon artisanal au moringa', 'SAV-01', 5);

    app(StockRequisitionService::class)->deliver(demandeValidee('boutique', $savon, 10));

    expect($produit->fresh()->stock_quantity)->toBe(15);
});

test('une quantité fractionnaire vers un produit boutique est refusée sans rien déstocker', function () {
    $savon = StockItem::create(['name' => 'Savon', 'reference' => 'SAV-01', 'unit' => 'pièce', 'current_stock' => 50]);
    $produit = produitBoutique('Savon', 'SAV-01', 5);
    $demande = demandeValidee('boutique', $savon, 2.5);

    expect(fn () => app(StockRequisitionService::class)->deliver($demande))
        ->toThrow(RuntimeException::class, 'quantité entière');
    expect((float) $savon->fresh()->current_stock)->toBe(50.0)
        ->and($produit->fresh()->stock_quantity)->toBe(5)
        ->and($demande->fresh()->status)->toBe(StockRequisition::STATUS_APPROVED);
});

// ── Rapport consolidé ───────────────────────────────────────────────────────

test('le rapport consolidé chiffre les achats reçus et les livraisons aux services', function () {
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg']);
    receptionner(bonEnvoye($riz, 10, 100_000), 10);

    // Une réception annulée ne compte pas dans les achats.
    $annulee = receptionner(bonEnvoye($riz, 4, 100_000), 4);
    app(GoodsReceiptService::class)->cancel($annulee, auth()->user());

    app(StockRequisitionService::class)->deliver(demandeValidee('housekeeping', $riz, 3));

    $rapport = app(StockControlService::class)->getExecutiveReport(now()->startOfDay(), now()->endOfDay());

    expect($rapport['total_purchases_received'])->toBe(1_000_000)
        ->and($rapport['total_requisitions_delivered'])->toBe(300_000);
});
