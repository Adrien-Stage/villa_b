<?php

namespace Tests\Feature;

use App\Models\PointOfSale;
use App\Models\RestaurantBarDrink;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantRecipePrintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PointOfSale $restaurant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TenantSeeder::class);

        $prop = new \ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
        $prop->setAccessible(true);
        $prop->setValue(null, ['restaurant']);

        $this->restaurant = PointOfSale::create([
            'name' => 'Restaurant Principal',
            'code' => 'RESTO-TEST',
            'slug' => 'restaurant-principal',
            'kind' => PointOfSale::KIND_RESTAURATION,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'role' => 'restaurant_chief',
            'is_active' => true,
        ]);
        $this->user->restaurants()->attach($this->restaurant->id);
    }

    public function test_can_print_single_kitchen_recipe(): void
    {
        $cat = RestaurantMenuCategory::create(['name' => 'Plats', 'is_active' => true]);
        $menuItem = RestaurantMenuItem::create([
            'point_of_sale_id' => $this->restaurant->id,
            'restaurant_menu_category_id' => $cat->id,
            'name' => 'Ndolé Royal',
            'price' => 500000,
            'type' => 'food',
            'is_active' => true,
        ]);

        $pantryItem = RestaurantPantryItem::create([
            'point_of_sale_id' => $this->restaurant->id,
            'name' => 'Feuilles de Ndolé',
            'unit' => 'kg',
            'current_stock' => 10,
            'average_cost' => 150000,
            'is_active' => true,
        ]);

        $recipe = RestaurantRecipe::create([
            'point_of_sale_id' => $this->restaurant->id,
            'name' => 'Ndolé Royal',
            'type' => RestaurantRecipe::TYPE_DISH,
            'restaurant_menu_item_id' => $menuItem->id,
            'yield_quantity' => 1,
            'notes' => 'Faire blanchir les feuilles avec soin.',
            'is_active' => true,
        ]);

        RestaurantRecipeLine::create([
            'restaurant_recipe_id' => $recipe->id,
            'restaurant_pantry_item_id' => $pantryItem->id,
            'quantity' => 0.5,
            'waste_percent' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('restaurant.recipes.print', $recipe));

        $response->assertOk();
        $response->assertSee('Ndolé Royal');
        $response->assertSee('FICHE TECHNIQUE CUISINE');
        $response->assertSee('Feuilles de Ndolé');
        $response->assertSee('Faire blanchir les feuilles avec soin.');
    }

    public function test_can_print_bar_drink_recipe(): void
    {
        $cat = RestaurantMenuCategory::create(['name' => 'Cocktails', 'is_active' => true]);
        $drinkItem = RestaurantMenuItem::create([
            'point_of_sale_id' => $this->restaurant->id,
            'restaurant_menu_category_id' => $cat->id,
            'name' => 'Daiquiri Passion',
            'price' => 450000,
            'type' => 'drink',
            'is_active' => true,
        ]);

        $stockCat = StockCategory::create(['name' => 'Alcools', 'is_active' => true]);
        $rum = StockItem::create([
            'stock_category_id' => $stockCat->id,
            'name' => 'Rhum Blanc 50°',
            'unit' => 'bouteille',
            'current_stock' => 12,
            'min_stock' => 2,
            'average_cost' => 850000,
            'is_active' => true,
        ]);

        RestaurantBarDrink::create([
            'restaurant_menu_item_id' => $drinkItem->id,
            'format' => 'cocktail',
            'ingredients' => [
                ['stock_item_id' => $rum->id, 'quantity' => 60, 'measure' => 'ml'],
            ],
            'instructions' => 'Frapper au shaker avec des glaçons et servir frais.',
        ]);

        $response = $this->actingAs($this->user)->get(route('restaurant.bar.menu.print', $drinkItem));

        $response->assertOk();
        $response->assertSee('Daiquiri Passion');
        $response->assertSee('FICHE TECHNIQUE BAR');
        $response->assertSee('Rhum Blanc 50°');
        $response->assertSee('Frapper au shaker avec des glaçons et servir frais.');
    }

    public function test_can_print_all_recipes_workbook(): void
    {
        $response = $this->actingAs($this->user)->get(route('restaurant.recipes.recueil'));

        $response->assertOk();
        $response->assertSee('RECUEIL DES FICHES TECHNIQUES');
        $response->assertSee('LIVRE DE RECETTES');
    }

    public function test_edition_catalog_includes_fiches_techniques(): void
    {
        $catalogue = app(\App\Editions\Catalogue::class);
        $edition = $catalogue->trouver('fiches-techniques-restaurant');

        $this->assertNotNull($edition);
        $this->assertEquals('Fiches techniques de restauration', $edition->titre());
        $this->assertTrue($edition->accessiblePour($this->user));

        $doc = $edition->document(['restaurant' => '', 'type' => ''], $this->user);
        $this->assertEquals('Fiches techniques de restauration', $doc->titre);
    }
}
