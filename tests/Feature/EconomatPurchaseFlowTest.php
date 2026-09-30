<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStaffUser(string $role = 'econome'): User
{
    $u = User::factory()->create(['role' => $role]);
    test()->actingAs($u);
    return $u;
}

test('un responsable de departement peut creer une demande d achat chiffree', function () {
    $user = createStaffUser('restaurant_chief');

    $item1 = StockItem::create([
        'name' => 'Riz Jasmin 25kg',
        'unit' => 'sac',
        'current_stock' => 2,
        'min_stock' => 5,
        'average_cost' => 1800000, // 18 000 F / sac
        'is_active' => true,
    ]);

    $item2 = StockItem::create([
        'name' => 'Huile de Tournesol 5L',
        'unit' => 'bidon',
        'current_stock' => 3,
        'min_stock' => 10,
        'average_cost' => 600000, // 6 000 F / bidon
        'is_active' => true,
    ]);

    $service = app(PurchaseRequestService::class);
    $request = $service->create([
        'department' => 'cuisine',
        'priority'   => PurchaseRequest::PRIORITY_URGENT,
        'purpose'    => 'Rupture imminente avant banquet de samedi',
        'lines'      => [
            [
                'stock_item_id'        => $item1->id,
                'quantity_requested'   => 10,
                'estimated_unit_price' => 18500, // 18 500 F
                'notes'                => 'Qualité supérieure',
            ],
            [
                'stock_item_id'        => $item2->id,
                'quantity_requested'   => 15,
                // sans prix spécifié, reprend average_cost (6 000 F = 600 000 centimes)
                'notes'                => 'Cartons de 4',
            ],
        ],
    ], $user);

    expect($request)->not->toBeNull()
        ->and($request->number)->toStartWith('DA-' . date('Y') . '-')
        ->and($request->status)->toBe(PurchaseRequest::STATUS_PENDING)
        ->and($request->priority)->toBe(PurchaseRequest::PRIORITY_URGENT)
        ->and($request->department)->toBe('cuisine')
        ->and($request->requested_by)->toBe($user->id)
        ->and($request->lines)->toHaveCount(2);

    // Total estimé : (10 * 18 500 F) + (15 * 6 000 F) = 185 000 + 90 000 = 275 000 F = 27 500 000 centimes
    expect($request->total_estimated_amount)->toBe(27500000);
});

test("le contrôleur de gestion consulte une demande d'achat sans pouvoir l'approuver", function () {
    activerModules(['economat']);

    $demandeur = createStaffUser('restaurant_chief');
    $article = StockItem::create([
        'name' => 'Farine T55 25kg', 'unit' => 'sac', 'current_stock' => 1,
        'average_cost' => 1500000, 'is_active' => true,
    ]);
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'cuisine',
        'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 4]],
    ], $demandeur);

    createStaffUser('controller');

    // Il surveille les achats : il lit la demande, mais n'y voit aucun bouton de décision.
    $this->get(route('economat.purchase_requests.show', $demande))
        ->assertOk()
        ->assertDontSee('Approuver la demande');

    // Et la route refuse, quelle que soit la forme du refus.
    $this->post(route('economat.purchase_requests.approve', $demande));
    $this->post(route('economat.purchase_requests.reject', $demande), ['rejection_reason' => 'Contrôle']);

    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING);
});

