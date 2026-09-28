<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (!function_exists('makeReceiptEconomeUser')) {
    function makeReceiptEconomeUser(string $name = 'Jean-Paul Kamga'): User
    {
        $u = User::factory()->create([
            'name' => $name,
            'role' => 'econome',
        ]);
        test()->actingAs($u);
        return $u;
    }
}

test('l econome valide une livraison dans un bon d entree signe avec integration en stock et valorisation', function () {
    $econome = makeReceiptEconomeUser('Adrien Bertrand Meka');

    $fournisseur = Supplier::create([
        'name'           => 'Société Brassicole du Littoral',
        'code'           => 'FOURN-BRASS-02',
        'contact_person' => 'M. Roger Mbia',
        'phone'          => '+237 699 12 34 56',
        'is_active'      => true,
    ]);

    $boisson = StockItem::create([
        'name'          => 'Casier Guinness 65cl (12 btles)',
        'unit'          => 'casier',
        'current_stock' => 10,
        'min_stock'     => 5,
        'average_cost'  => 800000, // 8 000 F / casier
        'supplier_id'   => $fournisseur->id,
        'is_active'     => true,
    ]);

    // Bon de commande envoyé pour 20 casiers à 8 000 F
    $order = PurchaseOrder::create([
        'number'       => 'BC-2026-VAL01',
        'supplier_id'  => $fournisseur->id,
        'created_by'   => $econome->id,
        'status'       => PurchaseOrder::STATUS_SENT,
        'total_amount' => 16000000, // 160 000 F
    ]);

    $poLine = PurchaseOrderLine::create([
        'purchase_order_id' => $order->id,
        'stock_item_id'     => $boisson->id,
        'quantity_ordered'  => 20,
        'quantity_received' => 0,
        'unit_price'        => 800000, // 8 000 F
    ]);

    // L'économe consulte le formulaire de réception
    $resCreate = test()->get(route('economat.receipts.create', $order));
    $resCreate->assertOk();
    $resCreate->assertSee('Établissement du Bon d\'Entrée en Stock', false);
    $resCreate->assertSee('Société Brassicole du Littoral');
    $resCreate->assertSee('Casier Guinness 65cl (12 btles)');
    $resCreate->assertSee('Adrien'); // Aperçu de la signature cursive

    // Validation de la livraison : 20 casiers livrés, 18 acceptés, 2 refusés (casse/avarie)
    $postData = [
        'delivery_note_number' => 'BL-FOURN-9842',
        'received_at'          => now()->toDateTimeString(),
        'notes'                => 'Livraison matinale, 2 bouteilles cassées constatées au déchargement',
        'lines'                => [
            $poLine->id => [
                'quantity_delivered' => 20,
                'quantity_accepted'  => 18,
                'quantity_rejected'  => 2,
                'rejection_reason'   => 'damaged',
                'notes'              => 'Casse durant transport fournisseur',
            ],
        ],
    ];

    $resStore = test()->post(route('economat.receipts.store', $order), $postData);
    $resStore->assertRedirect();

    // 1. Vérification du Bon d'Entrée (Goods Receipt)
    $receipt = GoodsReceipt::where('purchase_order_id', $order->id)->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->number)->toStartWith('BR-' . date('Y') . '-')
        ->and($receipt->delivery_note_number)->toBe('BL-FOURN-9842')
        ->and($receipt->status)->toBe(GoodsReceipt::STATUS_RECEIVED)
        ->and($receipt->received_by)->toBe($econome->id)
        // Signature automatique de l'économe réceptionnaire
        ->and($receipt->receiver_signature)->toBe('Adrien')
        ->and($receipt->receiverSignature())->toBe('Adrien')
        // Valeur admise : 18 casiers * 8 000 F = 144 000 F = 14 400 000 centimes
        ->and($receipt->total_amount)->toBe(14400000);

    // 2. Vérification des lignes du bon d'entrée
    expect($receipt->lines)->toHaveCount(1);
    $rcLine = $receipt->lines->first();
    expect((float) $rcLine->quantity_ordered)->toBe(20.0)
        ->and((float) $rcLine->quantity_delivered)->toBe(20.0)
        ->and((float) $rcLine->quantity_accepted)->toBe(18.0)
        ->and((float) $rcLine->quantity_rejected)->toBe(2.0)
        ->and($rcLine->rejection_reason)->toBe('damaged');

    // 3. Vérification de l'entrée physique en stock
    $boisson->refresh();
    // Stock initial (10) + Accepté (18) = 28
    expect((float) $boisson->current_stock)->toBe(28.0);

    // Mouvement de stock tracé
    $mvt = StockMovement::where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)
        ->where('source_id', $receipt->id)
        ->first();
    expect($mvt)->not->toBeNull()
        ->and($mvt->type)->toBe('in')
        ->and((float) $mvt->quantity)->toBe(18.0)
        ->and($mvt->unit_cost)->toBe(800000);

    // 4. Vérification de la mise à jour du Bon de Commande
    $poLine->refresh();
    expect((float) $poLine->quantity_received)->toBe(18.0);
    $order->refresh();
    expect($order->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
});

