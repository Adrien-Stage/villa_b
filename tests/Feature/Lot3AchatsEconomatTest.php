<?php

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DutySegregation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function creerUtilisateurLot3(string $roleSlug): User
{
    $user = User::factory()->create(['is_active' => true]);
    $role = Role::where('slug', $roleSlug)->firstOrFail();
    $user->roles()->attach($role->id, ['level' => $role->level]);

    return $user;
}

test('Lot 3 - Responsable Achats peut creer un article et un fournisseur mais ne peut ni les modifier ni les supprimer', function () {
    $acheteur = creerUtilisateurLot3('purchasing_manager');
    $categorie = StockCategory::firstOrCreate(['name' => 'Alimentation'], ['sort_order' => 1]);
    $fournisseur = Supplier::create([
        'name' => 'Fournisseur Test Initial',
        'phone' => '690112233',
        'is_active' => true,
    ]);

    // 1. Création d'article par l'acheteur : autorisée
    $this->actingAs($acheteur)
        ->post(route('economat.items.store'), [
            'name' => 'Farine de Blé 50kg',
            'unit' => 'kg',
            'stock_category_id' => $categorie->id,
            'supplier_id' => $fournisseur->id,
            'min_stock' => 5,
        ])->assertRedirect();

    $article = StockItem::where('name', 'Farine de Blé 50kg')->first();
    expect($article)->not->toBeNull();

    // 2. Création d'un nouveau fournisseur par l'acheteur : autorisée
    $this->actingAs($acheteur)
        ->post(route('economat.suppliers.store'), [
            'name' => 'Nouveau Meunier SA',
            'phone' => '691223344',
            'email' => 'contact@meunier.test',
        ])->assertRedirect();

    $nouveauFournisseur = Supplier::where('name', 'Nouveau Meunier SA')->first();
    expect($nouveauFournisseur)->not->toBeNull();

    // 3. Tentative de MODIFICATION d'article par l'acheteur : REFUSÉE (403 ou redirection denied)
    $resModifArt = $this->actingAs($acheteur)
        ->put(route('economat.items.update', $article), [
            'name' => 'Farine Modifiée Frauduleusement',
            'unit' => 'kg',
            'min_stock' => 10,
        ]);
    expect($resModifArt->status())->toBeIn([302, 403]);
    if ($resModifArt->status() === 302) {
        $resModifArt->assertSessionHas('access_denied_popup', true);
    }
    expect($article->fresh()->name)->toBe('Farine de Blé 50kg');

    // 4. Tentative de SUPPRESSION d'article par l'acheteur : REFUSÉE
    $resSupprArt = $this->actingAs($acheteur)->delete(route('economat.items.destroy', $article));
    expect($resSupprArt->status())->toBeIn([302, 403]);
    if ($resSupprArt->status() === 302) {
        $resSupprArt->assertSessionHas('access_denied_popup', true);
    }
    expect(StockItem::find($article->id))->not->toBeNull();

    // 5. Tentative de MODIFICATION de fournisseur par l'acheteur : REFUSÉE
    $resModifFourn = $this->actingAs($acheteur)
        ->put(route('economat.suppliers.update', $fournisseur), [
            'name' => 'Nom Fournisseur Détourné',
            'phone' => '699999999',
        ]);
    expect($resModifFourn->status())->toBeIn([302, 403]);
    if ($resModifFourn->status() === 302) {
        $resModifFourn->assertSessionHas('access_denied_popup', true);
    }
    expect($fournisseur->fresh()->name)->toBe('Fournisseur Test Initial');

    // 6. Tentative de SUPPRESSION de fournisseur par l'acheteur : REFUSÉE
    $resSupprFourn = $this->actingAs($acheteur)->delete(route('economat.suppliers.destroy', $fournisseur));
    expect($resSupprFourn->status())->toBeIn([302, 403]);
    if ($resSupprFourn->status() === 302) {
        $resSupprFourn->assertSessionHas('access_denied_popup', true);
    }
    expect(Supplier::find($fournisseur->id))->not->toBeNull();
});