test('la direction peut approuver ou refuser une demande d achat', function () {
    $requester = createStaffUser('restaurant_chief');
    $item = StockItem::create([
        'name' => 'Café Robusta 1kg',
        'unit' => 'kg',
        'current_stock' => 5,
        'average_cost' => 500000,
        'is_active' => true,
    ]);

    $service = app(PurchaseRequestService::class);
    $req = $service->create([
        'department' => 'bar',
        'lines' => [
            ['stock_item_id' => $item->id, 'quantity_requested' => 20],
        ],
    ], $requester);

    $manager = createStaffUser('manager');

    // 1. Approbation
    $approved = $service->approve($req, $manager, 'Validé pour commande immédiate');
    expect($approved->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        ->and($approved->reviewed_by)->toBe($manager->id)
        ->and($approved->reviewed_at)->not->toBeNull()
        ->and($approved->review_notes)->toBe('Validé pour commande immédiate');

    // 2. Création d'une 2e demande pour tester le rejet
    $req2 = $service->create([
        'department' => 'bar',
        'lines' => [
            ['stock_item_id' => $item->id, 'quantity_requested' => 50],
        ],
    ], $requester);

    $rejected = $service->reject($req2, $manager, 'Budget mensuel dépassé pour ce rayon');
    expect($rejected->status)->toBe(PurchaseRequest::STATUS_REJECTED)
        ->and($rejected->rejection_reason)->toBe('Budget mensuel dépassé pour ce rayon');
});

test('une demande approuvee peut etre convertie en bon de commande fournisseur', function () {
    $econome = createStaffUser('econome');

    $supplier = Supplier::create([
        'name' => 'Grossiste Boissons Cameroun',
        'email' => 'contact@grossiste-boissons.cm',
        'is_active' => true,
    ]);

    $item = StockItem::create([
        'supplier_id' => $supplier->id,
        'name' => 'Eau Minérale 10L',
        'unit' => 'bonbonne',
        'current_stock' => 4,
        'average_cost' => 150000, // 1 500 F
        'is_active' => true,
    ]);

    $service = app(PurchaseRequestService::class);
    $req = $service->create([
        'department' => 'economat',
        'lines' => [
            ['stock_item_id' => $item->id, 'quantity_requested' => 30],
        ],
    ], $econome);

    $manager = createStaffUser('manager');
    $service->approve($req, $manager);

    // Conversion en bon de commande
    $orders = $service->convertToOrders($req, $econome);

    expect($orders)->toHaveCount(1);
    $order = $orders->first();

    expect($order->status)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($order->supplier_id)->toBe($supplier->id)
        ->and($order->purchase_request_id)->toBe($req->id)
        ->and($order->number)->toStartWith('BC-' . date('Y') . '-')
        ->and($order->lines)->toHaveCount(1)
        ->and((float) $order->lines->first()->quantity_ordered)->toBe(30.0)
        // La demande d'achat passe au statut 'converted'
        ->and($req->fresh()->status)->toBe(PurchaseRequest::STATUS_CONVERTED);
});

test('le demandeur ne décide pas de sa propre demande', function () {
    $manager = createStaffUser('manager');
    $article = StockItem::create([
        'name' => 'Nappes blanches', 'unit' => 'pièce', 'current_stock' => 0,
        'average_cost' => 250000, 'is_active' => true,
    ]);
    $service = app(PurchaseRequestService::class);
    $demande = $service->create([
        'department' => 'direction',
        'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 30]],
    ], $manager);

    // Approuver la dépense qu'on a soi-même demandée, c'est l'autoriser sans contrôle.
    expect(fn () => $service->approve($demande, $manager))->toThrow(RuntimeException::class)
        ->and(fn () => $service->reject($demande, $manager, 'Retrait'))->toThrow(RuntimeException::class)
        ->and($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING);

    // Un autre responsable, lui, décide.
    expect($service->approve($demande, User::factory()->create(['role' => 'manager']))->status)
        ->toBe(PurchaseRequest::STATUS_APPROVED);
});

test("sur sa propre demande, le manager ne voit aucun bouton de décision", function () {
    activerModules(['economat']);

    $manager = createStaffUser('manager');
    $article = StockItem::create([
        'name' => 'Serviettes', 'unit' => 'pièce', 'current_stock' => 0,
        'average_cost' => 150000, 'is_active' => true,
    ]);
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'direction',
        'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 12]],
    ], $manager);

    $this->get(route('economat.purchase_requests.show', $demande))
        ->assertOk()
        ->assertDontSee('Approuver la demande');
});

test("l'économe ne décide plus des demandes d'achat", function () {
    activerModules(['economat']);

    $demandeur = createStaffUser('restaurant_chief');
    $article = StockItem::create([
        'name' => 'Sel fin 1kg', 'unit' => 'paquet', 'current_stock' => 0,
        'average_cost' => 50000, 'is_active' => true,
    ]);
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'cuisine',
        'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 10]],
    ], $demandeur);

    createStaffUser('econome');

    // Il exécute l'achat ; la décision de dépense revient à la direction.
    $this->post(route('economat.purchase_requests.approve', $demande));

    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING);
});

