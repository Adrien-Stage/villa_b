<?php

use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\User;
use App\Services\StockRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUserForDept(string $role): User
{
    $u = User::factory()->create(['role' => $role]);
    test()->actingAs($u);
    return $u;
}

test('tous les services (hebergement, housekeeping, restaurant, boutique, comptabilite) peuvent emettre un bon de requisition', function () {
    $catBureau = StockCategory::create(['name' => 'Fournitures de bureau']);
    $catNettoyage = StockCategory::create(['name' => 'Produits d\'entretien']);
    $catFood = StockCategory::create(['name' => 'Épicerie & Boissons']);

    $paper = StockItem::create([
        'name'              => 'Ramettes Papier A4 80g',
        'unit'              => 'ramette',
        'current_stock'     => 50,
        'min_stock'         => 10,
        'average_cost'      => 350000, // 3 500 F
        'stock_category_id' => $catBureau->id,
        'is_active'         => true,
    ]);

    $javel = StockItem::create([
        'name'              => 'Eau de Javel 5L',
        'unit'              => 'bidon',
        'current_stock'     => 30,
        'min_stock'         => 5,
        'average_cost'      => 200000, // 2 000 F
        'stock_category_id' => $catNettoyage->id,
        'is_active'         => true,
    ]);

    // 1. Comptabilité émet un bon pour fournitures de bureau
    $comptable = createUserForDept('accountant');
    $resCompta = test()->post(route('economat.requisitions.store'), [
        'department' => 'comptabilite',
        'purpose'    => 'Réapprovisionnement bureau comptabilité clôture annuelle',
        'lines'      => [
            ['stock_item_id' => $paper->id, 'quantity' => 5],
        ],
    ]);
    $resCompta->assertRedirect();
    $bonCompta = StockRequisition::where('department', 'comptabilite')->first();
    expect($bonCompta)->not->toBeNull()
        ->and($bonCompta->department)->toBe('comptabilite')
        ->and($bonCompta->departmentLabel())->toBe('Comptabilité / Finances')
        // Le comptable n'est pas chef : son RAF vise avant l'économat.
        ->and($bonCompta->status)->toBe(StockRequisition::STATUS_AWAITING_ENDORSEMENT)
        ->and($bonCompta->lines)->toHaveCount(1);

    // 2. Hébergement / Réception émet un bon
    $reception = createUserForDept('reception');
    test()->post(route('economat.requisitions.store'), [
        'department' => 'hebergement',
        'purpose'    => 'Fournitures pour le desk d\'accueil',
        'lines'      => [
            ['stock_item_id' => $paper->id, 'quantity' => 2],
        ],
    ])->assertRedirect();
    expect(StockRequisition::where('department', 'hebergement')->count())->toBe(1);

    // 3. Housekeeping émet un bon
    $hk = createUserForDept('housekeeping_leader');
    test()->post(route('economat.requisitions.store'), [
        'department' => 'housekeeping',
        'purpose'    => 'Nettoyage des chambres étages 1 et 2',
        'lines'      => [
            ['stock_item_id' => $javel->id, 'quantity' => 6],
        ],
    ])->assertRedirect();
    expect(StockRequisition::where('department', 'housekeeping')->count())->toBe(1);

    // 4. Restauration émet un bon
    $chef = createUserForDept('restaurant_chief');
    test()->post(route('economat.requisitions.store'), [
        'department' => 'restaurant',
        'purpose'    => 'Besoins cuisine pour le service du soir',
        'lines'      => [
            ['stock_item_id' => $javel->id, 'quantity' => 2],
        ],
    ])->assertRedirect();
    expect(StockRequisition::where('department', 'restaurant')->count())->toBe(1);

    // 5. Boutique émet un bon
    $shop = createUserForDept('shop_manager');
    test()->post(route('economat.requisitions.store'), [
        'department' => 'boutique',
        'purpose'    => 'Réassort présentoir boutique',
        'lines'      => [
            ['stock_item_id' => $paper->id, 'quantity' => 1],
        ],
    ])->assertRedirect();
    expect(StockRequisition::where('department', 'boutique')->count())->toBe(1);

    // Total = 5 bons émis
    expect(StockRequisition::count())->toBe(5);
});