test('la liste des bons d entree offre 4 KPI et est filtrable par fournisseur date litige et recherche', function () {
    $econome = makeReceiptEconomeUser();

    $f1 = Supplier::create(['name' => 'Boulangerie Industrielle', 'is_active' => true]);
    $f2 = Supplier::create(['name' => 'Maraîcher Bio', 'is_active' => true]);

    $po1 = PurchaseOrder::create(['number' => 'BC-2026-F1', 'supplier_id' => $f1->id, 'created_by' => $econome->id]);
    $po2 = PurchaseOrder::create(['number' => 'BC-2026-F2', 'supplier_id' => $f2->id, 'created_by' => $econome->id]);

    $item1 = StockItem::create(['name' => 'Pain Baguette', 'unit' => 'piece', 'current_stock' => 10, 'supplier_id' => $f1->id]);
    $item2 = StockItem::create(['name' => 'Tomates Fraîches', 'unit' => 'kg', 'current_stock' => 5, 'supplier_id' => $f2->id]);

    $poLine1 = PurchaseOrderLine::create([
        'purchase_order_id' => $po1->id,
        'stock_item_id'     => $item1->id,
        'quantity_ordered'  => 100,
        'unit_price'        => 50000,
    ]);

    $poLine2 = PurchaseOrderLine::create([
        'purchase_order_id' => $po2->id,
        'stock_item_id'     => $item2->id,
        'quantity_ordered'  => 20,
        'unit_price'        => 500000,
    ]);

    // Bon d'entrée 1 : Conforme, il y a 3 jours, fournisseur 1
    $br1 = GoodsReceipt::create([
        'number'               => 'BR-2026-TST01',
        'purchase_order_id'    => $po1->id,
        'supplier_id'          => $f1->id,
        'delivery_note_number' => 'BL-BOUL-01',
        'received_by'          => $econome->id,
        'status'               => GoodsReceipt::STATUS_RECEIVED,
        'total_amount'         => 5000000, // 50 000 F
    ]);
    $br1->created_at = now()->subDays(3);
    $br1->received_at = now()->subDays(3);
    $br1->save();
    GoodsReceiptLine::create([
        'goods_receipt_id'       => $br1->id,
        'purchase_order_line_id' => $poLine1->id,
        'stock_item_id'          => $item1->id,
        'quantity_ordered'       => 100,
        'quantity_delivered'     => 100,
        'quantity_accepted'      => 100,
        'quantity_rejected'      => 0,
        'unit_cost'              => 50000,
        'total_cost'             => 5000000,
    ]);

    // Bon d'entrée 2 : Avec litige (avarie), aujourd'hui, fournisseur 2
    $br2 = GoodsReceipt::create([
        'number'               => 'BR-2026-TST02',
        'purchase_order_id'    => $po2->id,
        'supplier_id'          => $f2->id,
        'delivery_note_number' => 'BL-BIO-99',
        'received_by'          => $econome->id,
        'status'               => GoodsReceipt::STATUS_RECEIVED,
        'total_amount'         => 8000000, // 80 000 F
    ]);
    $br2->created_at = now();
    $br2->received_at = now();
    $br2->save();
    GoodsReceiptLine::create([
        'goods_receipt_id'       => $br2->id,
        'purchase_order_line_id' => $poLine2->id,
        'stock_item_id'          => $item2->id,
        'quantity_ordered'       => 20,
        'quantity_delivered'     => 20,
        'quantity_accepted'      => 16,
        'quantity_rejected'      => 4,
        'rejection_reason'       => 'spoilage',
        'unit_cost'              => 500000,
        'total_cost'             => 8000000,
    ]);

    // 1. Accès global sans filtre
    $resGlobal = test()->get(route('economat.receipts.index'));
    $resGlobal->assertOk();
    $resGlobal->assertSee('Bons d\'entrée en stock');
    $resGlobal->assertSee('Réceptions');
    $resGlobal->assertSee('BR-2026-TST01');
    $resGlobal->assertSee('BR-2026-TST02');
    $resGlobal->assertSee('Boulangerie Industrielle');
    $resGlobal->assertSee('Maraîcher Bio');

    // 2. Filtrage par Fournisseur : Fournisseur 1 uniquement
    $resF1 = test()->get(route('economat.receipts.index', ['supplier_id' => $f1->id]));
    $resF1->assertOk();
    $resF1->assertSee('BR-2026-TST01');
    $resF1->assertDontSee('BR-2026-TST02');

    // 3. Filtrage par Litige : avec litige
    $resLitige = test()->get(route('economat.receipts.index', ['litige' => 'avec']));
    $resLitige->assertOk();
    $resLitige->assertSee('BR-2026-TST02');
    $resLitige->assertDontSee('BR-2026-TST01');

    // 4. Filtrage par Date : aujourd'hui seulement
    $resDate = test()->get(route('economat.receipts.index', [
        'du' => now()->toDateString(),
        'au' => now()->toDateString(),
    ]));
    $resDate->assertOk();
    $resDate->assertSee('BR-2026-TST02');
    $resDate->assertDontSee('BR-2026-TST01');

    // 5. Recherche par numéro de BL
    $resSearch = test()->get(route('economat.receipts.index', ['recherche' => 'BL-BOUL-01']));
    $resSearch->assertOk();
    $resSearch->assertSee('BR-2026-TST01');
    $resSearch->assertDontSee('BR-2026-TST02');
});

