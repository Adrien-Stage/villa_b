<?php

use App\Models\PointOfSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuItem;
use App\Models\Role;
use App\Models\ServiceStoreMovement as Movement;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\RestaurantBarStock;
use App\Services\RestaurantContext;
use App\Services\RestaurantStockService;
use App\Services\ServiceStoreService;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant', 'economat', 'comptabilite', 'accounting']);
    $this->seed(TenantSeeder::class);
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();

    $this->restaurant = PointOfSale::where('slug', 'restaurant')->firstOrFail();
    $this->restaurant->update(['services' => ['salle', 'cuisine', 'bar', 'stock']]);
    $this->restaurant->synchroniserDepotsService();
    $this->depot = app(RestaurantBarStock::class)->storeFor($this->restaurant);

    $this->barman = equipeBar('restaurant_barman', $this->restaurant);
    $this->serveur = equipeBar('restaurant_staff', $this->restaurant);
    $this->caissier = equipeBar('cashier', $this->restaurant);
    $this->responsable = equipeBar('restaurant_manager', $this->restaurant);

    $category = StockCategory::create(['name' => 'Boissons', 'stock_account' => '311000']);
    $this->rhum = StockItem::create([
        'stock_category_id' => $category->id, 'name' => 'Rhum ambré', 'unit' => 'bouteille', 'is_active' => true,
    ]);

    $this->verre = produitBar($this->restaurant, 'Verre de rhum', 200000);
    $this->cocktail = produitBar($this->restaurant, 'Daiquiri', 350000);
    $this->plat = produitBar($this->restaurant, 'Côte de porc', 450000, 'food');

    $this->actingAs($this->barman)->put(route('restaurant.bar.configure', $this->verre), [
        'format' => 'glass', 'stock_item_id' => $this->rhum->id,
    ])->assertSessionHasNoErrors();
    $this->put(route('restaurant.bar.configure', $this->cocktail), [
        'format' => 'cocktail', 'instructions' => 'Secouer avec de la glace',
        'ingredients' => [['stock_item_id' => $this->rhum->id, 'measure' => 'ml', 'quantity' => 20]],
    ])->assertSessionHasNoErrors();
});

function equipeBar(string $role, PointOfSale $restaurant): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));
    $user->restaurants()->attach($restaurant);

    return $user;
}

function produitBar(PointOfSale $restaurant, string $name, int $price, string $type = 'drink'): RestaurantMenuItem
{
    return RestaurantMenuItem::create([
        'point_of_sale_id' => $restaurant->id, 'name' => $name, 'price' => $price, 'type' => $type, 'is_active' => true,
    ]);
}

function commandeBar(array $articles): array
{
    return ['table_number' => '7', 'items_json' => json_encode(array_map(fn ($article) => ['id' => $article->id, 'qty' => 1], $articles))];
}

