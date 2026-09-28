<?php

use App\Models\PurchaseOrder;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== VÉRIFICATION COMPLÈTE DU MODULE FOURNISSEURS AMÉLIORÉ ===\n\n";

DB::beginTransaction();

try {
    // 1. Utilisateur Économe
    $econome = User::firstOrCreate(
        ['email' => 'econome.test@zingana.cm'],
        ['name' => 'Économe Test', 'role' => 'econome', 'password' => bcrypt('password')]
    );
    auth()->login($econome);
    echo "✔ Économe authentifié : {$econome->name}\n";

    // 2. Création Catégorie et Articles de test
    $category = StockCategory::firstOrCreate(['name' => 'Produits d\'Accueil & Hygiène']);
    $itemA = StockItem::create([
        'name'              => 'Savonnette Parfumée 30g Test',
        'unit'              => 'Pièce',
        'current_stock'     => 10,
        'min_stock'         => 5,
        'stock_category_id' => $category->id,
    ]);
    $itemB = StockItem::create([
        'name'              => 'Gel Douche Flacon 50ml Test',
        'unit'              => 'Flacon',
        'current_stock'     => 20,
        'min_stock'         => 10,
        'stock_category_id' => $category->id,
    ]);
    echo "✔ Articles de test créés : {$itemA->name}, {$itemB->name}\n";

    // 3. Test de création d'un Fournisseur enrichi avec ses informations complètes
    $controller = app(\App\Http\Controllers\Economat\SupplierController::class);

    $createRequest = Request::create(route('economat.suppliers.store'), 'POST', [
        'name'                    => 'Cosmétiques & Hygiène d\'Afrique SARL',
        'code'                    => 'FOU-COSMETIK',
        'category'                => 'hygiene',
        'tax_id'                  => 'M098765432100A',
        'rccm'                    => 'RC/DLA/2022/B/987',
        'city'                    => 'Douala',
        'address'                 => 'Zone Industrielle Bassa',
        'contact_name'            => 'Mme Chantal Manga',
        'email'                   => 'commandes@cosmetik-afrique.cm',
        'phone'                   => '+237 677 88 99 00',
        'payment_terms'           => '30_days',
        'payment_method'          => 'transfer',
        'delivery_lead_time_days' => 2,
        'bank_details'            => 'UBA CM21 10033 00012345678 90',
        'notes'                   => 'Fournisseur principal pour les produits d\'accueil des chambres VIP.',
        'is_active'               => '1',
        'linked_items'            => [
            [
                'id'    => $itemA->id,
                'unit'  => 'Carton de 100',
                'price' => '150', // 150 FCFA
            ],
            [
                'id'    => $itemB->id,
                'unit'  => 'Carton de 50',
                'price' => '350', // 350 FCFA
            ],
        ],
    ]);

    $controller->store($createRequest);

    $supplier = Supplier::where('name', 'Cosmétiques & Hygiène d\'Afrique SARL')->first();
    assert($supplier !== null, "Le fournisseur doit exister");
    assert($supplier->code === 'FOU-COSMETIK', "Code fournisseur correct");
    assert($supplier->category === 'hygiene', "Catégorie correcte");
    assert($supplier->category_label === 'Hygiène, Entretien & Accueil', "Libellé de catégorie correct");
    assert($supplier->payment_terms === '30_days', "Délai de paiement correct");
    assert($supplier->payment_terms_label === '30 jours', "Libellé délai de paiement correct");
    assert($supplier->payment_method === 'transfer', "Mode de règlement correct");
    assert($supplier->delivery_lead_time_days === 2, "Délai de livraison 2 jours");
    assert($supplier->canReceiveOrdersByEmail() === true, "Peut recevoir les commandes par email");

    // Vérification de la liaison des articles
    $itemA->refresh();
    $itemB->refresh();
    assert($itemA->supplier_id === $supplier->id, "Item A lié au fournisseur");
    assert($itemA->unit === 'Carton de 100', "Unité Item A mise à jour");
    assert($itemA->last_purchase_price === 15000, "Prix Item A en centimes (150 FCFA = 15000)");

    assert($itemB->supplier_id === $supplier->id, "Item B lié au fournisseur");
    assert($itemB->unit === 'Carton de 50', "Unité Item B mise à jour");
    assert($itemB->last_purchase_price === 35000, "Prix Item B en centimes (350 FCFA = 35000)");

    echo "✔ Création fournisseur avec données fiscales, commerciales, bancaires et liaison articles VALIDÉE !\n";

    // 4. Test de mise à jour et dissociation d'articles
    $updateRequest = Request::create(route('economat.suppliers.update', $supplier), 'PUT', [
        'name'                    => 'Cosmétiques & Hygiène d\'Afrique Pro SARL',
        'code'                    => 'FOU-COSMETIK-PRO',
        'category'                => 'hygiene',
        'tax_id'                  => 'M098765432100A',
        'city'                    => 'Douala Bonanjo',
        'payment_terms'           => 'cash',
        'payment_method'          => 'mobile_money',
        'delivery_lead_time_days' => 1,
        'is_active'               => '1',
        'linked_items'            => [
            [
                'id'    => $itemA->id,
                'unit'  => 'Carton 200',
                'price' => '140', // Prix renégocié 140 FCFA
            ],
            // Item B est retiré intentionnellement
        ],
    ]);

    $controller->update($updateRequest, $supplier);

    $supplier->refresh();
    assert($supplier->name === 'Cosmétiques & Hygiène d\'Afrique Pro SARL', "Nom mis à jour");
    assert($supplier->payment_terms === 'cash', "Délai mis à jour à comptant");
    assert($supplier->payment_method === 'mobile_money', "Mode mis à jour à Mobile Money");
    assert($supplier->delivery_lead_time_days === 1, "Délai de livraison 1 jour");

    $itemA->refresh();
    $itemB->refresh();
    assert($itemA->supplier_id === $supplier->id, "Item A toujours lié");
    assert($itemA->unit === 'Carton 200', "Unité Item A mise à jour");
    assert($itemA->last_purchase_price === 14000, "Prix Item A mis à jour (140 FCFA = 14000)");

    assert($itemB->supplier_id === null, "Item B dissocié avec succès");

    echo "✔ Mise à jour fournisseur et dissociation d'article retiré VALIDÉE !\n";

    // 5. Test de consultation de l'index avec filtres et KPIs
    $indexRequest = Request::create(route('economat.suppliers.index'), 'GET', [
        'search'   => 'Bonanjo',
        'category' => 'hygiene',
        'status'   => 'active',
    ]);
    $view = $controller->index($indexRequest);
    $viewData = $view->getData();

    assert(isset($viewData['kpis']), "KPIs présents");
    assert($viewData['kpis']['total_suppliers'] >= 1, "Compte total fournisseurs");
    assert($viewData['kpis']['active_suppliers'] >= 1, "Compte fournisseurs actifs");
    assert(isset($viewData['availableItems']), "Articles disponibles passés à la vue");
    assert(isset($viewData['categories']), "Catégories passées à la vue");

    echo "✔ Index, filtres multi-critères, recherche et KPIs VALIDÉS !\n";

    echo "\nTOUS LES TESTS SONT PASSÉS AVEC SUCCÈS À 100% !\n";

} finally {
    DB::rollBack();
    echo "✔ Rollback transactionnel effectué (base de données propre).\n";
}
