<?php

use App\Models\PurchaseOrder;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('l econome accede a la liste enrichie des fournisseurs avec KPIs et catalogue d articles', function () {
    $econome = User::factory()->create([
        'name' => 'Paul Biya Meka',
        'role' => 'econome',
    ]);

    $category = StockCategory::create(['name' => 'Hygiène & Entretien']);

    $item1 = StockItem::create([
        'name'              => 'Savonnette 30g',
        'unit'              => 'Pièce',
        'current_stock'     => 10,
        'min_stock'         => 5,
        'stock_category_id' => $category->id,
    ]);

    $supplier = Supplier::create([
        'name'                    => 'Prodimex Hygiène SARL',
        'code'                    => 'FOU-PRODIMEX',
        'category'                => 'hygiene',
        'tax_id'                  => 'M051200000000X',
        'rccm'                    => 'RC/DLA/2020/B/1234',
        'city'                    => 'Douala',
        'contact_name'            => 'M. Essomba',
        'email'                   => 'commandes@prodimex.cm',
        'phone'                   => '+237 670 00 00 00',
        'payment_terms'           => '30_days',
        'payment_method'          => 'transfer',
        'delivery_lead_time_days' => 2,
        'is_active'               => true,
    ]);

    // Son tarif chez ce fournisseur : 150 FCFA la pièce.
    app(\App\Services\SupplierTariffService::class)->fixer($item1, $supplier, 15000);

    $response = $this->actingAs($econome)->get(route('economat.suppliers.index'));

    $response->assertOk()
        ->assertSee('Fournisseurs de l\'Économat', false)
        ->assertSee('Prodimex Hygiène SARL')
        ->assertSee('FOU-PRODIMEX')
        ->assertSee('Hygiène, Entretien &amp; Accueil', false)
        ->assertSee('Douala')
        ->assertSee('M051200000000X')
        ->assertSee('commandes@prodimex.cm')
        ->assertSee('1 article(s)');
});

test('le responsable des achats cree un fournisseur avec toutes ses informations commerciales et ses articles lies', function () {
    // Le fichier des fournisseurs est tenu par le responsable des achats.
    $econome = User::factory()->create([
        'name' => 'Jean-Pierre Eboule',
        'role' => 'purchasing_manager',
    ]);

    $category = StockCategory::create(['name' => 'Boissons']);
    // Les unités se choisissent dans la liste de Paramètres › Économat.
    StockUnit::create(['name' => 'Pack de 6']);
    StockUnit::create(['name' => 'Carton de 12']);
    $itemA = StockItem::create([
        'name'              => 'Eau Minérale 1.5L x6',
        'unit'              => 'Pack',
        'current_stock'     => 0,
        'stock_category_id' => $category->id,
    ]);
    $itemB = StockItem::create([
        'name'              => 'Jus Naturel Ananas 1L',
        'unit'              => 'Bouteille',
        'current_stock'     => 0,
        'stock_category_id' => $category->id,
    ]);

    $payload = [
        'name'                    => 'Société Anonyme des Brasseries (SABC)',
        'code'                    => 'FOU-SABC',
        'category'                => 'beverage',
        'tax_id'                  => 'M01900000001Z',
        'rccm'                    => 'RC/DLA/1960/A/001',
        'city'                    => 'Douala',
        'address'                 => 'Koumassi, Zone Industrielle',
        'contact_name'            => 'M. Nguema Commercial',
        'email'                   => 'commandes@sabc.cm',
        'phone'                   => '+237 699 99 99 99',
        'payment_terms'           => '15_days',
        'payment_method'          => 'transfer',
        'delivery_lead_time_days' => 1,
        'bank_details'            => 'BICEC CM21 10001 00001234567 89',
        'notes'                   => 'Livraison chaque mardi et jeudi matin sans faute.',
        'is_active'               => '1',
        'linked_items'            => [
            [
                'id'    => $itemA->id,
                'price' => '2400', // 2400 FCFA -> 240000 centimes
            ],
            [
                'id'    => $itemB->id,
                'price' => '12000', // 12000 FCFA
            ],
        ],
    ];

    $response = $this->actingAs($econome)
        ->from(route('economat.suppliers.index'))
        ->post(route('economat.suppliers.store'), $payload);

    $response->assertRedirect(route('economat.suppliers.index'))
        ->assertSessionHas('success');

    $supplier = Supplier::where('name', 'Société Anonyme des Brasseries (SABC)')->first();
    expect($supplier)->not->toBeNull()
        ->and($supplier->code)->toBe('FOU-SABC')
        ->and($supplier->category)->toBe('beverage')
        ->and($supplier->tax_id)->toBe('M01900000001Z')
        ->and($supplier->city)->toBe('Douala')
        ->and($supplier->delivery_lead_time_days)->toBe(1)
        ->and($supplier->canReceiveOrdersByEmail())->toBeTrue();

    // Vérification de la liaison des articles
    $itemA->refresh();
    $itemB->refresh();

    // Le tarif s'exprime dans l'unité de stock : l'article garde la sienne,
    // et son dernier prix payé reste celui des réceptions.
    expect($itemA->supplier_id)->toBe($supplier->id)
        ->and($itemA->unit)->toBe('Pack')
        ->and($itemA->supplierPriceFor($supplier))->toBe(240000)
        ->and((int) $itemA->last_purchase_price)->toBe(0)
        ->and($itemB->supplier_id)->toBe($supplier->id)
        ->and($itemB->unit)->toBe('Bouteille')
        ->and($itemB->supplierPriceFor($supplier))->toBe(1200000);
});