test('une commande mixte trace 50 et 20 mL sur une bouteille et passe par la caisse commune', function () {
    app(ServiceStoreService::class)->receive($this->depot, $this->rhum, 2, 750000, Movement::SOURCE_REQUISITION);
    $this->get(route('restaurant.bar.depot.index'))->assertOk()->assertSee('Rhum ambré');
    $this->get(route('restaurant.bar.menu.index'))->assertOk()->assertSee('Daiquiri')->assertSee('20 mL');
    $ouverture = ['stock_item_id' => $this->rhum->id, 'capacity_ml' => 750, 'opening_key' => (string) Str::uuid()];

    $this->post(route('restaurant.bar.open_bottle'), $ouverture)->assertSessionHasNoErrors();
    $this->post(route('restaurant.bar.open_bottle'), $ouverture)->assertSessionHasNoErrors();
    $bouteille = $this->depot->barBottles()->sole();
    $stock = $this->depot->stocks()->sole();
    expect((float) $stock->current_stock)->toBe(1.0)
        ->and($bouteille->remaining_ml)->toBe(750)->and($this->depot->fresh()->stockValue())->toBe(1500000);
    expect(app(LedgerPostingService::class)->postServiceStoreStock(now()))->toBeNull();

    $this->actingAs($this->serveur)->post(route('restaurant.orders.store'), commandeBar([$this->plat, $this->verre, $this->cocktail]))
        ->assertSessionHasNoErrors();
    $order = RestaurantCustomerOrder::sole();
    app(RestaurantStockService::class)->deductForOrder($order);
    expect($order->items()->enCuisine()->count())->toBe(1)
        ->and($order->items()->auBar()->count())->toBe(2)
        ->and($order->food_cost)->toBe(70000)->and($bouteille->fresh()->remaining_ml)->toBe(680)
        ->and($this->depot->fresh()->stockValue())->toBe(1430000);

    $mouvements = $this->depot->movements()->where('source_type', Movement::SOURCE_BAR_SALE)->orderBy('id')->get();
    expect($mouvements->pluck('details.volume_ml')->all())->toBe([-50, -20])
        ->and($mouvements->pluck('details.remaining_ml')->all())->toBe([700, 680])
        ->and($mouvements->pluck('source_id')->unique()->all())->toBe([$order->id]);
    $journal = app(LedgerPostingService::class)->postServiceStoreStock(now());
    expect((int) $journal->lines()->sum('credit'))->toBe(70000)
        ->and(app(LedgerPostingService::class)->postServiceStoreStock(now()))->toBeNull();

    $this->actingAs($this->barman)->put(route('restaurant.bar.configure', $this->cocktail), [
        'format' => 'cocktail', 'instructions' => 'Nouvelle recette',
        'ingredients' => [['stock_item_id' => $this->rhum->id, 'measure' => 'ml', 'quantity' => 30]],
    ])->assertSessionHasNoErrors();
    $this->get(route('restaurant.bar.bottles.show', $bouteille))->assertOk()->assertSee('680 / 750 mL')->assertSee('-50 mL')->assertSee('-20 mL');
    $this->get(route('restaurant.bar.index'))->assertOk()->assertSee('Daiquiri')->assertSee('Secouer avec de la glace')
        ->assertSee('20 mL')->assertDontSee('Nouvelle recette')->assertDontSee('Côte de porc');
    $this->postJson(route('restaurant.billing.paid', $order), ['payment_method' => 'cash'])->assertForbidden();
    $this->post(route('restaurant.orders.bar_ready', $order))->assertSessionHasNoErrors();

    $this->actingAs($this->caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 20000])
        ->assertRedirect(route('restaurant.billing.index'));
    $this->post(route('restaurant.billing.paid', $order), ['payment_method' => 'cash'])->assertSessionHasNoErrors();
    expect($order->fresh()->amount_paid)->toBe(1000000)->and($bouteille->fresh()->remaining_ml)->toBe(680);
    $this->actingAs($this->barman)->get(route('restaurant.bar.sales.index'))->assertOk()
        ->assertSee('Daiquiri')->assertSee('3 500')->assertSee('Verre de rhum')->assertDontSee('Côte de porc');
});

test('un manque ne bloque pas la commande et son attribution puis son annulation rétablissent le bon stock', function () {
    app(ServiceStoreService::class)->receive($this->depot, $this->rhum, 2, 750000, Movement::SOURCE_REQUISITION);
    $bar = app(RestaurantBarStock::class);
    $this->actingAs($this->barman);
    $premiere = $bar->openBottle($this->depot, $this->rhum, 750, (string) Str::uuid());
    $bar->countBottle($this->depot, $premiere, 30, 'Volume compté');

    $this->actingAs($this->serveur)->post(route('restaurant.orders.store'), commandeBar([$this->verre]))
        ->assertSessionHasNoErrors()->assertSessionHas('stock_warning');
    $order = RestaurantCustomerOrder::sole();
    $manque = $bar->unallocated($this->depot)->sole();
    expect($premiere->fresh()->remaining_ml)->toBe(0)->and($manque['volume'])->toBe(20);

    $this->actingAs($this->barman);
    $suivante = $bar->openBottle($this->depot, $this->rhum, 750, (string) Str::uuid());
    $attribution = ['movement_id' => $manque['movement']->id, 'volume_ml' => 20, 'allocation_key' => (string) Str::uuid()];
    $this->post(route('restaurant.bar.allocate_volume', $suivante), $attribution)->assertSessionHasNoErrors();
    $this->post(route('restaurant.bar.allocate_volume', $suivante), $attribution)->assertSessionHasNoErrors();
    expect($bar->unallocated($this->depot))->toBeEmpty()->and($suivante->fresh()->remaining_ml)->toBe(730);

    $this->actingAs($this->responsable)->post(route('restaurant.orders.status', $order), ['status' => 'canceled'])->assertSessionHasNoErrors();
    expect($premiere->fresh()->remaining_ml)->toBe(30)->and($suivante->fresh()->remaining_ml)->toBe(750)
        ->and($bar->unallocated($this->depot))->toBeEmpty()->and($order->fresh()->food_cost)->toBeNull();
});