test('l economat peut exporter et imprimer la liste des bons d entree sous forme de tableau selon une periode definie', function () {
    $econome = makeReceiptEconomeUser();

    $f = Supplier::create(['name' => 'Grossiste Laitier SARL', 'is_active' => true]);
    $po = PurchaseOrder::create(['number' => 'BC-2026-EXP', 'supplier_id' => $f->id, 'created_by' => $econome->id]);
    $item = StockItem::create(['name' => 'Lait Entier 1L', 'unit' => 'brique', 'supplier_id' => $f->id]);

    $poLineExp = PurchaseOrderLine::create([
        'purchase_order_id' => $po->id,
        'stock_item_id'     => $item->id,
        'quantity_ordered'  => 100,
        'unit_price'        => 95000,
    ]);

    $br = GoodsReceipt::create([
        'number'               => 'BR-2026-EXPDOC',
        'purchase_order_id'    => $po->id,
        'supplier_id'          => $f->id,
        'delivery_note_number' => 'BL-LAIT-55',
        'received_by'          => $econome->id,
        'status'               => GoodsReceipt::STATUS_RECEIVED,
        'total_amount'         => 9500000, // 95 000 F
    ]);
    GoodsReceiptLine::create([
        'goods_receipt_id'       => $br->id,
        'purchase_order_line_id' => $poLineExp->id,
        'stock_item_id'          => $item->id,
        'quantity_ordered'       => 100,
        'quantity_delivered'     => 100,
        'quantity_accepted'      => 100,
        'quantity_rejected'      => 0,
        'unit_cost'              => 95000,
        'total_cost'             => 9500000,
    ]);

    // Export en vue d'impression (format = impression)
    $resExport = test()->get(route('economat.receipts.export', [
        'format' => 'impression',
        'du'     => now()->subDays(5)->toDateString(),
        'au'     => now()->toDateString(),
    ]));

    $resExport->assertOk();
    $resExport->assertSee('BR-2026-EXPDOC');
    $resExport->assertSee('Grossiste Laitier SARL');
    $resExport->assertSee('95 000');
});