test('le responsable des achats met a jour un fournisseur et dissocie un article retire', function () {
    $econome = User::factory()->create([
        'name' => 'Jean-Pierre Eboule',
        'role' => 'purchasing_manager',
    ]);

    $supplier = Supplier::create([
        'name'      => 'BuroTech Cameroun',
        'code'      => 'FOU-BURO',
        'category'  => 'office',
        'email'     => 'vente@burotech.cm',
        'is_active' => true,
    ]);

    $item1 = StockItem::create([
        'name'                => 'Rame Papier A4 80g',
        'unit'                => 'Rame',
        'current_stock'       => 5,
        'supplier_id'         => $supplier->id,
        'last_purchase_price' => 320000,
    ]);

    $item2 = StockItem::create([
        'name'                => 'Stylo Bille Bleu (Boîte de 50)',
        'unit'                => 'Boîte',
        'current_stock'       => 2,
        'supplier_id'         => $supplier->id,
        'last_purchase_price' => 500000,
    ]);

    $tarifs = app(\App\Services\SupplierTariffService::class);
    $tarifs->fixer($item1, $supplier, 320000);
    $tarifs->fixer($item2, $supplier, 500000);

    // On retire l'article 2 et on ajuste le prix de l'article 1
    $updatePayload = [
        'name'                    => 'BuroTech Bureautique & Papeterie SARL',
        'code'                    => 'FOU-BURO-PRO',
        'category'                => 'office',
        'tax_id'                  => 'M028711122233X',
        'city'                    => 'Yaoundé',
        'email'                   => 'contact@burotech.cm',
        'payment_terms'           => 'cash',
        'payment_method'          => 'mobile_money',
        'delivery_lead_time_days' => 3,
        'is_active'               => '1',
        'linked_items'            => [
            [
                'id'    => $item1->id,
                'price' => '3500', // Nouveau prix négocié 3500 FCFA
            ],
        ],
    ];

    $response = $this->actingAs($econome)
        ->from(route('economat.suppliers.index'))
        ->put(route('economat.suppliers.update', $supplier), $updatePayload);

    $response->assertRedirect(route('economat.suppliers.index'))
        ->assertSessionHas('success');

    $supplier->refresh();
    expect($supplier->name)->toBe('BuroTech Bureautique & Papeterie SARL')
        ->and($supplier->code)->toBe('FOU-BURO-PRO')
        ->and($supplier->city)->toBe('Yaoundé')
        ->and($supplier->payment_terms)->toBe('cash')
        ->and($supplier->payment_method)->toBe('mobile_money');

    $item1->refresh();
    $item2->refresh();

    // Item 1 toujours lié, au nouveau prix ; son unité ne change pas
    expect($item1->supplier_id)->toBe($supplier->id)
        ->and($item1->unit)->toBe('Rame')
        ->and($item1->supplierPriceFor($supplier))->toBe(350000);

    // Item 2 dissocié : plus de tarif chez ce fournisseur, plus de fournisseur habituel
    expect($item2->supplierPriceFor($supplier))->toBeNull()
        ->and($item2->supplier_id)->toBeNull();
});

test('le filtrage par recherche, categorie et statut fonctionne sur les fournisseurs', function () {
    $econome = User::factory()->create([
        'name' => 'Filtreur Test',
        'role' => 'econome',
    ]);

    Supplier::create([
        'name'      => 'Boulangerie Moderne',
        'code'      => 'FOU-PAIN',
        'category'  => 'food',
        'city'      => 'Douala',
        'is_active' => true,
    ]);

    Supplier::create([
        'name'      => 'Blanchisserie Centrale',
        'code'      => 'FOU-BLANC',
        'category'  => 'linen',
        'city'      => 'Yaoundé',
        'is_active' => false,
    ]);

    // Filtrer par catégorie 'food'
    $resCat = $this->actingAs($econome)->get(route('economat.suppliers.index', ['category' => 'food']));
    $resCat->assertOk()
        ->assertSee('Boulangerie Moderne')
        ->assertDontSee('Blanchisserie Centrale');

    // Filtrer par statut 'inactive'
    $resStatus = $this->actingAs($econome)->get(route('economat.suppliers.index', ['status' => 'inactive']));
    $resStatus->assertOk()
        ->assertSee('Blanchisserie Centrale')
        ->assertDontSee('Boulangerie Moderne');

    // Recherche par mot clé 'Yaoundé'
    $resSearch = $this->actingAs($econome)->get(route('economat.suppliers.index', ['search' => 'Yaoundé']));
    $resSearch->assertOk()
        ->assertSee('Blanchisserie Centrale')
        ->assertDontSee('Boulangerie Moderne');
});