test('la désactivation du Bar conserve les bouteilles et ferme la saisie', function () {
    app(ServiceStoreService::class)->receive($this->depot, $this->rhum, 1, 750000, Movement::SOURCE_REQUISITION);
    $bouteille = app(RestaurantBarStock::class)->openBottle($this->depot, $this->rhum, 750, (string) Str::uuid());
    $admin = equipeBar('admin', $this->restaurant);
    $reglages = [
        'code' => $this->restaurant->code, 'name' => $this->restaurant->name, 'service_modes' => ['carte'], 'is_active' => 1,
    ];
    $this->actingAs($admin)->put(route('restaurant.restaurants.update', $this->restaurant), $reglages + ['services' => ['salle', 'cuisine', 'stock']])
        ->assertSessionHasNoErrors();
    expect($this->depot->fresh()->is_active)->toBeFalse();
    $this->actingAs($this->barman)->get(route('restaurant.bar.depot.index'))->assertOk()->assertSee("n'a pas de bar", false)->assertDontSee('Ouvrir une bouteille');
    $this->postJson(route('restaurant.bar.open_bottle'), [])->assertNotFound();
    $this->get(route('restaurant.bar.bottles.show', $bouteille))->assertNotFound();

    $this->actingAs($admin)->put(route('restaurant.restaurants.update', $this->restaurant), $reglages + ['services' => ['salle', 'cuisine', 'bar', 'stock']])
        ->assertSessionHasNoErrors();
    expect($this->depot->fresh()->is_active)->toBeTrue();
    expect($this->restaurant->fresh()->offre(PointOfSale::SERVICE_BAR))->toBeTrue();
    $this->actingAs($this->barman)->get(route('restaurant.bar.bottles.show', $bouteille))->assertOk()->assertSee('750 / 750 mL');
});

test('un barman ne gère pas une boisson ni une bouteille d’un autre restaurant et ne valide pas les écarts', function () {
    $autre = PointOfSale::create([
        'code' => 'R2', 'slug' => 'restaurant-2', 'name' => 'Restaurant 2', 'kind' => PointOfSale::KIND_RESTAURATION,
        'service_modes' => ['carte'], 'services' => ['salle', 'bar'], 'is_active' => true,
    ]);
    $autre->synchroniserDepotsService();
    app(RestaurantContext::class)->oublier();
    $service = app(RestaurantBarStock::class);
    $depot = $service->storeFor($autre);
    app(ServiceStoreService::class)->receive($depot, $this->rhum, 1, 750000, Movement::SOURCE_REQUISITION);
    $autreBouteille = $service->openBottle($depot, $this->rhum, 750, (string) Str::uuid());
    $autreProduit = produitBar($autre, 'Bière locale', 200000);

    $this->get(route('restaurant.bar.bottles.show', $autreBouteille))->assertNotFound();
    $this->putJson(route('restaurant.bar.configure', $autreProduit), ['format' => 'bottle', 'stock_item_id' => $this->rhum->id])->assertNotFound();
    $this->postJson(route('restaurant.bar.count_bottle', $autreBouteille), ['volume_ml' => 0, 'reason' => 'Perte'])->assertForbidden();
});

