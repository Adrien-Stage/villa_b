<?php

use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== VÉRIFICATION DU CHAMP ORDRE AUTO-INCRÉMENTÉ & UNICITÉ DES CATÉGORIES ===\n\n";

DB::beginTransaction();

try {
    // 1. Authentification d'un utilisateur Économe
    $econome = User::firstOrCreate(
        ['email' => 'econome.cat.test@zingana.cm'],
        ['name' => 'Économe Test Catégories', 'role' => 'econome', 'password' => bcrypt('password')]
    );
    auth()->login($econome);
    echo "✔ Économe connecté : {$econome->name}\n";

    $controller = app(\App\Http\Controllers\Economat\StockCategoryController::class);

    // 2. Test du chargement de l'index et du calcul de nextSortOrder
    $indexView = $controller->index();
    $data = $indexView->getData();
    $categories = $data['categories'];
    $nextSortOrder = $data['nextSortOrder'];

    echo "✔ Catégories existantes : " . $categories->count() . "\n";
    echo "✔ Prochain ordre calculé (nextSortOrder) : {$nextSortOrder}\n";
    $maxCurrent = $categories->max('sort_order');
    $expectedNext = $maxCurrent !== null ? ($maxCurrent + 1) : 0;
    assert($nextSortOrder === $expectedNext, "nextSortOrder doit être égal à max(sort_order) + 1");

    // 3. Test de création d'une catégorie avec l'ordre calculé automatiquement
    $firstNewOrder = $nextSortOrder;
    $reqCreate1 = Request::create(route('economat.categories.store'), 'POST', [
        'name'          => 'CONFISERIE & BISCUITS',
        'stock_account' => '321000',
        'sort_order'    => $firstNewOrder,
    ]);
    $controller->store($reqCreate1);

    $cat1 = StockCategory::where('name', 'CONFISERIE & BISCUITS')->first();
    assert($cat1 !== null, "La catégorie CONFISERIE & BISCUITS doit être créée");
    assert($cat1->sort_order === $firstNewOrder, "L'ordre doit correspondre à {$firstNewOrder}");
    echo "✔ Catégorie « {$cat1->name} » créée avec l'ordre auto-incrémenté {$cat1->sort_order} !\n";

    // 4. Vérification que l'index propose désormais le suivant (firstNewOrder + 1)
    $indexView2 = $controller->index();
    $data2 = $indexView2->getData();
    assert($data2['nextSortOrder'] === $firstNewOrder + 1, "Le prochain ordre doit s'incrémenter à " . ($firstNewOrder + 1));
    echo "✔ Après création, le prochain ordre est automatiquement {$data2['nextSortOrder']} !\n";

    // 5. Création d'une seconde catégorie avec l'ordre suivant
    $secondNewOrder = $data2['nextSortOrder'];
    $reqCreate2 = Request::create(route('economat.categories.store'), 'POST', [
        'name'          => 'VINS FINS & CHAMPAGNES',
        'stock_account' => '311000',
        'sort_order'    => $secondNewOrder,
    ]);
    $controller->store($reqCreate2);

    $cat2 = StockCategory::where('name', 'VINS FINS & CHAMPAGNES')->first();
    assert($cat2 !== null, "La catégorie VINS FINS & CHAMPAGNES doit être créée");
    assert($cat2->sort_order === $secondNewOrder, "L'ordre doit correspondre à {$secondNewOrder}");
    echo "✔ Catégorie « {$cat2->name} » créée avec l'ordre auto-incrémenté {$cat2->sort_order} !\n";

    // 6. Test d'interdiction de valider un ordre DÉJÀ ATTRIBUÉ (doublon interdit)
    echo "\nTest de la contrainte d'unicité sur un ordre déjà attribué...\n";
    $duplicateAttemptFailed = false;
    try {
        $reqDuplicate = Request::create(route('economat.categories.store'), 'POST', [
            'name'          => 'DOUBLON TENTATIVE',
            'stock_account' => '332000',
            'sort_order'    => $firstNewOrder, // Déjà pris par CONFISERIE & BISCUITS
        ]);
        $controller->store($reqDuplicate);
    } catch (ValidationException $e) {
        $duplicateAttemptFailed = true;
        $errors = $e->errors();
        assert(isset($errors['sort_order']), "L'erreur de validation doit porter sur sort_order");
        echo "✔ ValidationException levée avec succès : " . $errors['sort_order'][0] . "\n";
    }
    assert($duplicateAttemptFailed, "La création d'une catégorie avec un ordre déjà attribué DOIT échouer");

    // 7. Test de mise à jour : conserver son propre ordre est autorisé
    $reqUpdateSame = Request::create(route('economat.categories.update', $cat1), 'PUT', [
        'name'          => 'CONFISERIE & CHOCOLATS',
        'stock_account' => '321000',
        'sort_order'    => $cat1->sort_order,
    ]);
    $controller->update($reqUpdateSame, $cat1);
    $cat1->refresh();
    assert($cat1->name === 'CONFISERIE & CHOCOLATS', "Nom mis à jour");
    assert($cat1->sort_order === $firstNewOrder, "L'ordre reste inchangé sans conflit");
    echo "✔ Mise à jour d'une catégorie avec conservation de son propre ordre VALIDÉE !\n";

    // 8. Test de mise à jour : tenter d'attribuer l'ordre d'une autre catégorie est refusé
    $updateConflictFailed = false;
    try {
        $reqUpdateConflict = Request::create(route('economat.categories.update', $cat1), 'PUT', [
            'name'          => 'CONFISERIE & CHOCOLATS',
            'stock_account' => '321000',
            'sort_order'    => $cat2->sort_order, // Déjà pris par cat2
        ]);
        $controller->update($reqUpdateConflict, $cat1);
    } catch (ValidationException $e) {
        $updateConflictFailed = true;
        $errors = $e->errors();
        assert(isset($errors['sort_order']), "L'erreur doit cibler sort_order");
        echo "✔ Mise à jour conflictuelle bloquée : " . $errors['sort_order'][0] . "\n";
    }
    assert($updateConflictFailed, "La mise à jour vers un ordre déjà attribué DOIT échouer");

    // 9. Test de création sans sort_order spécifié (auto-assignation par le modèle)
    $catAuto = StockCategory::create(['name' => 'ÉPICES & CONDIMENTS']);
    assert($catAuto->sort_order > $cat2->sort_order, "L'ordre automatique est supérieur au précédent");
    echo "✔ Création par le modèle sans ordre spécifié : ordre {$catAuto->sort_order} auto-assigné !\n";

    echo "\nTOUS LES TESTS DE GESTION DE L'ORDRE SONT PASSÉS AVEC SUCCÈS À 100% !\n";

} finally {
    DB::rollBack();
    echo "✔ Rollback transactionnel effectué (base de données propre).\n";
}
