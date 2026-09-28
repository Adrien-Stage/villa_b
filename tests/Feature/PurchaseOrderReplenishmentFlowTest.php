<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (!function_exists('makeReplenishmentEconomeUser')) {
    function makeReplenishmentEconomeUser(string $name = 'Jean-Paul Kamga'): User
    {
        $u = User::factory()->create([
            'name' => $name,
            'role' => 'econome',
        ]);
        test()->actingAs($u);
        return $u;
    }
}

test('un fournisseur prealablement cree dans les parametres fournit plusieurs produits avec prix et unites connus', function () {
    $econome = makeReplenishmentEconomeUser();

    // 1. Création du fournisseur dans les paramètres
    $fournisseur = Supplier::create([
        'name'           => 'Brasseries & Boissons du Cameroun',
        'code'           => 'FOURN-BRASS-01',
        'contact_person' => 'M. Talla Roger',
        'email'          => 'commandes@brasseries-cam.cm',
        'phone'          => '+237 699 00 11 22',
        'address'        => 'Zone Industrielle Bassa, Douala',
        'is_active'      => true,
    ]);

    $catBoissons = StockCategory::create(['name' => 'Boissons & Rafraîchissements']);

    // 2. Produits délivrés par ce fournisseur avec unité et prix connus
    $bierre1 = StockItem::create([
        'name'              => 'Casier Castel Beer 65cl (12 btles)',
        'code'              => 'BOIS-CAS-001',
        'unit'              => 'casier',
        'current_stock'     => 15,
        'min_stock'         => 5,
        'average_cost'      => 750000, // 7 500 F
        'supplier_id'       => $fournisseur->id,
        'stock_category_id' => $catBoissons->id,
        'is_active'         => true,
    ]);

    $jus1 = StockItem::create([
        'name'              => 'Carton Top Ananas 1L (6 btles)',
        'code'              => 'BOIS-TOP-002',
        'unit'              => 'carton',
        'current_stock'     => 8,
        'min_stock'         => 3,
        'average_cost'      => 450000, // 4 500 F
        'supplier_id'       => $fournisseur->id,
        'stock_category_id' => $catBoissons->id,
        'is_active'         => true,
    ]);

    // Un autre produit d'un autre fournisseur
    $autreFournisseur = Supplier::create([
        'name'      => 'Papeterie Centrale',
        'code'      => 'FOURN-PAP-02',
        'is_active' => true,
    ]);
    $papier = StockItem::create([
        'name'         => 'Rame Papier A4',
        'unit'         => 'rame',
        'current_stock' => 20,
        'supplier_id'  => $autreFournisseur->id,
        'is_active'    => true,
    ]);

    // Vérification de la relation fournisseur -> produits
    $produitsFournisseur = StockItem::where('supplier_id', $fournisseur->id)->get();
    expect($produitsFournisseur)->toHaveCount(2)
        ->and($produitsFournisseur->pluck('id'))->toContain($bierre1->id, $jus1->id)
        ->and($produitsFournisseur->pluck('id'))->not->toContain($papier->id);

    // Vérification du formulaire create avec le fournisseur sélectionné
    $resCreate = test()->get(route('economat.orders.create', ['supplier_id' => $fournisseur->id]));
    $resCreate->assertOk();
    $resCreate->assertSee('Brasseries &amp; Boissons du Cameroun', false);
    $resCreate->assertSee('Casier Castel Beer 65cl (12 btles)');
    $resCreate->assertSee('Carton Top Ananas 1L (6 btles)');
});

test('un bon de commande est lie a strictement un seul fournisseur avec signature automatique Qwigley', function () {
    $econome = makeReplenishmentEconomeUser('Adrien Bertrand Meka');

    $fournisseur = Supplier::create([
        'name'      => 'Société Alimentaire Camerounaise',
        'code'      => 'FOURN-SAC-03',
        'email'     => 'vente@sac-distrib.cm',
        'is_active' => true,
    ]);

    $item1 = StockItem::create([
        'name'          => 'Huile de Palme Raffinée 20L',
        'unit'          => 'bidon',
        'current_stock' => 4,
        'min_stock'     => 10,
        'average_cost'  => 1600000, // 16 000 F
        'supplier_id'   => $fournisseur->id,
        'is_active'     => true,
    ]);

    $item2 = StockItem::create([
        'name'          => 'Sac de Sel Raffiné 25kg',
        'unit'          => 'sac',
        'current_stock' => 2,
        'min_stock'     => 5,
        'average_cost'  => 650000, // 6 500 F
        'supplier_id'   => $fournisseur->id,
        'is_active'     => true,
    ]);

    // Émission du bon de commande pour ce fournisseur unique
    $postData = [
        'supplier_id' => $fournisseur->id,
        'expected_at' => now()->addDays(3)->toDateString(),
        'notes'       => 'Livraison urgente économat matinée',
        'lines'       => [
            [
                'stock_item_id' => $item1->id,
                'quantity'      => 5,
                'unit_price'    => 16000, // 16 000 F / bidon
            ],
            [
                'stock_item_id' => $item2->id,
                'quantity'      => 4,
                'unit_price'    => 6500, // 6 500 F / sac
            ],
        ],
    ];

    $response = test()->post(route('economat.orders.store'), $postData);
    $response->assertRedirect();

    $order = PurchaseOrder::where('supplier_id', $fournisseur->id)->latest()->first();
    expect($order)->not->toBeNull()
        ->and($order->supplier_id)->toBe($fournisseur->id)
        ->and($order->number)->toStartWith('BC-' . date('Y') . '-')
        ->and($order->status)->toBe('draft')
        ->and($order->lines)->toHaveCount(2)
        // Vérification de la signature automatique extraite du profil utilisateur (un des noms de l'utilisateur)
        ->and($order->issuer_signature)->toBe('Adrien')
        ->and($order->issuerSignature())->toBe('Adrien')
        // Total calculé : (5 * 16 000) + (4 * 6 500) = 80 000 + 26 000 = 106 000 F = 10 600 000 centimes
        ->and($order->total_amount)->toBe(10600000);
});