test('un bon d entree individuel peut etre consulte en detail et imprime au format officiel avec police Qwigley et signature', function () {
    $econome = makeReceiptEconomeUser('Rodrigue Tchouassi');

    $fournisseur = Supplier::create([
        'name'           => 'Poissonnerie de l\'Océan',
        'code'           => 'FOURN-POISSON-01',
        'contact_person' => 'M. Ondoua Simon',
        'phone'          => '+237 670 11 22 33',
        'is_active'      => true,
    ]);

    $po = PurchaseOrder::create([
        'number'       => 'BC-2026-SEA01',
        'supplier_id'  => $fournisseur->id,
        'created_by'   => $econome->id,
        'status'       => PurchaseOrder::STATUS_SENT,
        'total_amount' => 15000000,
    ]);

    $cat = StockCategory::create(['name' => 'Produits de la mer']);
    $item = StockItem::create([
        'name'              => 'Bar Frais de Mer',
        'code'              => 'POIS-BAR-01',
        'unit'              => 'kg',
        'current_stock'     => 5,
        'min_stock'         => 10,
        'average_cost'      => 300000, // 3 000 F
        'supplier_id'       => $fournisseur->id,
        'stock_category_id' => $cat->id,
        'is_active'         => true,
    ]);

    $poLineSea = PurchaseOrderLine::create([
        'purchase_order_id' => $po->id,
        'stock_item_id'     => $item->id,
        'quantity_ordered'  => 50,
        'unit_price'        => 300000,
    ]);

    $receipt = GoodsReceipt::create([
        'number'               => 'BR-2026-OF001',
        'purchase_order_id'    => $po->id,
        'supplier_id'          => $fournisseur->id,
        'delivery_note_number' => 'BL-OCEAN-77',
        'received_by'          => $econome->id,
        'receiver_signature'   => 'Rodrigue',
        'status'               => GoodsReceipt::STATUS_RECEIVED,
        'total_amount'         => 15000000, // 150 000 F
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_id'       => $receipt->id,
        'purchase_order_line_id' => $poLineSea->id,
        'stock_item_id'          => $item->id,
        'quantity_ordered'       => 50,
        'quantity_delivered'     => 50,
        'quantity_accepted'      => 50,
        'quantity_rejected'      => 0,
        'unit_cost'              => 300000,
        'total_cost'             => 15000000,
    ]);

    // 1. Consultation des détails
    $resShow = test()->get(route('economat.receipts.show', $receipt));
    $resShow->assertOk();
    $resShow->assertSee('BR-2026-OF001');
    $resShow->assertSee('Poissonnerie de l\'Océan');
    $resShow->assertSee('Signature Économe');
    $resShow->assertSee('Rodrigue');
    $resShow->assertSee('Qwigley');
    $resShow->assertSee('Bar Frais de Mer');
    $resShow->assertSee('150 000');

    // 2. Impression officielle A4 du Bon d'Entrée
    $resPrint = test()->get(route('economat.receipts.print', $receipt));
    $resPrint->assertOk();
    $resPrint->assertSee('Bon d\'Entrée en Stock', false);
    $resPrint->assertSee('Bordereau de Réception');
    $resPrint->assertSee('BR-2026-OF001');
    $resPrint->assertSee('Poissonnerie de l\'Océan');
    $resPrint->assertSee('Rodrigue');
    $resPrint->assertSee('Qwigley');
    $resPrint->assertSee('L\'Économe / Magasinier Réceptionnaire', false);
});