test('Lot 3 - Responsable Achats ne peut pas ajuster le stock ni lier/delier les tarifs d articles existants', function () {
    $acheteur = creerUtilisateurLot3('purchasing_manager');
    $fournisseur = Supplier::create(['name' => 'Fournisseur Bio', 'phone' => '690000000', 'is_active' => true]);
    $article = StockItem::create([
        'name' => 'Riz Parfumé',
        'unit' => 'kg',
        'current_stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    // Tentative d'ajustement de stock : REFUSÉE
    $resAjust = $this->actingAs($acheteur)->post(route('economat.items.adjust', $article), [
        'counted_quantity' => 100,
        'adjustment_type' => 'inventory',
    ]);
    expect($resAjust->status())->toBeIn([302, 403]);
    if ($resAjust->status() === 302) {
        $resAjust->assertSessionHas('access_denied_popup', true);
    }
    expect((float) $article->fresh()->current_stock)->toBe(10.0);

    // Tentative de reprise de stock initial : REFUSÉE
    $resReprise = $this->actingAs($acheteur)->post(route('economat.items.opening', $article), [
        'quantity' => 50,
        'unit_cost' => 500,
    ]);
    expect($resReprise->status())->toBeIn([302, 403]);
    if ($resReprise->status() === 302) {
        $resReprise->assertSessionHas('access_denied_popup', true);
    }

    // Tentative de liaison tarifaire fournisseur : REFUSÉE
    $resLiaison = $this->actingAs($acheteur)->post(route('economat.items.suppliers.link', $article), [
        'supplier_id' => $fournisseur->id,
        'purchase_price' => 1500,
    ]);
    expect($resLiaison->status())->toBeIn([302, 403]);
    if ($resLiaison->status() === 302) {
        $resLiaison->assertSessionHas('access_denied_popup', true);
    }
});

test('Lot 3 - Controleur de gestion a l exclusivite de modifier/supprimer articles et fournisseurs et d ajuster le stock', function () {
    $controleur = creerUtilisateurLot3('controller');
    $fournisseur = Supplier::create(['name' => 'Fournisseur Contrôle', 'phone' => '691111111', 'is_active' => true]);
    $article = StockItem::create([
        'name' => 'Sucre Roux',
        'unit' => 'kg',
        'current_stock' => 15,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    // 1. Modification d'article par le contrôleur : AUTORISÉE
    $this->actingAs($controleur)
        ->put(route('economat.items.update', $article), [
            'name' => 'Sucre Roux Raffiné',
            'unit' => 'kg',
            'min_stock' => 8,
        ])->assertRedirect();
    expect($article->fresh()->name)->toBe('Sucre Roux Raffiné');

    // 2. Ajustement de stock par le contrôleur : AUTORISÉ
    $this->actingAs($controleur)
        ->post(route('economat.items.adjust', $article), [
            'counted_quantity' => 20,
            'adjustment_type' => 'inventory',
            'reason' => 'Vérification contradictoire contrôle',
        ])->assertRedirect();
    expect((float) $article->fresh()->current_stock)->toBe(20.0);

    // 3. Liaison fournisseur par le contrôleur : AUTORISÉE
    $this->actingAs($controleur)
        ->post(route('economat.items.suppliers.link', $article), [
            'supplier_id' => $fournisseur->id,
            'purchase_price' => 850,
        ])->assertRedirect();
    expect($article->suppliers()->where('supplier_id', $fournisseur->id)->exists())->toBeTrue();

    // 4. Modification de fournisseur par le contrôleur : AUTORISÉE
    $this->actingAs($controleur)
        ->put(route('economat.suppliers.update', $fournisseur), [
            'name' => 'Fournisseur Contrôle Mis à Jour',
            'phone' => '692222222',
        ])->assertRedirect();
    expect($fournisseur->fresh()->name)->toBe('Fournisseur Contrôle Mis à Jour');

    // 5. Suppression d'un article sans mouvements par le contrôleur : AUTORISÉE
    $articleSuppr = StockItem::create(['name' => 'Article Éphémère', 'unit' => 'kg', 'current_stock' => 0, 'min_stock' => 1, 'is_active' => true]);
    $this->actingAs($controleur)->delete(route('economat.items.destroy', $articleSuppr))->assertRedirect();
    expect(StockItem::find($articleSuppr->id))->toBeNull();

    // 6. Suppression d'un fournisseur sans commandes par le contrôleur : AUTORISÉE
    $fournSuppr = Supplier::create(['name' => 'Fournisseur Éphémère', 'phone' => '693333333', 'is_active' => true]);
    $this->actingAs($controleur)->delete(route('economat.suppliers.destroy', $fournSuppr))->assertRedirect();
    expect(Supplier::find($fournSuppr->id))->toBeNull();
});

test('Lot 3 - Responsable Achats peut emettre un bon de commande direct sans expression de besoin prealable', function () {
    $acheteur = creerUtilisateurLot3('purchasing_manager');
    $fournisseur = Supplier::create(['name' => 'Grossiste Direct', 'phone' => '694444444', 'is_active' => true]);
    $article = StockItem::create(['name' => 'Café Moulu', 'unit' => 'kg', 'current_stock' => 3, 'min_stock' => 5, 'is_active' => true]);

    $this->actingAs($acheteur)->get(route('economat.orders.create'))->assertOk();

    $this->actingAs($acheteur)->post(route('economat.orders.store'), [
        'supplier_id' => $fournisseur->id,
        'expected_at' => now()->addDays(2)->format('Y-m-d'),
        'notes' => 'Commande directe urgente pour le banquet',
        'lines' => [
            ['stock_item_id' => $article->id, 'quantity' => 25, 'unit_price' => 4500],
        ],
    ])->assertRedirect();

    $bon = PurchaseOrder::where('supplier_id', $fournisseur->id)->latest('id')->first();
    expect($bon)->not->toBeNull()
        ->and($bon->purchase_request_id)->toBeNull()
        ->and($bon->lines)->toHaveCount(1)
        ->and((float) $bon->lines->first()->quantity_ordered)->toBe(25.0);
});

test('Lot 3 - Econome et Magasiniers ont interdiction formelle de visibilite sur les bons de commande', function () {
    $econome = creerUtilisateurLot3('econome');
    $magasinier = creerUtilisateurLot3('storekeeper');
    $fournisseur = Supplier::create(['name' => 'Fournisseur Secret', 'phone' => '695555555', 'is_active' => true]);
    $article = StockItem::create(['name' => 'Thé Vert', 'unit' => 'kg', 'current_stock' => 10, 'min_stock' => 2, 'is_active' => true]);

    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_SENT]);
    $bon->lines()->create(['stock_item_id' => $article->id, 'quantity_ordered' => 10, 'unit_price' => 250000]);

    // 1. Accès à la liste des bons de commande : REFUSÉ (403 ou redirection popup)
    foreach ([$econome, $magasinier] as $user) {
        $resListe = $this->actingAs($user)->get(route('economat.orders.index'));
        expect($resListe->status())->toBeIn([302, 403]);
        if ($resListe->status() === 302) {
            $resListe->assertSessionHas('access_denied_popup', true);
        }

        // 2. Accès à un bon de commande détaillé : REFUSÉ
        $resDetail = $this->actingAs($user)->get(route('economat.orders.show', $bon));
        expect($resDetail->status())->toBeIn([302, 403]);
        if ($resDetail->status() === 302) {
            $resDetail->assertSessionHas('access_denied_popup', true);
        }

        // 3. Export des bons de commande : REFUSÉ
        $resExport = $this->actingAs($user)->get(route('economat.orders.export'));
        expect($resExport->status())->toBeIn([302, 403]);
        if ($resExport->status() === 302) {
            $resExport->assertSessionHas('access_denied_popup', true);
        }
    }
});

test('Lot 3 - Les vues de reception et fiches fournisseurs ne font pas fuiter les bons ni les prix vers l econome', function () {
    $econome = creerUtilisateurLot3('econome');
    $fournisseur = Supplier::create(['name' => 'Fournisseur Étanche', 'phone' => '696666666', 'is_active' => true]);
    $article = StockItem::create(['name' => 'Poivre Noir', 'unit' => 'kg', 'current_stock' => 5, 'min_stock' => 1, 'is_active' => true]);

    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_SENT]);
    $bon->lines()->create(['stock_item_id' => $article->id, 'quantity_ordered' => 15, 'unit_price' => 500000]);
    $bon->recalculateTotal();

    $reception = GoodsReceipt::create([
        'purchase_order_id' => $bon->id,
        'supplier_id' => $fournisseur->id,
        'number' => 'BR-TEST-001',
        'status' => GoodsReceipt::STATUS_RECEIVED,
        'delivery_type' => GoodsReceipt::DELIVERY_COMPLETE,
        'received_at' => now(),
    ]);

    // 1. Fiche fournisseur vue par l'économe : la section "Bons de commande en cours" est absente
    $this->actingAs($econome)
        ->get(route('economat.suppliers.show', $fournisseur))
        ->assertOk()
        ->assertDontSee('Bons de commande en cours');

    // 2. Fiche de réception vue par l'économe : le bon est mentionné en texte mais SANS lien cliquable vers orders.show
    $vueReception = $this->actingAs($econome)
        ->get(route('economat.receipts.show', $reception))
        ->assertOk()
        ->getContent();

    expect($vueReception)->toContain($bon->number)
        ->not->toContain(route('economat.orders.show', $bon));

    // 3. Liste des réceptions vue par l'économe : aucun lien cliquable vers orders.show
    $vueListeReceptions = $this->actingAs($econome)
        ->get(route('economat.receipts.index'))
        ->assertOk()
        ->getContent();

    expect($vueListeReceptions)->toContain($bon->number)
        ->not->toContain(route('economat.orders.show', $bon));
});

