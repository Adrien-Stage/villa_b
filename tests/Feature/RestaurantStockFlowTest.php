<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BreakfastEntitlement;
use App\Models\Customer;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Models\RestaurantWasteLog;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BreakfastPricingService;
use App\Services\RestaurantStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantStockFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $chief;
    private User $cook;
    private User $server;
    private User $manager;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        activerModules(['restaurant']);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Hôtel Zingana',
            'slug' => 'hotel-zingana',
        ]);

        $this->chief = User::factory()->create(['role' => 'restaurant_chief', 'tenant_id' => $this->tenant->id]);
        $this->chief->roles()->sync(\App\Models\Role::where('slug', 'restaurant_chief')->pluck('id'));

        $this->cook = User::factory()->create(['role' => 'restaurant_cook', 'tenant_id' => $this->tenant->id]);
        $this->cook->roles()->sync(\App\Models\Role::where('slug', 'restaurant_cook')->pluck('id'));

        $this->server = User::factory()->create(['role' => 'restaurant_staff', 'tenant_id' => $this->tenant->id]);
        $this->server->roles()->sync(\App\Models\Role::where('slug', 'restaurant_staff')->pluck('id'));

        $this->manager = User::factory()->create(['role' => 'manager', 'tenant_id' => $this->tenant->id]);
        $this->manager->roles()->sync(\App\Models\Role::where('slug', 'manager')->pluck('id'));
    }

    public function test_recipe_theoretical_deduction_on_order_creation(): void
    {
        $category = RestaurantPantryCategory::create(['name' => 'Épicerie']);

        // Ingrédient A : Farine (1 000 FCFA/kg = 100 000 centimes/kg)
        $farine = RestaurantPantryItem::create([
            'restaurant_pantry_category_id' => $category->id,
            'name' => 'Farine de blé',
            'unit' => 'kg',
            'current_stock' => 10.0,
            'average_cost' => 100000, // 1 000 FCFA
            'is_active' => true,
        ]);

        // Ingrédient B : Œufs (150 FCFA/pcs = 15 000 centimes/pcs)
        $oeufs = RestaurantPantryItem::create([
            'restaurant_pantry_category_id' => $category->id,
            'name' => 'Œufs frais',
            'unit' => 'pcs',
            'current_stock' => 50.0,
            'average_cost' => 15000, // 150 FCFA
            'is_active' => true,
        ]);

        // Plat : Crêpes sucrées (prix de vente 3 000 FCFA = 300 000 centimes)
        $dish = RestaurantMenuItem::create([
            'name' => 'Assiette de crêpes',
            'price' => 300000,
            'is_active' => true,
        ]);

        // Fiche technique : 1 portion = 0.2 kg farine + 2 oeufs
        $recipe = RestaurantRecipe::create([
            'name' => 'Recette Crêpes',
            'type' => RestaurantRecipe::TYPE_DISH,
            'restaurant_menu_item_id' => $dish->id,
            'yield_quantity' => 1.0,
            'is_active' => true,
        ]);

        RestaurantRecipeLine::create([
            'restaurant_recipe_id' => $recipe->id,
            'restaurant_pantry_item_id' => $farine->id,
            'quantity' => 0.200,
            'waste_percent' => 0,
        ]);

        RestaurantRecipeLine::create([
            'restaurant_recipe_id' => $recipe->id,
            'restaurant_pantry_item_id' => $oeufs->id,
            'quantity' => 2.0,
            'waste_percent' => 0,
        ]);

        // Passage d'une commande de 3 portions
        $response = $this->actingAs($this->server)->post(route('restaurant.orders.store'), [
            'table_number' => 'T12',
            'customer_name' => 'M. Kamga',
            'order_type' => 'standard',
            'items_json' => json_encode([
                ['id' => $dish->id, 'qty' => 3],
            ]),
        ]);

        $response->assertRedirect();

        $order = RestaurantCustomerOrder::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals(900000, $order->total_amount); // 3 * 3 000 FCFA
        $this->assertTrue($order->stockWasDeducted());

        // Vérification des stocks après déduction théorique :
        // Farine : 10.0 - (0.2 * 3) = 9.4 kg
        $farine->refresh();
        $this->assertEquals(9.400, (float) $farine->current_stock);

        // Œufs : 50 - (2 * 3) = 44 pièces
        $oeufs->refresh();
        $this->assertEquals(44.0, (float) $oeufs->current_stock);

        // Vérification du food cost de la commande :
        // Farine : 0.6 kg * 100 000 = 60 000 centimes
        // Œufs : 6 pcs * 15 000 = 90 000 centimes
        // Total food cost = 150 000 centimes (1 500 FCFA)
        $this->assertEquals(150000, $order->food_cost);
        $this->assertEquals(750000, $order->margin()); // 900 000 - 150 000
    }

    public function test_complimentary_order_deducts_stock_with_zero_billed_amount(): void
    {
        $category = RestaurantPantryCategory::create(['name' => 'Boissons']);

        $cafe = RestaurantPantryItem::create([
            'restaurant_pantry_category_id' => $category->id,
            'name' => 'Café moulu arabica',
            'unit' => 'kg',
            'current_stock' => 5.0,
            'average_cost' => 800000, // 8 000 FCFA / kg
            'is_active' => true,
        ]);

        $dish = RestaurantMenuItem::create([
            'name' => 'Espresso simple',
            'price' => 150000, // 1 500 FCFA
            'is_active' => true,
        ]);

        $recipe = RestaurantRecipe::create([
            'name' => 'Espresso',
            'type' => RestaurantRecipe::TYPE_DISH,
            'restaurant_menu_item_id' => $dish->id,
            'yield_quantity' => 1.0,
            'is_active' => true,
        ]);

        RestaurantRecipeLine::create([
            'restaurant_recipe_id' => $recipe->id,
            'restaurant_pantry_item_id' => $cafe->id,
            'quantity' => 0.010, // 10 g
            'waste_percent' => 0,
        ]);

        // Commande offerte (geste commercial VIP)
        $response = $this->actingAs($this->server)->post(route('restaurant.orders.store'), [
            'table_number' => 'VIP-1',
            'customer_name' => 'Client VIP',
            'order_type' => 'complimentary',
            'is_complimentary' => true,
            'complimentary_reason' => 'Attente prolongée check-in',
            'items_json' => json_encode([
                ['id' => $dish->id, 'qty' => 2],
            ]),
        ]);

        $response->assertRedirect();

        $order = RestaurantCustomerOrder::latest('id')->first();
        $this->assertEquals(0, $order->total_amount);
        $this->assertTrue($order->isComplimentary());
        $this->assertEquals('paid', $order->payment_status);
        $this->assertTrue($order->stockWasDeducted());

        // Stock café déduit : 5.0 - (0.010 * 2) = 4.980 kg
        $cafe->refresh();
        $this->assertEquals(4.980, (float) $cafe->current_stock);

        // Food cost tracé : 0.020 kg * 800 000 = 16 000 centimes (160 FCFA)
        $this->assertEquals(16000, $order->food_cost);
        $this->assertEquals(-16000, $order->margin()); // Perte nette du coût matière
    }

    public function test_breakfast_pointage_triggers_order_and_stock_deduction(): void
    {
        $roomType = RoomType::create([
            'code' => 'DLX',
            'name' => 'Chambre Deluxe',
            'base_capacity' => 2,
            'max_capacity' => 2,
            'base_price' => 5000000,
            'includes_breakfast' => true,
            'allows_extra_bed' => false,
            'extra_bed_price' => 0,
        ]);

        $room = Room::create([
            'room_type_id' => $roomType->id,
            'number' => '104',
            'status' => 'occupied',
            'price_per_night' => 5000000,
        ]);

        $customer = Customer::create([
            'first_name' => 'Samuel',
            'last_name' => 'Eto\'o',
            'email' => 'samuel@eto.cm',
            'phone' => '+237699000001',
        ]);

        $booking = Booking::create([
            'booking_number' => 'BKG-2026-0001',
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'price_per_night' => 5000000,
            'total_room_amount' => 10000000,
            'total_nights' => 2,
            'adults' => 2,
            'children_count' => 0,
            'total_amount' => 10000000,
            'paid_amount' => 10000000,
            'status' => 'checked_in',
        ]);

        $entitlement = BreakfastEntitlement::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'service_date' => now()->toDateString(),
            'adults_included' => 2,
            'children_included' => 0,
            'status' => BreakfastEntitlement::STATUS_AVAILABLE,
            'tenant_id' => $this->tenant->id,
        ]);

        // Mise en place de l'ingrédient et de la fiche technique du Petit-déjeuner
        $pantryItem = RestaurantPantryItem::create([
            'name' => 'Pain baguette',
            'unit' => 'pcs',
            'current_stock' => 30.0,
            'average_cost' => 20000, // 200 FCFA
            'is_active' => true,
        ]);

        $breakfastItem = RestaurantMenuItem::create([
            'name' => 'Petit-déjeuner buffet adulte',
            'price' => 500000,
            'meal_services' => ['breakfast'],
            'is_active' => true,
        ]);

        $recipe = RestaurantRecipe::create([
            'name' => 'Recette PDJ',
            'type' => RestaurantRecipe::TYPE_DISH,
            'restaurant_menu_item_id' => $breakfastItem->id,
            'yield_quantity' => 1.0,
            'is_active' => true,
        ]);

        RestaurantRecipeLine::create([
            'restaurant_recipe_id' => $recipe->id,
            'restaurant_pantry_item_id' => $pantryItem->id,
            'quantity' => 1.0,
            'waste_percent' => 0,
        ]);

        // Le restaurant pointe 2 adultes servis (tous les deux inclus)
        $pricingService = app(BreakfastPricingService::class);
        $result = $pricingService->recordPointage(
            entitlement: $entitlement,
            adultsServed: 2,
            childrenServed: 0,
            settlementMethod: 'room_charge',
            userId: $this->chief->id,
            notes: 'Service buffet matin'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['covered_adults']);
        $this->assertEquals(0, $result['extra_amount_fcfa']);
        $this->assertNotNull($result['order_id']);

        // Vérification de la commande créée automatiquement
        $order = RestaurantCustomerOrder::find($result['order_id']);
        $this->assertEquals(RestaurantCustomerOrder::ORDER_TYPE_BREAKFAST_INCLUDED, $order->order_type);
        $this->assertEquals(0, $order->total_amount);
        $this->assertTrue($order->stockWasDeducted());

        // Le pain a été déduit théoriquement : 30.0 - 2 = 28.0 baguettes
        $pantryItem->refresh();
        $this->assertEquals(28.0, (float) $pantryItem->current_stock);
        $this->assertEquals(40000, $order->food_cost); // 2 * 200 FCFA = 400 FCFA
    }

    public function test_waste_log_creation_and_stock_reduction(): void
    {
        $item = RestaurantPantryItem::create([
            'name' => 'Tomates fraîches',
            'unit' => 'kg',
            'current_stock' => 15.0,
            'average_cost' => 50000, // 500 FCFA / kg
            'is_active' => true,
        ]);

        $stockService = app(RestaurantStockService::class);

        $wasteLog = $stockService->recordWaste(
            item: $item,
            quantity: 3.5,
            reason: RestaurantWasteLog::REASON_SPOILAGE,
            department: RestaurantWasteLog::DEPT_KITCHEN,
            responsiblePerson: 'Chef Eric',
            notes: 'Tomates gâtées suite à chaleur',
            tenantId: $this->tenant->id,
        );

        $this->assertNotNull($wasteLog);
        $this->assertEquals(RestaurantWasteLog::REASON_SPOILAGE, $wasteLog->reason);
        $this->assertEquals(3.5, (float) $wasteLog->quantity);
        $this->assertEquals(175000, $wasteLog->total_cost); // 3.5 * 500 FCFA = 1 750 FCFA
        $this->assertEquals('1 750 FCFA', $wasteLog->formattedTotalCost());

        // Stock après perte : 15.0 - 3.5 = 11.5 kg
        $item->refresh();
        $this->assertEquals(11.5, (float) $item->current_stock);

        // Mouvement associé
        $movement = RestaurantPantryMovement::where('restaurant_waste_log_id', $wasteLog->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(RestaurantPantryMovement::TYPE_OUT, $movement->type);
        $this->assertEquals(RestaurantPantryMovement::REASON_WASTE, $movement->reason);
        $this->assertEquals(175000, $movement->total_cost);
    }

    public function test_waste_controller_http_endpoints_and_print(): void
    {
        $item = RestaurantPantryItem::create([
            'name' => 'Filet de bœuf',
            'unit' => 'kg',
            'current_stock' => 8.0,
            'average_cost' => 350000, // 3 500 FCFA / kg
            'is_active' => true,
        ]);

        // Saisie via HTTP POST
        $response = $this->actingAs($this->chief)->post(route('restaurant.waste.store'), [
            'restaurant_pantry_item_id' => $item->id,
            'quantity' => 1.2,
            'reason' => RestaurantWasteLog::REASON_BURNT,
            'department' => RestaurantWasteLog::DEPT_KITCHEN,
            'responsible_person' => 'Cuisinier Junior',
            'notes' => 'Viande brûlée au fourneau',
        ]);

        $response->assertRedirect();

        $wasteLog = RestaurantWasteLog::latest('id')->first();
        $this->assertNotNull($wasteLog);
        $this->assertEquals(RestaurantWasteLog::REASON_BURNT, $wasteLog->reason);
        $this->assertEquals(420000, $wasteLog->total_cost); // 1.2 * 3 500 = 4 200 FCFA

        // Vérification de l'écran d'affichage (show)
        $showRes = $this->actingAs($this->chief)->get(route('restaurant.waste.show', $wasteLog));
        $showRes->assertOk();
        $showRes->assertSee($wasteLog->reference);
        $showRes->assertSee('Filet de bœuf');

        // Vérification de l'impression du PV de perte (print)
        $printRes = $this->actingAs($this->chief)->get(route('restaurant.waste.print', $wasteLog));
        $printRes->assertOk();
        $printRes->assertSee('PV de Mise au Rebut');
        $printRes->assertSee('4 200 FCFA');
    }

    public function test_cannot_record_waste_with_invalid_quantity(): void
    {
        $item = RestaurantPantryItem::create([
            'name' => 'Lait entier',
            'unit' => 'l',
            'current_stock' => 10.0,
            'average_cost' => 80000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->chief)->post(route('restaurant.waste.store'), [
            'restaurant_pantry_item_id' => $item->id,
            'quantity' => 0, // Invalide
            'reason' => RestaurantWasteLog::REASON_SPOILAGE,
            'department' => 'cuisine',
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_consumption_report_computes_ratios(): void
    {
        $stockService = app(RestaurantStockService::class);

        $report = $stockService->getKitchenConsumptionReport(
            startDate: now()->startOfMonth(),
            endDate: now()->endOfMonth(),
            tenantId: $this->tenant->id,
        );

        $this->assertIsArray($report);
        $this->assertArrayHasKey('food_cost_ratio', $report);
        $this->assertArrayHasKey('theoretical_sales_cost', $report);
        $this->assertArrayHasKey('waste_cost', $report);

        // Test de la vue HTTP
        $response = $this->actingAs($this->manager)->get(route('restaurant.consumption.index'));
        $response->assertOk();
        $response->assertSee('Food Cost');
        $response->assertSee('Rapprochement');
    }
}