test('l economat peut consulter tous les bons emis et filtrer par service et par date', function () {
    $econome = createUserForDept('econome');

    $item = StockItem::create([
        'name'          => 'Stylo Bille Bleu',
        'unit'          => 'boite',
        'current_stock' => 100,
        'min_stock'     => 10,
        'average_cost'  => 150000,
        'is_active'     => true,
    ]);

    // Bon comptabilité il y a 2 jours
    $r1 = StockRequisition::create([
        'department'   => 'comptabilite',
        'purpose'      => 'Facturation clients',
        'status'       => StockRequisition::STATUS_PENDING,
        'requested_by' => $econome->id,
    ]);
    $r1->created_at = now()->subDays(2);
    $r1->save();
    StockRequisitionLine::create(['stock_requisition_id' => $r1->id, 'stock_item_id' => $item->id, 'quantity_requested' => 3]);

    // Bon housekeeping aujourd'hui
    $r2 = StockRequisition::create([
        'department'   => 'housekeeping',
        'purpose'      => 'Fournitures gouvernante',
        'status'       => StockRequisition::STATUS_APPROVED,
        'requested_by' => $econome->id,
        'created_at'   => now(),
    ]);
    StockRequisitionLine::create(['stock_requisition_id' => $r2->id, 'stock_item_id' => $item->id, 'quantity_requested' => 1]);

    // 1. Accès global sans filtre
    $res = test()->get(route('economat.requisitions.index'));
    $res->assertOk();
    $res->assertSee('Bons de réquisition &amp; Demandes des services', false);
    $res->assertSee($r1->number);
    $res->assertSee($r2->number);

    // 2. Filtrage par service : comptabilité
    $resCompta = test()->get(route('economat.requisitions.index', ['service' => 'comptabilite']));
    $resCompta->assertOk();
    $resCompta->assertSee($r1->number);
    $resCompta->assertDontSee($r2->number);

    // 3. Filtrage par date : aujourd'hui seulement
    $resDate = test()->get(route('economat.requisitions.index', [
        'du' => now()->toDateString(),
        'au' => now()->toDateString(),
    ]));
    $resDate->assertOk();
    $resDate->assertSee($r2->number);
    $resDate->assertDontSee($r1->number);
});

test('un bon individuel peut etre consulte en detail et imprime au format officiel', function () {
    $econome = createUserForDept('econome');

    $cat = StockCategory::create(['name' => 'Bureautique']);
    $item = StockItem::create([
        'name'              => 'Classeurs à levier A4',
        'unit'              => 'piece',
        'current_stock'     => 40,
        'min_stock'         => 5,
        'average_cost'      => 120000, // 1 200 F
        'stock_category_id' => $cat->id,
        'is_active'         => true,
    ]);

    $requisition = StockRequisition::create([
        'department'   => 'comptabilite',
        'purpose'      => 'Archivage des pièces comptables du trimestre',
        'status'       => StockRequisition::STATUS_APPROVED,
        'requested_by' => $econome->id,
        'reviewed_by'  => $econome->id,
        'reviewed_at'  => now(),
    ]);

    StockRequisitionLine::create([
        'stock_requisition_id' => $requisition->id,
        'stock_item_id'        => $item->id,
        'quantity_requested'   => 10,
        'quantity_issued'      => 0,
    ]);

    // 1. Consultation des détails
    $resShow = test()->get(route('economat.requisitions.show', $requisition));
    $resShow->assertOk();
    $resShow->assertSee($requisition->number);
    $resShow->assertSee('Classeurs à levier A4');
    $resShow->assertSee('Comptabilité / Finances');
    $resShow->assertSee('Archivage des pièces comptables');
    $resShow->assertSee('Imprimer le bon');

    // 2. Impression officielle du bon
    $resPrint = test()->get(route('economat.requisitions.print', $requisition));
    $resPrint->assertOk();
    $resPrint->assertSee('BON DE RÉQUISITION INTERNE');
    $resPrint->assertSee($requisition->number);
    $resPrint->assertSee('Classeurs à levier A4');
    $resPrint->assertSee('Bureautique');
    $resPrint->assertSee('Le Demandeur');
    $resPrint->assertSee('L\'Économe / Magasinier', false);
    $resPrint->assertSee('Contrôle de Gestion / Direction');
});

test('la liste des bons peut etre exportee et imprimee selon une periode definie', function () {
    $econome = createUserForDept('econome');

    $item = StockItem::create([
        'name'          => 'Ampoules LED 12W',
        'unit'          => 'piece',
        'current_stock' => 50,
        'min_stock'     => 10,
        'average_cost'  => 180000,
        'is_active'     => true,
    ]);

    $req = StockRequisition::create([
        'department'   => 'hebergement',
        'purpose'      => 'Remplacement éclairage couloirs',
        'status'       => StockRequisition::STATUS_PENDING,
        'requested_by' => $econome->id,
    ]);
    StockRequisitionLine::create(['stock_requisition_id' => $req->id, 'stock_item_id' => $item->id, 'quantity_requested' => 8]);

    // Format impression navigateur
    $resImpression = test()->get(route('economat.requisitions.export', [
        'format'  => 'impression',
        'service' => 'hebergement',
        'du'      => now()->subDays(5)->toDateString(),
        'au'      => now()->toDateString(),
    ]));
    $resImpression->assertOk();
    $resImpression->assertSee('Demandes à l\'économat');
    $resImpression->assertSee($req->number);

    // Format Excel
    $resExcel = test()->get(route('economat.requisitions.export', [
        'format' => 'excel',
    ]));
    $resExcel->assertOk();
});