test('une bouteille entière et un mocktail sortent du dépôt, et une boisson préparée reste consommée', function () {
    $eau = StockItem::create(['name' => 'Eau pétillante', 'unit' => 'bouteille', 'is_active' => true]);
    $citron = StockItem::create(['name' => 'Citron', 'unit' => 'pièce', 'is_active' => true]);
    $mocktail = produitBar($this->restaurant, 'Citronnade pétillante', 300000);
    $bouteilleEntiere = produitBar($this->restaurant, 'Eau pétillante entière', 200000);

    $this->put(route('restaurant.bar.configure', $mocktail), [
        'format' => 'mocktail',
        'ingredients' => [
            ['stock_item_id' => $eau->id, 'measure' => 'ml', 'quantity' => 120],
            ['stock_item_id' => $citron->id, 'measure' => 'stock', 'quantity' => 1],
        ],
    ])->assertSessionHasNoErrors();
    $this->put(route('restaurant.bar.configure', $bouteilleEntiere), [
        'format' => 'bottle', 'stock_item_id' => $eau->id,
    ])->assertSessionHasNoErrors();

    $stock = app(ServiceStoreService::class);
    $stock->receive($this->depot, $eau, 3, 75000, Movement::SOURCE_REQUISITION);
    $stock->receive($this->depot, $citron, 3, 4000, Movement::SOURCE_REQUISITION);
    $ouverte = app(RestaurantBarStock::class)->openBottle($this->depot, $eau, 750, (string) Str::uuid());

    $this->actingAs($this->serveur)->post(route('restaurant.orders.store'), commandeBar([$mocktail, $bouteilleEntiere]))
        ->assertSessionHasNoErrors();
    $order = RestaurantCustomerOrder::sole();

    $this->actingAs($this->barman)->post(route('restaurant.orders.bar_ready', $order))->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe('ready')
        ->and($this->serveur->notifications()->first()->data['title'])->toBe('Boissons prêtes à servir');
    $this->actingAs($this->responsable)->post(route('restaurant.orders.status', $order), ['status' => 'canceled'])
        ->assertSessionHasNoErrors();

    expect($ouverte->fresh()->remaining_ml)->toBe(630)
        ->and((float) $this->depot->stocks()->where('stock_item_id', $eau->id)->value('current_stock'))->toBe(1.0)
        ->and((float) $this->depot->stocks()->where('stock_item_id', $citron->id)->value('current_stock'))->toBe(2.0)
        ->and($order->fresh()->food_cost)->toBe(91000);
    $this->post(route('restaurant.bar.count_bottle', $ouverte), ['volume_ml' => 620, 'reason' => 'Écart à la mesure'])
        ->assertSessionHasNoErrors();
    expect($ouverte->fresh()->remaining_ml)->toBe(620)
        ->and($this->depot->movements()->where('source_type', Movement::SOURCE_BAR_COUNT)->sole()->details['volume_ml'])->toBe(-10);
});

test('le barman demande une livraison à son dépôt par le circuit de l’économat', function () {
    $this->get(route('restaurant.bar.depot.index'))->assertOk()->assertSee('Demander un approvisionnement');
    $this->get(route('economat.requisitions.create', ['depot' => $this->depot->id]))->assertOk()
        ->assertViewHas('depotParDefaut', fn ($store) => $store?->id === $this->depot->id);
    $this->post(route('economat.requisitions.store'), [
        'department' => 'restaurant', 'service_store_id' => $this->depot->id,
        'lines' => [['stock_item_id' => $this->rhum->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $demande = \App\Models\StockRequisition::sole();
    expect($demande->service_store_id)->toBe($this->depot->id)
        ->and($demande->point_of_sale_id)->toBe($this->restaurant->id)
        ->and($demande->status)->toBe(\App\Models\StockRequisition::STATUS_AWAITING_ENDORSEMENT);
});
