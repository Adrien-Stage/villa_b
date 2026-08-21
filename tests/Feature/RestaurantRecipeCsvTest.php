<?php

use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

if (!function_exists('enableModules')) {
    function enableModules(array $modules): void
    {
        $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
        $prop->setAccessible(true);
        $prop->setValue(null, $modules);
    }
}

function recipeManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'restaurant_chief', 'is_active' => true]);
    enableModules(['restaurant']);

    return $user;
}

function createPantryItem(string $name, string $unit = 'g'): RestaurantPantryItem
{
    return RestaurantPantryItem::create([
        'name'          => $name,
        'unit'          => $unit,
        'is_prepared'   => false,
        'cost_price'    => 100000,
        'average_cost'  => 100000,
        'current_stock' => 5000,
        'is_active'     => true,
    ]);
}

function createMenuItem(string $name, int $price = 600000): RestaurantMenuItem
{
    $cat = RestaurantMenuCategory::firstOrCreate(['name' => 'Plats'], ['is_active' => true]);

    return RestaurantMenuItem::create([
        'restaurant_menu_category_id' => $cat->id,
        'name'                        => $name,
        'price'                       => $price,
        'type'                        => 'food',
        'is_active'                   => true,
    ]);
}

test('le modele d\'exportation Excel des fiches techniques du restaurant est telechargeable par defaut', function () {
    $this->actingAs(recipeManager());

    $response = $this->get(route('restaurant.recipes.export', ['template' => 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('modele_fiches_techniques_restaurant.xlsx');
});

test('le modele d\'exportation CSV des fiches techniques du restaurant est telechargeable avec format=csv', function () {
    $this->actingAs(recipeManager());

    $response = $this->get(route('restaurant.recipes.export', ['template' => 1, 'format' => 'csv']))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('modele_fiches_techniques_restaurant.csv');
});

test('l\'exportation des fiches techniques inclut les plats et les preparations au format Excel et CSV', function () {
    $this->actingAs(recipeManager());

    $menuItem = createMenuItem('Ndolè aux crevettes');
    $pantryItem = createPantryItem('Crevettes fraîches');

    $recipe = RestaurantRecipe::create([
        'name'                    => 'Ndolè aux crevettes',
        'type'                    => RestaurantRecipe::TYPE_DISH,
        'restaurant_menu_item_id' => $menuItem->id,
        'yield_quantity'          => 1,
        'is_active'               => true,
    ]);

    RestaurantRecipeLine::create([
        'restaurant_recipe_id'      => $recipe->id,
        'restaurant_pantry_item_id' => $pantryItem->id,
        'quantity'                  => 250,
        'waste_percent'             => 10,
    ]);

    // Test Excel
    $responseXlsx = $this->get(route('restaurant.recipes.export'))->assertOk();
    expect($responseXlsx->headers->get('Content-Disposition'))->toContain('fiches_techniques_restaurant_')
        ->and($responseXlsx->headers->get('Content-Disposition'))->toContain('.xlsx');

    // Test CSV
    $responseCsv = $this->get(route('restaurant.recipes.export', ['format' => 'csv']))->assertOk();

    ob_start();
    $responseCsv->baseResponse->sendContent();
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', ob_get_clean());

    expect($csv)->toContain('Ndolè aux crevettes')
        ->and($csv)->toContain('plat')
        ->and($csv)->toContain('Crevettes fraîches')
        ->and($csv)->toContain('250');
});

test('l\'importation avec le modele complet cree le plat et la preparation avec tous leurs ingredients', function () {
    $this->actingAs(recipeManager());

    createMenuItem('Ndolé aux crevettes & plantains mûrs vapeur');
    createPantryItem('Viande de Boeuf', 'g');
    createPantryItem('Crevettes', 'g');
    createPantryItem('Plantain mûr', 'pcs');
    createPantryItem('Feuilles de ndolé', 'g');
    createPantryItem('Arachide décortiquée', 'g');
    createPantryItem('Oignon', 'g');
    createPantryItem('Huile', 'ml');
    createPantryItem('Crevettes séchées & épices', 'g');

    $csvContent = implode("\n", [
        "nom_fiche;type;plat_menu;article_produit;rendement;notes_fiche;ingredient;quantite;perte_pct;notes_ingredient",
        "\"Ndolé aux crevettes & plantains mûrs vapeur\";plat;\"Ndolé aux crevettes & plantains mûrs vapeur\";;1;;\"Sauce Ndole\";200;0;",
        "\"Ndolé aux crevettes & plantains mûrs vapeur\";plat;\"Ndolé aux crevettes & plantains mûrs vapeur\";;1;;\"Viande de Boeuf\";125;20;",
        "\"Ndolé aux crevettes & plantains mûrs vapeur\";plat;\"Ndolé aux crevettes & plantains mûrs vapeur\";;1;;Crevettes;50;0;",
        "\"Ndolé aux crevettes & plantains mûrs vapeur\";plat;\"Ndolé aux crevettes & plantains mûrs vapeur\";;1;;\"Plantain mûr\";2;0;",
        "\"Sauce Ndole\";preparation;;\"Sauce Ndole\";5000;;\"Feuilles de ndolé\";2000;0;",
        "\"Sauce Ndole\";preparation;;\"Sauce Ndole\";5000;;\"Arachide décortiquée\";1500;0;",
        "\"Sauce Ndole\";preparation;;\"Sauce Ndole\";5000;;Oignon;400;0;",
        "\"Sauce Ndole\";preparation;;\"Sauce Ndole\";5000;;Huile;400;0;",
        "\"Sauce Ndole\";preparation;;\"Sauce Ndole\";5000;;\"Crevettes séchées & épices\";100;0;",
    ]);

    $file = UploadedFile::fake()->createWithContent('recipes.csv', $csvContent);

    $response = $this->post(route('restaurant.recipes.import'), ['csv_file' => $file]);
    $response->assertRedirect(route('restaurant.recipes.index'));

    // Vérifie le plat
    $plat = RestaurantRecipe::where('name', 'Ndolé aux crevettes & plantains mûrs vapeur')->first();
    expect($plat)->not->toBeNull()
        ->and($plat->type)->toBe(RestaurantRecipe::TYPE_DISH)
        ->and($plat->lines)->toHaveCount(4);

    // Vérifie la préparation
    $prep = RestaurantRecipe::where('name', 'Sauce Ndole')->first();
    expect($prep)->not->toBeNull()
        ->and($prep->type)->toBe(RestaurantRecipe::TYPE_PREP)
        ->and((float) $prep->yield_quantity)->toBe(5000.0)
        ->and($prep->lines)->toHaveCount(5);
});

test('un ingredient inconnu dans le garde-manger produit une erreur d\'importation', function () {
    $this->actingAs(recipeManager());
    createMenuItem('Plat Inexistant');

    $csvContent = implode("\n", [
        "nom_fiche;type;plat_menu;article_produit;rendement;notes_fiche;ingredient;quantite;perte_pct;notes_ingredient",
        "Plat Inexistant;plat;Plat Inexistant;;1;;Ingredient Inconnu;100;0;",
    ]);

    $file = UploadedFile::fake()->createWithContent('recipes.csv', $csvContent);

    $response = $this->post(route('restaurant.recipes.import'), ['csv_file' => $file]);
    $response->assertRedirect(route('restaurant.recipes.index'));
    $response->assertSessionHas('import_errors');

    $errors = session('import_errors');
    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('introuvable');
});

test('l\'importation depuis un classeur Excel XLSX fonctionne parfaitement', function () {
    $this->actingAs(recipeManager());

    createMenuItem('Ndolé aux crevettes & plantains mûrs vapeur');
    createPantryItem('Sauce Ndole', 'g');
    createPantryItem('Viande de Boeuf', 'g');
    createPantryItem('Crevettes', 'g');
    createPantryItem('Plantain mûr', 'pcs');

    // Crée un vrai fichier XLSX avec PhpSpreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = ['nom_fiche', 'type', 'plat_menu', 'article_produit', 'rendement', 'notes_fiche', 'ingredient', 'quantite', 'perte_pct', 'notes_ingredient'];
    foreach ($headers as $idx => $h) {
        $sheet->setCellValue([$idx + 1, 1], $h);
    }

    $rows = [
        ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', 1, '', 'Sauce Ndole', 200, 0, ''],
        ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', 1, '', 'Viande de Boeuf', 125, 20, ''],
        ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', 1, '', 'Crevettes', 50, 0, ''],
        ['Ndolé aux crevettes & plantains mûrs vapeur', 'plat', 'Ndolé aux crevettes & plantains mûrs vapeur', '', 1, '', 'Plantain mûr', 2, 0, ''],
    ];

    foreach ($rows as $rIdx => $row) {
        foreach ($row as $cIdx => $val) {
            $sheet->setCellValue([$cIdx + 1, $rIdx + 2], $val);
        }
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($tempPath);

    $file = new UploadedFile($tempPath, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->post(route('restaurant.recipes.import'), ['excel_file' => $file]);
    $response->assertRedirect(route('restaurant.recipes.index'));

    $recipe = RestaurantRecipe::where('name', 'Ndolé aux crevettes & plantains mûrs vapeur')->first();
    expect($recipe)->not->toBeNull()
        ->and($recipe->type)->toBe(RestaurantRecipe::TYPE_DISH)
        ->and($recipe->lines)->toHaveCount(4);

    @unlink($tempPath);
});