test('Lot 3 - Segregation des taches : interdiction stricte de cumuler Responsable Achats avec Econome, Magasinier ou Comptable', function () {
    $cumulsInterdits = [
        ['purchasing_manager', 'econome'],
        ['purchasing_manager', 'storekeeper'],
        ['purchasing_manager', 'accountant'],
    ];

    foreach ($cumulsInterdits as $roles) {
        $conflits = DutySegregation::conflictsFor($roles);
        expect($conflits)->not->toBeEmpty();
    }
});

test("Lot 3 - Responsable Achats ne peut plus creer d'expression de besoin (demande d'achat)", function () {
    $acheteur = creerUtilisateurLot3('purchasing_manager');
    $econome = creerUtilisateurLot3('econome');

    // 1. Matrice des droits : purchasing_manager n'a plus economat.purchase_requests.creer
    expect(PermissionCatalog::roles('economat.purchase_requests.creer'))
        ->not->toContain('purchasing_manager')
        ->toContain('econome');

    // 2. Accès direct refusé pour l'acheteur (popup de refus en web, 403 en JSON)
    $this->actingAs($acheteur)
        ->get(route('economat.purchase_requests.create'))
        ->assertSessionHas('access_denied_popup', true);

    $this->actingAs($acheteur)
        ->getJson(route('economat.purchase_requests.create'))
        ->assertForbidden();

    $this->actingAs($acheteur)
        ->post(route('economat.purchase_requests.store'), [
            'lines' => [['stock_item_id' => 1, 'quantity_requested' => 10]],
        ])->assertSessionHas('access_denied_popup', true);

    $this->actingAs($acheteur)
        ->postJson(route('economat.purchase_requests.store'), [
            'lines' => [['stock_item_id' => 1, 'quantity_requested' => 10]],
        ])->assertForbidden();

    // 3. L'économe peut toujours créer une demande d'achat
    $this->actingAs($econome)
        ->get(route('economat.purchase_requests.create'))
        ->assertOk();

    // 4. Sur le tableau de bord /achats, l'acheteur voit "Nouveau bon de commande" et non "Nouvelle demande d'achat"
    $pageAchatsAcheteur = $this->actingAs($acheteur)
        ->get(route('achats.index'))
        ->assertOk()
        ->getContent();

    expect($pageAchatsAcheteur)
        ->toContain(route('economat.orders.create'))
        ->not->toContain(route('economat.purchase_requests.create'));
});