test("le manager valide la dépense mais laisse à l'économe la commande", function () {
    activerModules(['economat']);

    $econome = createStaffUser('econome');
    $fournisseur = Supplier::create(['name' => 'Brasseries du Cameroun', 'is_active' => true]);
    $article = StockItem::create([
        'supplier_id' => $fournisseur->id, 'name' => 'Casier 33 Export', 'unit' => 'casier',
        'current_stock' => 2, 'average_cost' => 900000, 'is_active' => true,
    ]);
    $service = app(PurchaseRequestService::class);
    $demande = $service->create([
        'department' => 'bar',
        'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 12]],
    ], $econome);

    $manager = createStaffUser('manager');
    $service->approve($demande, $manager);

    // La demande approuvée ne lui propose pas de générer la commande…
    $this->get(route('economat.purchase_requests.show', $demande))
        ->assertOk()
        ->assertDontSee('Générer le(s) bon(s) de commande');

    // … et la route la lui refuse : la demande reste approuvée, sans commande.
    $this->post(route('economat.purchase_requests.convert', $demande), ['supplier_id' => $fournisseur->id]);

    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        ->and(PurchaseOrder::where('purchase_request_id', $demande->id)->exists())->toBeFalse();
});

test('la reception de marchandise genere un bon de reception contradictoire et met a jour le stock', function () {
    $econome = createStaffUser('econome');

    $supplier = Supplier::create([
        'name' => 'Boulangerie Moderne',
        'email' => 'commandes@boulangerie.cm',
        'is_active' => true,
    ]);

    $item = StockItem::create([
        'supplier_id' => $supplier->id,
        'name' => 'Pain Baguette Céréales',
        'unit' => 'pièce',
        'current_stock' => 10,
        'average_cost' => 20000, // 200 F / baguette
        'is_active' => true,
    ]);

    // Bon de commande envoyé de 100 baguettes à 250 F (25 000 centimes)
    $order = PurchaseOrder::create([
        'supplier_id'  => $supplier->id,
        'status'       => PurchaseOrder::STATUS_SENT,
        'total_amount' => 2500000,
    ]);

    $line = PurchaseOrderLine::create([
        'purchase_order_id' => $order->id,
        'stock_item_id'     => $item->id,
        'quantity_ordered'  => 100,
        'quantity_received' => 0,
        'unit_price'        => 25000,
    ]);

    // Réception contradictoire avec litige partiel :
    // Livré sur le BL : 90 baguettes
    // Refusé pour avarie/écrasement : 5 baguettes (damaged)
    // Accepté entrant en stock : 85 baguettes
    $receiptService = app(GoodsReceiptService::class);
    $receipt = $receiptService->receive($order, [
        'delivery_note_number' => 'BL-2026-994',
        'received_at'          => now()->toDateTimeString(),
        'notes'                => 'Livraison matin',
        'lines'                => [
            $line->id => [
                'quantity_delivered' => 90,
                'quantity_accepted'  => 85,
                'quantity_rejected'  => 5,
                'rejection_reason'   => 'damaged',
                'notes'              => 'Baguettes écrasées durant transport',
            ],
        ],
    ], $econome);

    // 1. Vérification du Bon de Réception (BR)
    expect($receipt)->not->toBeNull()
        ->and($receipt->number)->toStartWith('BR-' . date('Y') . '-')
        ->and($receipt->status)->toBe(GoodsReceipt::STATUS_RECEIVED)
        ->and($receipt->delivery_note_number)->toBe('BL-2026-994')
        // Montant accepté : 85 baguettes * 250 F = 21 250 F = 2 125 000 centimes
        ->and($receipt->total_amount)->toBe(2125000)
        ->and($receipt->hasRejections())->toBeTrue();

    $receiptLine = $receipt->lines->first();
    expect((float) $receiptLine->quantity_ordered)->toBe(100.0)
        ->and((float) $receiptLine->quantity_delivered)->toBe(90.0)
        ->and((float) $receiptLine->quantity_accepted)->toBe(85.0)
        ->and((float) $receiptLine->quantity_rejected)->toBe(5.0)
        ->and($receiptLine->rejection_reason)->toBe('damaged');

    // 2. Vérification du stock réel dans StockItem : 10 + 85 = 95 baguettes
    expect((float) $item->fresh()->current_stock)->toBe(95.0);

    // 3. Vérification du mouvement de stock avec source_type = goods_receipt
    $movement = StockMovement::where('stock_item_id', $item->id)
        ->where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)
        ->where('source_id', $receipt->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(StockMovement::TYPE_IN)
        ->and((float) $movement->quantity)->toBe(85.0)
        ->and($movement->unit_cost)->toBe(25000);

    // 4. Statut du bon de commande : 85 / 100 reçus -> partially_received
    expect($order->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and((float) $line->fresh()->quantity_received)->toBe(85.0);
});

test('le statut de facturation et le rapprochement comptable fonctionnent', function () {
    $econome = createStaffUser('econome');

    $supplier = Supplier::create([
        'name' => 'Fournisseur Boissons',
        'is_active' => true,
    ]);

    $order = PurchaseOrder::create([
        'supplier_id'  => $supplier->id,
        'status'       => PurchaseOrder::STATUS_RECEIVED,
        'total_amount' => 5000000, // 50 000 F
    ]);

    expect($order->invoicingStatus())->toBe('not_invoiced');

    // Enregistrement d'une première facture partielle de 20 000 F (2 000 000 centimes)
    SupplierInvoice::create([
        'supplier_id'       => $supplier->id,
        'purchase_order_id' => $order->id,
        'number'            => 'FAC-SUPP-001',
        'invoice_date'      => now()->toDateString(),
        'charge_account'    => '601',
        'label'             => 'Acompte livraison boissons',
        'amount_ttc'        => 2000000,
        'amount_ht'         => 1677852,
        'amount_vat'        => 322148,
        'net_payable'       => 2000000,
    ]);

    expect($order->fresh()->invoicingStatus())->toBe('partially_invoiced')
        ->and($order->fresh()->invoicedAmount())->toBe(2000000);

    // Deuxième facture soldant les 30 000 F restants
    SupplierInvoice::create([
        'supplier_id'       => $supplier->id,
        'purchase_order_id' => $order->id,
        'number'            => 'FAC-SUPP-002',
        'invoice_date'      => now()->toDateString(),
        'charge_account'    => '601',
        'label'             => 'Solde livraison boissons',
        'amount_ttc'        => 3000000,
        'amount_ht'         => 2516778,
        'amount_vat'        => 483222,
        'net_payable'       => 3000000,
    ]);

    expect($order->fresh()->invoicingStatus())->toBe('fully_invoiced')
        ->and($order->fresh()->invoicedAmount())->toBe(5000000);
});

test('les routes d impression BC et BR respectent les standards', function () {
    $econome = createStaffUser('econome');

    $supplier = Supplier::create(['name' => 'Grossiste Test']);
    $item = StockItem::create([
        'name' => 'Article Imprimable',
        'unit' => 'kg',
        'average_cost' => 100000,
    ]);

    $order = PurchaseOrder::create([
        'supplier_id'  => $supplier->id,
        'status'       => PurchaseOrder::STATUS_SENT,
        'total_amount' => 1000000,
    ]);

    PurchaseOrderLine::create([
        'purchase_order_id' => $order->id,
        'stock_item_id'     => $item->id,
        'quantity_ordered'  => 10,
        'unit_price'        => 100000,
    ]);

    // 1. Impression Bon de Commande
    $response = test()->get(route('economat.orders.print', $order));
    $response->assertOk()
        ->assertViewIs('economat.orders.print')
        ->assertSee($order->number)
        ->assertSee('Bon de Commande Fournisseur')
        ->assertSee('Article Imprimable');

    // 2. Impression Bon de Réception
    $receipt = GoodsReceipt::create([
        'purchase_order_id'    => $order->id,
        'supplier_id'          => $supplier->id,
        'delivery_note_number' => 'BL-TEST-123',
        'received_at'          => now(),
        'total_amount'         => 1000000,
    ]);

    GoodsReceiptLine::create([
        'goods_receipt_id'       => $receipt->id,
        'purchase_order_line_id' => $order->lines->first()->id,
        'stock_item_id'          => $item->id,
        'quantity_ordered'       => 10,
        'quantity_delivered'     => 10,
        'quantity_accepted'      => 10,
        'quantity_rejected'      => 0,
        'unit_cost'              => 100000,
        'total_cost'             => 1000000,
    ]);

    $response = test()->get(route('economat.receipts.print', $receipt));
    $response->assertOk()
        ->assertViewIs('economat.receipts.print')
        ->assertSee($receipt->number)
        ->assertSee('Bordereau Officiel de Réception')
        ->assertSee('Article Imprimable');
});
