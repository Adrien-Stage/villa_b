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

test('le modele d\'exportation CSV des fiches techniques du restaurant est telechargeable', function () {
    $this->actingAs(recipeManager());

    $response = $this->get(route('restaurant.recipes.export', ['template' => 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('modele_fiches_techniques_restaurant.csv');
});

test('l\'exportation des fiches techniques inclut les plats et les preparations', function () {
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

    $response = $this->get(route('restaurant.recipes.export'))->assertOk();

    ob_start();
    $response->baseResponse->sendContent();
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', ob_get_clean());

    expect($csv)->toContain('Ndolè aux crevettes')
        ->and($csv)->toContain('plat')
        ->and($csv)->toContain('Crevettes fraîches')
        ->and($csv)->toContain('250');
});

test('l\'importation CSV des fiches techniques cree une fiche de plat avec ses ingredients', function () {
    $this->actingAs(recipeManager());

    createMenuItem('Poulet DG');
    createPantryItem('Poulet entier', 'pcs');
    createPantryItem('Plantain mûr', 'pcs');

    $csvContent = implode("\n", [
        "nom_fiche;type;plat_menu;article_produit;rendement;notes_fiche;ingredient;quantite;perte_pct;notes_ingredient",
        "Poulet DG;plat;Poulet DG;;1;Servir chaud;Poulet entier;1;0;découpé",
        "Poulet DG;plat;Poulet DG;;1;Servir chaud;Plantain mûr;4;5;frit",
    ]);

    $file = UploadedFile::fake()->createWithContent('recipes.csv', $csvContent);

    $this->post(route('restaurant.recipes.import'), ['csv_file' => $file])->assertRedirect(route('restaurant.recipes.index'));

    $recipe = RestaurantRecipe::where('name', 'Poulet DG')->first();
    expect($recipe)->not->toBeNull()
        ->and($recipe->type)->toBe(RestaurantRecipe::TYPE_DISH)
        ->and($recipe->menuItem->name)->toBe('Poulet DG');

    $lines = $recipe->lines;
    expect($lines)->toHaveCount(2);

    $pouletLine = $lines->firstWhere('item.name', 'Poulet entier');
    expect($pouletLine)->not->toBeNull()
        ->and((float) $pouletLine->quantity)->toBe(1.0);
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