test('la validation echoue si le fournisseur est manquant ou invalide', function () {
    $econome = makeReplenishmentEconomeUser();

    $item = StockItem::create([
        'name'          => 'Papier toilette pro',
        'unit'          => 'paquet',
        'current_stock' => 10,
        'is_active'     => true,
    ]);

    // Tentative sans fournisseur
    $response = test()->post(route('economat.orders.store'), [
        'supplier_id' => null,
        'lines' => [
            ['stock_item_id' => $item->id, 'quantity' => 10, 'unit_price' => 500],
        ],
    ]);

    $response->assertSessionHasErrors('supplier_id');
    expect(PurchaseOrder::count())->toBe(0);
});

test('les bons de commandes sont filtrables par fournisseur statut date et recherche avec KPI clairs', function () {
    $econome = makeReplenishmentEconomeUser();

    $f1 = Supplier::create(['name' => 'Boulangerie Moderne', 'code' => 'FOURN-BOUL-01', 'is_active' => true]);
    $f2 = Supplier::create(['name' => 'Grossiste Boissons SARL', 'code' => 'FOURN-BOIS-02', 'is_active' => true]);

    $item1 = StockItem::create(['name' => 'Farine de Blé 50kg', 'unit' => 'sac', 'current_stock' => 5, 'supplier_id' => $f1->id]);
    $item2 = StockItem::create(['name' => 'Eau Minérale 1.5L', 'unit' => 'pack', 'current_stock' => 10, 'supplier_id' => $f2->id]);

    // Bon 1 : Fournisseur 1, il y a 3 jours, statut 'draft'
    $bc1 = PurchaseOrder::create([
        'number'       => 'BC-2026-TEST01',
        'supplier_id'  => $f1->id,
        'created_by'   => $econome->id,
        'status'       => 'draft',
        'total_amount' => 5000000, // 50 000 F
    ]);
    $bc1->created_at = now()->subDays(3);
    $bc1->save();
    PurchaseOrderLine::create([
        'purchase_order_id' => $bc1->id,
        'stock_item_id'     => $item1->id,
        'quantity_ordered'  => 2,
        'unit_price'        => 2500000,
    ]);

    // Bon 2 : Fournisseur 2, aujourd'hui, statut 'sent'
    $bc2 = PurchaseOrder::create([
        'number'       => 'BC-2026-TEST02',
        'supplier_id'  => $f2->id,
        'created_by'   => $econome->id,
        'status'       => 'sent',
        'total_amount' => 8000000, // 80 000 F
    ]);
    $bc2->created_at = now();
    $bc2->save();
    PurchaseOrderLine::create([
        'purchase_order_id' => $bc2->id,
        'stock_item_id'     => $item2->id,
        'quantity_ordered'  => 4,
        'unit_price'        => 2000000,
    ]);

    // 1. Consultation globale : les 2 bons sont visibles
    $resGlobal = test()->get(route('economat.orders.index'));
    $resGlobal->assertOk();
    $resGlobal->assertSee('Bons de commande fournisseurs');
    $resGlobal->assertSee('BC-2026-TEST01');
    $resGlobal->assertSee('BC-2026-TEST02');
    $resGlobal->assertSee('Boulangerie Moderne');
    $resGlobal->assertSee('Grossiste Boissons SARL');

    // 2. Filtrage par Fournisseur : Fournisseur 1 uniquement
    $resF1 = test()->get(route('economat.orders.index', ['supplier_id' => $f1->id]));
    $resF1->assertOk();
    $resF1->assertSee('BC-2026-TEST01');
    $resF1->assertDontSee('BC-2026-TEST02');

    // 3. Filtrage par Statut : 'sent'
    $resStatut = test()->get(route('economat.orders.index', ['statut' => 'sent']));
    $resStatut->assertOk();
    $resStatut->assertSee('BC-2026-TEST02');
    $resStatut->assertDontSee('BC-2026-TEST01');

    // 4. Filtrage par Période : aujourd'hui seulement
    $resDate = test()->get(route('economat.orders.index', [
        'du' => now()->toDateString(),
        'au' => now()->toDateString(),
    ]));
    $resDate->assertOk();
    $resDate->assertSee('BC-2026-TEST02');
    $resDate->assertDontSee('BC-2026-TEST01');

    // 5. Filtrage par Recherche textuelle (numéro ou nom)
    $resSearch = test()->get(route('economat.orders.index', ['recherche' => 'TEST01']));
    $resSearch->assertOk();
    $resSearch->assertSee('BC-2026-TEST01');
    $resSearch->assertDontSee('BC-2026-TEST02');
});