test("Lot 3 - Selection du fournisseur par article et comparateur integre aux bons de commande", function () {
    $acheteur = creerUtilisateurLot3('purchasing_manager');

    $fournisseurA = Supplier::create(['name' => 'Grossiste Alpha', 'phone' => '690000001', 'is_active' => true]);
    $fournisseurB = Supplier::create(['name' => 'Grossiste Beta', 'phone' => '690000002', 'is_active' => true]);

    $categorie = StockCategory::firstOrCreate(['name' => 'Épicerie'], ['sort_order' => 1]);
    $article = StockItem::create([
        'name' => 'Sucre en Poudre 50kg',
        'unit' => 'kg',
        'stock_category_id' => $categorie->id,
        'supplier_id' => $fournisseurA->id,
        'current_stock' => 15,
        'is_active' => true,
    ]);

    // Fixer des tarifs contractuels pour comparer
    DB::table('stock_item_supplier')->insert([
        ['stock_item_id' => $article->id, 'supplier_id' => $fournisseurA->id, 'purchase_price' => 3000000], // 30 000 FCFA
        ['stock_item_id' => $article->id, 'supplier_id' => $fournisseurB->id, 'purchase_price' => 2800000], // 28 000 FCFA (moins cher)
    ]);

    // 1. Route API de comparaison pour les bons de commande
    $reponse = $this->actingAs($acheteur)
        ->getJson(route('economat.orders.fournisseurs', $article))
        ->assertOk()
        ->json();

    expect($reponse['article']['id'])->toBe($article->id)
        ->and($reponse['fournisseurs'])->toHaveCount(2)
        ->and($reponse['fournisseurs'][0]['id'])->toBe($fournisseurB->id)
        ->and($reponse['fournisseurs'][0]['moinsCher'])->toBeTrue();

    // 2. Affichage de la page /economat/bons/nouveau avec le comparateur
    $vue = $this->actingAs($acheteur)
        ->get(route('economat.orders.create', ['article' => $article->id]))
        ->assertOk()
        ->getContent();

    expect($vue)
        ->toContain('Comparateur de Prix Fournisseurs')
        ->toContain('Comparer les fournisseurs par article')
        ->toContain(route('economat.orders.store'));

    // 3. Soumission d'un bon de commande issu du choix comparatif
    $this->actingAs($acheteur)
        ->post(route('economat.orders.store'), [
            'supplier_id' => $fournisseurB->id,
            'expected_at' => now()->addDays(2)->format('Y-m-d'),
            'lines' => [
                [
                    'stock_item_id' => $article->id,
                    'quantity' => 5,
                    'unit_price' => 28000,
                ],
            ],
        ])->assertRedirect();

    $bon = PurchaseOrder::where('supplier_id', $fournisseurB->id)->latest()->first();
    expect($bon)->not->toBeNull()
        ->and($bon->total_amount)->toBe(14000000) // 140 000 FCFA en centimes
        ->and($bon->issuer_signature)->not->toBeNull();
});