test('la livraison d un bon de requisition destocke correctement et trace les quantites servies', function () {
    $econome = createUserForDept('econome');

    $item = StockItem::create([
        'name'          => 'Papier Toilette Professionnel',
        'unit'          => 'rouleau',
        'current_stock' => 100,
        'min_stock'     => 20,
        'average_cost'  => 50000, // 500 F
        'is_active'     => true,
    ]);

    $req = StockRequisition::create([
        'department'   => 'housekeeping',
        'purpose'      => 'Dotation étages',
        'status'       => StockRequisition::STATUS_APPROVED,
        'requested_by' => $econome->id,
    ]);
    $line = StockRequisitionLine::create([
        'stock_requisition_id' => $req->id,
        'stock_item_id'        => $item->id,
        'quantity_requested'   => 20,
        'quantity_issued'      => 0,
    ]);

    $service = app(StockRequisitionService::class);
    $service->deliver($req, [$line->id => 20]);

    $req->refresh();
    $line->refresh();
    $item->refresh();

    expect($req->status)->toBe(StockRequisition::STATUS_DELIVERED)
        ->and($req->delivered_at)->not->toBeNull()
        ->and((float) $line->quantity_issued)->toBe(20.0)
        ->and((float) $item->current_stock)->toBe(80.0); // 100 - 20 = 80

    // Mouvement tracé
    $mvt = StockMovement::where('source_type', StockMovement::SOURCE_REQUISITION)
        ->where('source_id', $req->id)
        ->first();
    expect($mvt)->not->toBeNull()
        ->and($mvt->type)->toBe('out')
        ->and((float) $mvt->quantity)->toBe(-20.0);
});

test('chaque bon emis est automatiquement signe par le nom de l utilisateur connecte avec la police qwigley', function () {
    // 1. Vérification de l'extraction sur nom simple et nom double
    expect(User::extractSignatureName('Boris Setate'))->toBe('Boris')
        ->and(User::extractSignatureName('Jean Dupont'))->toBe('Jean')
        ->and(User::extractSignatureName('Clyde'))->toBe('Clyde')
        ->and(User::extractSignatureName('marie-claire kuate'))->toBe('Marie-Claire');

    // 2. Vérification de la présence des fichiers de police Qwigley installés localement
    expect(file_exists(public_path('fonts/Qwigley-Regular.woff2')))->toBeTrue()
        ->and(file_exists(public_path('fonts/Qwigley-Regular.ttf')))->toBeTrue();

    // 3. Émission d'un bon par un utilisateur ayant un nom composé ("Boris Setate")
    $demandeur = User::factory()->create([
        'name' => 'Boris Setate',
        'role' => 'accountant',
    ]);
    test()->actingAs($demandeur);

    $cat = StockCategory::create(['name' => 'Fournitures de bureau']);
    $item = StockItem::create([
        'name'              => 'Bloc-notes A5',
        'unit'              => 'piece',
        'current_stock'     => 20,
        'min_stock'         => 5,
        'average_cost'      => 80000,
        'stock_category_id' => $cat->id,
        'is_active'         => true,
    ]);

    $res = test()->post(route('economat.requisitions.store'), [
        'department' => 'comptabilite',
        'purpose'    => 'Fournitures réunions de clôture comptable',
        'lines'      => [
            ['stock_item_id' => $item->id, 'quantity' => 3],
        ],
    ]);
    $res->assertRedirect();

    $bon = StockRequisition::where('department', 'comptabilite')->latest()->first();
    expect($bon)->not->toBeNull()
        ->and($bon->requester_signature)->toBe('Boris')
        ->and($bon->requesterSignature())->toBe('Boris');

    // 4. Consultation du détail (show)
    $resShow = test()->get(route('economat.requisitions.show', $bon));
    $resShow->assertOk();
    $resShow->assertSee('Boris');
    $resShow->assertSee('Qwigley');
    $resShow->assertSee('font-signature');

    // 5. Impression officielle (print)
    $resPrint = test()->get(route('economat.requisitions.print', $bon));
    $resPrint->assertOk();
    $resPrint->assertSee('Qwigley');
    $resPrint->assertSee('sig-handwritten');
    $resPrint->assertSee('Boris');
    $resPrint->assertSee('Signé électroniquement le', false);
});