test('l economat peut imprimer ou exporter la liste des bons de commande sous forme de tableau selon une periode definie', function () {
    $econome = makeReplenishmentEconomeUser();

    $f = Supplier::create(['name' => 'Fournisseur Fruits Frais', 'is_active' => true]);
    $item = StockItem::create(['name' => 'Ananas Victoria', 'unit' => 'kg', 'supplier_id' => $f->id]);

    $bc = PurchaseOrder::create([
        'number'       => 'BC-2026-EXP01',
        'supplier_id'  => $f->id,
        'created_by'   => $econome->id,
        'status'       => 'draft',
        'total_amount' => 12500000, // 125 000 F
    ]);
    PurchaseOrderLine::create([
        'purchase_order_id' => $bc->id,
        'stock_item_id'     => $item->id,
        'quantity_ordered'  => 50,
        'unit_price'        => 250000,
    ]);

    // Export en vue d'impression (format = impression)
    $resPrintList = test()->get(route('economat.orders.export', [
        'format' => 'impression',
        'du'     => now()->subDays(7)->toDateString(),
        'au'     => now()->toDateString(),
    ]));

    $resPrintList->assertOk();
    $resPrintList->assertSee('BC-2026-EXP01');
    $resPrintList->assertSee('Fournisseur Fruits Frais');
    $resPrintList->assertSee('125 000');
});

test('un bon individuel peut etre consulte en detail et imprime au format officiel avec police Qwigley et signature', function () {
    $econome = makeReplenishmentEconomeUser('Rodrigue Tchouassi');

    $fournisseur = Supplier::create([
        'name'           => 'Société Industrielle des Viandes',
        'code'           => 'FOURN-VIANDE-09',
        'contact_person' => 'Mme. Essomba Christine',
        'email'          => 'contact@siv-viandes.cm',
        'phone'          => '+237 677 88 99 00',
        'address'        => 'Abattoir Municipal, Yaoundé',
        'is_active'      => true,
    ]);

    $boeuf = StockItem::create([
        'name'          => 'Filet de Bœuf Local 1er Choix',
        'code'          => 'BOUCH-BOEUF-01',
        'unit'          => 'kg',
        'current_stock' => 10,
        'min_stock'     => 15,
        'average_cost'  => 400000, // 4 000 F / kg
        'supplier_id'   => $fournisseur->id,
        'is_active'     => true,
    ]);

    $order = PurchaseOrder::create([
        'number'           => 'BC-2026-OF001',
        'supplier_id'      => $fournisseur->id,
        'created_by'       => $econome->id,
        'issuer_signature' => 'Rodrigue Tchouassi',
        'status'           => 'draft',
        'total_amount'     => 8000000, // 80 000 F
    ]);

    PurchaseOrderLine::create([
        'purchase_order_id' => $order->id,
        'stock_item_id'     => $boeuf->id,
        'quantity_ordered'  => 20,
        'unit_price'        => 400000,
    ]);

    // 1. Consultation de la vue détail
    $resShow = test()->get(route('economat.orders.show', $order));
    $resShow->assertOk();
    $resShow->assertSee('BC-2026-OF001');
    $resShow->assertSee('Société Industrielle des Viandes');
    $resShow->assertSee('Bon mono-fournisseur');
    $resShow->assertSee('Signature Émetteur');
    $resShow->assertSee('Rodrigue Tchouassi');
    $resShow->assertSee('Qwigley');
    $resShow->assertSee('Filet de Bœuf Local 1er Choix');
    $resShow->assertSee('80 000');

    // 2. Impression officielle du bon de commande (format PDF / impression directe)
    $resPrint = test()->get(route('economat.orders.print', $order));
    $resPrint->assertOk();
    $resPrint->assertSee('Bon de Commande Fournisseur');
    $resPrint->assertSee('BC-2026-OF001');
    $resPrint->assertSee('Société Industrielle des Viandes');
    $resPrint->assertSee('Rodrigue Tchouassi');
    $resPrint->assertSee('Qwigley');
    $resPrint->assertSee('L\'Économe / Responsable des Achats', false);
});
