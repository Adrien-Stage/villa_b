<?php

/**
 * Restaurants multiples.
 *
 * L'hôtel crée autant de restaurants qu'il en exploite. Chacun a sa carte, sa
 * cuisine et son bar, son garde-manger, sa caisse et son équipe ; chacun sert
 * à la carte, au buffet, ou les deux ; un banquet se tient dans l'un ou
 * l'autre. Le personnel ne voit que les restaurants où il est affecté ; la
 * direction et le contrôle les voient tous ; la réception voit, partout, les
 * seules notes reportées sur un séjour.
 */

use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Department;
use App\Models\FolioItem;
use App\Models\PointOfSale;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBuffetEntry;
use App\Models\RestaurantBuffetService;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantStockCount;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Space;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\CountSheetService;
use App\Services\RestaurantContext;
use App\Services\StockRequisitionService;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Carbon\Carbon;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant', 'comptabilite', 'accounting', 'economat']);
    $this->seed(TenantSeeder::class);
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();

    $this->origine = PointOfSale::where('slug', 'restaurant')->first();
    $this->origine->update(['name' => 'Le Jardin', 'service_modes' => ['carte']]);
    $this->kotibe = PointOfSale::create([
        'code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe', 'kind' => PointOfSale::KIND_RESTAURATION,
        'series_prefix' => 'KOT-', 'service_modes' => ['carte', 'buffet'], 'is_active' => true, 'sort_order' => 4,
    ]);
});

/** Un membre du personnel, affecté aux restaurants donnés. */
function equipierDe(string $role, array $restaurants, string $nom = 'Agent'): User
{
    $user = User::factory()->create(['role' => $role, 'name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));
    $user->restaurants()->sync(collect($restaurants)->map(fn (PointOfSale $r) => $r->id)->all());

    return $user;
}

function articleDe(PointOfSale $restaurant, string $nom, string $type = 'food', int $prix = 250000): RestaurantMenuItem
{
    return RestaurantMenuItem::create([
        'point_of_sale_id' => $restaurant->id, 'name' => $nom, 'price' => $prix, 'type' => $type, 'is_active' => true,
    ]);
}

/** @param list<RestaurantMenuItem> $articles */
function commandeDe(PointOfSale $restaurant, string $table, array $articles, array $attributs = []): RestaurantCustomerOrder
{
    $commande = RestaurantCustomerOrder::create($attributs + [
        'point_of_sale_id' => $restaurant->id, 'table_number' => $table, 'status' => 'confirmed',
        'total_amount' => collect($articles)->sum('price'), 'payment_status' => 'pending',
        'placed_at' => now(), 'sent_to_kitchen_at' => now(),
    ]);

    foreach ($articles as $article) {
        RestaurantCustomerOrderItem::create([
            'restaurant_customer_order_id' => $commande->id, 'menu_item_id' => $article->id, 'item_name' => $article->name,
            'quantity' => 1, 'unit_price' => $article->price, 'total_price' => $article->price,
        ]);
    }

    return $commande;
}

function sejourEnCours(): Booking
{
    $type = RoomType::create(['name' => 'Deluxe', 'code' => 'DLX', 'base_capacity' => 2, 'max_capacity' => 2, 'base_price' => 4000000]);
    $chambre = Room::create(['number' => '204', 'room_type_id' => $type->id, 'floor' => 2, 'status' => 'occupied']);
    $client = Customer::create(['first_name' => 'Aïcha', 'last_name' => 'Mbarga']);

    return Booking::create([
        'booking_number' => 'BKG-MULTI-1', 'customer_id' => $client->id, 'room_id' => $chambre->id,
        'check_in' => Carbon::today(), 'check_out' => Carbon::today()->addDays(2),
        'price_per_night' => 4000000, 'total_room_amount' => 8000000, 'total_nights' => 2,
        'total_amount' => 8000000, 'paid_amount' => 0, 'balance_due' => 8000000, 'status' => 'checked_in',
    ]);
}

function directionGenerale(string $role = 'manager'): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user;
}

// ── Cloisonnement ─────────────────────────────────────────────────────────

test('chaque restaurant a sa carte : le serveur prend commande sur celle du sien', function () {
    articleDe($this->origine, 'Poulet DG');
    articleDe($this->kotibe, 'Ndolè royal');

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]))
        ->get(route('restaurant.menus.index'))
        ->assertOk()
        ->assertSee('Ndolè royal')
        ->assertDontSee('Poulet DG');
});

test('une commande ne prend que la carte du restaurant du serveur, et y est enregistrée', function () {
    $poulet = articleDe($this->origine, 'Poulet DG');
    $ndole = articleDe($this->kotibe, 'Ndolè royal');

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]));

    $this->post(route('restaurant.orders.store'), ['table_number' => '4', 'items_json' => json_encode([['id' => $poulet->id, 'qty' => 1]])])
        ->assertSessionHasErrors('items');

    $this->post(route('restaurant.orders.store'), ['table_number' => '4', 'items_json' => json_encode([['id' => $ndole->id, 'qty' => 2]])])
        ->assertSessionHasNoErrors();

    expect(RestaurantCustomerOrder::sole()->point_of_sale_id)->toBe($this->kotibe->id);
});

test("le personnel ne voit ni n'ouvre les commandes d'un autre restaurant", function () {
    $chezLeVoisin = commandeDe($this->origine, 'T-JARDIN', [articleDe($this->origine, 'Poulet DG')]);
    commandeDe($this->kotibe, 'T-KOTIBE', [articleDe($this->kotibe, 'Ndolè royal')]);

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]));

    $this->get(route('restaurant.orders.index'))->assertOk()->assertSee('T-KOTIBE')->assertDontSee('T-JARDIN');
    $this->get(route('restaurant.orders.show', $chezLeVoisin))->assertNotFound();
});

test('la direction voit les restaurants ensemble, ou un par un', function () {
    commandeDe($this->origine, 'T-JARDIN', [articleDe($this->origine, 'Poulet DG')]);
    commandeDe($this->kotibe, 'T-KOTIBE', [articleDe($this->kotibe, 'Ndolè royal')]);

    $this->actingAs(directionGenerale());

    $this->get(route('restaurant.orders.index'))->assertOk()->assertSee('T-KOTIBE')->assertSee('T-JARDIN');

    $this->post(route('restaurant.courant'), ['restaurant' => $this->kotibe->id]);
    $this->get(route('restaurant.orders.index'))->assertSee('T-KOTIBE')->assertDontSee('T-JARDIN');

    $this->post(route('restaurant.courant'), ['restaurant' => 'tous']);
    $this->get(route('restaurant.orders.index'))->assertSee('T-JARDIN');
});

test("on ne choisit pas un restaurant où l'on n'est pas affecté", function () {
    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]))
        ->post(route('restaurant.courant'), ['restaurant' => $this->origine->id])
        ->assertSessionHas('error');
});

test('la réception voit, dans tous les restaurants, les seules notes reportées sur un séjour', function () {
    $sejour = sejourEnCours();
    commandeDe($this->origine, 'T-RESIDENT-J', [articleDe($this->origine, 'Poulet DG')], ['booking_id' => $sejour->id, 'status' => 'served']);
    commandeDe($this->kotibe, 'T-RESIDENT-K', [articleDe($this->kotibe, 'Ndolè royal')], ['booking_id' => $sejour->id, 'status' => 'served']);
    $dePassage = commandeDe($this->kotibe, 'T-PASSAGE', [articleDe($this->kotibe, 'Jus de bissap', 'drink')], ['status' => 'served']);

    $this->actingAs(directionGenerale('reception'));

    $this->get(route('restaurant.billing.index'))->assertOk()
        ->assertSee('T-RESIDENT-J')->assertSee('T-RESIDENT-K')->assertDontSee('T-PASSAGE');
    $this->get(route('restaurant.billing.show', $dePassage))->assertNotFound();
});

test("un établissement d'un seul restaurant ne cloisonne rien", function () {
    $this->kotibe->update(['is_active' => false]);
    commandeDe($this->origine, 'T-JARDIN', [articleDe($this->origine, 'Poulet DG')]);

    // Un serveur que personne n'a encore affecté voit son restaurant, comme avant.
    $this->actingAs(equipierDe('restaurant_staff', []))
        ->get(route('restaurant.orders.index'))->assertOk()->assertSee('T-JARDIN');
});

// ── Cuisine et bar ────────────────────────────────────────────────────────

test('la cuisine reçoit les plats, le bar les boissons', function () {
    commandeDe($this->kotibe, 'T7', [articleDe($this->kotibe, 'Ndolè royal'), articleDe($this->kotibe, 'Jus de bissap', 'drink')]);

    expect(RestaurantCustomerOrderItem::where('item_name', 'Jus de bissap')->value('station'))->toBe('bar');

    $this->actingAs(equipierDe('restaurant_cook', [$this->kotibe]))
        ->get(route('restaurant.kitchen.index'))->assertOk()->assertSee('Ndolè royal')->assertDontSee('Jus de bissap');

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe], 'Barman'))
        ->get(route('restaurant.bar.index'))->assertOk()->assertSee('Jus de bissap')->assertDontSee('Ndolè royal');
});

test('une commande de boissons seules est prête quand le bar les a servies', function () {
    $commande = commandeDe($this->kotibe, 'T8', [articleDe($this->kotibe, 'Jus de bissap', 'drink')]);

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]))
        ->post(route('restaurant.orders.bar_ready', $commande))->assertSessionHasNoErrors();

    expect($commande->fresh()->status)->toBe(RestaurantCustomerOrder::STATUS_READY)
        ->and($commande->items()->first()->ready_at)->not->toBeNull();

    $this->get(route('restaurant.bar.index'))->assertDontSee('Jus de bissap');
});

test("le bar d'un restaurant ne sert pas les boissons de l'autre", function () {
    $commande = commandeDe($this->origine, 'T2', [articleDe($this->origine, 'Jus de bissap', 'drink')]);

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]))
        ->post(route('restaurant.orders.bar_ready', $commande))->assertNotFound();
});

// ── Carte, garde-manger, inventaires ──────────────────────────────────────

test('deux restaurants peuvent servir chacun leur article du même nom', function () {
    $chef = equipierDe('restaurant_manager', [$this->origine, $this->kotibe]);
    $this->actingAs($chef);

    foreach ([$this->origine, $this->kotibe] as $restaurant) {
        $this->post(route('restaurant.courant'), ['restaurant' => $restaurant->id]);
        $this->post(route('restaurant.menus.items.store'), ['name' => 'Coca-Cola', 'price' => 1000, 'type' => 'drink'])
            ->assertSessionHasNoErrors();
    }

    expect(RestaurantMenuItem::where('name', 'Coca-Cola')->pluck('point_of_sale_id')->sort()->values()->all())
        ->toBe(collect([$this->origine->id, $this->kotibe->id])->sort()->values()->all());
});

test("la formule buffet au couvert n'existe que là où l'on sert au buffet", function () {
    $this->actingAs(equipierDe('restaurant_manager', [$this->origine]));
    $this->post(route('restaurant.menus.items.store'), ['name' => 'Formule buffet', 'price' => 15000, 'type' => 'buffet'])
        ->assertSessionHasErrors('type');

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe], 'Chef Kotibe'));
    $this->post(route('restaurant.menus.items.store'), ['name' => 'Formule buffet', 'price' => 15000, 'type' => 'buffet'])
        ->assertSessionHasNoErrors();

    expect(RestaurantMenuItem::where('type', 'buffet')->sole()->point_of_sale_id)->toBe($this->kotibe->id);
});

test("depuis la vue d'ensemble, on choisit d'abord le restaurant dont on compose la carte", function () {
    $this->actingAs(equipierDe('restaurant_manager', [$this->origine, $this->kotibe]));
    $this->post(route('restaurant.courant'), ['restaurant' => 'tous']);

    $this->post(route('restaurant.menus.items.store'), ['name' => 'Coca-Cola', 'price' => 1000, 'type' => 'drink'])
        ->assertSessionHasErrors('restaurant');
    expect(RestaurantMenuItem::count())->toBe(0);
});

test('chaque restaurant compte son garde-manger', function () {
    foreach ([$this->origine, $this->kotibe] as $restaurant) {
        RestaurantPantryItem::create([
            'point_of_sale_id' => $restaurant->id, 'name' => 'Riz '.$restaurant->code, 'unit' => 'kg',
            'current_stock' => 10, 'average_cost' => 50000, 'min_stock' => 0, 'is_active' => true,
        ]);
    }

    $this->actingAs(equipierDe('restaurant_chief', [$this->kotibe]))
        ->post(route('restaurant.stock_counts.store'))->assertSessionHasNoErrors();
    $this->actingAs(equipierDe('restaurant_chief', [$this->origine], 'Chef Jardin'))
        ->post(route('restaurant.stock_counts.store'))->assertSessionHasNoErrors();

    $inventaire = RestaurantStockCount::where('point_of_sale_id', $this->kotibe->id)->sole();
    expect($inventaire->lines()->with('item')->get()->pluck('item.name')->all())->toBe(['Riz KOT'])
        ->and(RestaurantStockCount::count())->toBe(2);
});

test("une livraison de l'économat entre dans le garde-manger du restaurant qui l'a demandée", function () {
    $this->actingAs(directionGenerale('econome'));
    $farine = StockItem::create(['name' => 'Farine', 'unit' => 'kg', 'current_stock' => 100, 'average_cost' => 50000]);
    $auJardin = RestaurantPantryItem::create([
        'point_of_sale_id' => $this->origine->id, 'name' => 'Farine', 'unit' => 'kg',
        'current_stock' => 5, 'average_cost' => 50000, 'min_stock' => 0, 'is_active' => true,
    ]);

    $demande = StockRequisition::create(['department' => 'restaurant', 'point_of_sale_id' => $this->kotibe->id]);
    $demande->lines()->create(['stock_item_id' => $farine->id, 'quantity_requested' => 20]);

    $service = app(StockRequisitionService::class);
    $service->approve($demande);
    $service->deliver($demande);

    expect((float) $auJardin->fresh()->current_stock)->toBe(5.0);
    $aKotibe = RestaurantPantryItem::where('point_of_sale_id', $this->kotibe->id)->where('name', 'Farine')->sole();
    expect((float) $aKotibe->current_stock)->toBe(20.0);
});

// ── Buffet au forfait ─────────────────────────────────────────────────────

test("le buffet s'ouvre dans un restaurant qui sert au buffet, et pas ailleurs", function () {
    $payload = ['service_date' => today()->toDateString(), 'meal_service' => 'lunch', 'adult_price' => 12000, 'child_price' => 6000];

    $this->actingAs(equipierDe('restaurant_manager', [$this->origine]))
        ->post(route('restaurant.buffets.store'), $payload)->assertSessionHasErrors('restaurant');

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe], 'Chef Kotibe'))
        ->post(route('restaurant.buffets.store'), $payload)->assertSessionHasNoErrors();

    $this->post(route('restaurant.buffets.store'), $payload)->assertSessionHasErrors('service_date');

    $buffet = RestaurantBuffetService::sole();
    expect($buffet->point_of_sale_id)->toBe($this->kotibe->id)
        ->and($buffet->adult_price)->toBe(1200000);
});

test('la caisse enregistre les entrées au buffet, en espèces ou sur le séjour', function () {
    $buffet = RestaurantBuffetService::create([
        'point_of_sale_id' => $this->kotibe->id, 'service_date' => today(), 'meal_service' => 'lunch',
        'adult_price' => 1200000, 'child_price' => 600000, 'status' => 'open', 'opened_at' => now(),
    ]);
    $sejour = sejourEnCours();

    $this->actingAs(equipierDe('cashier', [$this->kotibe]));

    // Sans caisse ouverte, pas d'encaissement.
    $this->post(route('restaurant.buffets.entries.store', $buffet), ['adults' => 2, 'children' => 1, 'payment_method' => 'cash'])
        ->assertSessionHasErrors('cash_register');

    $this->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000])->assertSessionHasNoErrors();
    $this->post(route('restaurant.buffets.entries.store', $buffet), ['adults' => 2, 'children' => 1, 'payment_method' => 'cash'])
        ->assertSessionHasNoErrors();

    // Le résident reporte son entrée sur son séjour : ce n'est pas un encaissement.
    $this->post(route('restaurant.buffets.entries.store', $buffet), ['adults' => 1, 'payment_method' => 'room_charge', 'booking_id' => $sejour->id])
        ->assertSessionHasNoErrors();

    expect(RestaurantBuffetEntry::where('payment_method', 'cash')->sole()->amount)->toBe(3000000);
    expect(FolioItem::where('booking_id', $sejour->id)->sole()->total_price)->toBe(1200000);

    $caisse = CashRegisterSession::where('module', 'restaurant')->sole();
    expect($caisse->point_of_sale_id)->toBe($this->kotibe->id)
        ->and($caisse->theoreticalBalance())->toBe(1000000 + 3000000);
});

test("un buffet clos n'enregistre plus d'entrée", function () {
    $buffet = RestaurantBuffetService::create([
        'point_of_sale_id' => $this->kotibe->id, 'service_date' => today(), 'meal_service' => 'dinner',
        'adult_price' => 1200000, 'status' => 'open', 'opened_at' => now(),
    ]);

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe]))
        ->post(route('restaurant.buffets.close', $buffet))->assertSessionHasNoErrors();

    $sejour = sejourEnCours();
    $this->post(route('restaurant.buffets.entries.store', $buffet), ['adults' => 1, 'payment_method' => 'room_charge', 'booking_id' => $sejour->id])
        ->assertSessionHasErrors('buffet');
});

// ── Banquets ──────────────────────────────────────────────────────────────

test("un banquet se réserve, se confirme à l'acompte, se réalise et se solde", function () {
    $salle = Space::create(['name' => 'Salle des fêtes', 'slug' => 'kot-fetes', 'capacity' => 120, 'point_of_sale_id' => $this->kotibe->id, 'is_active' => true]);
    $devis = [
        'point_of_sale_id' => $this->kotibe->id, 'title' => 'Mariage Ngo', 'client_name' => 'Famille Ngo',
        'event_date' => today()->addMonth()->toDateString(), 'start_time' => '18:00', 'end_time' => '23:00',
        'space_id' => $salle->id, 'covers' => 100, 'price_per_cover' => 15000, 'deposit_required' => 500000,
    ];

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe], 'Responsable'));
    $this->post(route('restaurant.banquets.store'), $devis)->assertSessionHasNoErrors();
    $banquet = RestaurantBanquet::sole();

    expect($banquet->reference)->toStartWith('BQT-')
        ->and($banquet->total_amount)->toBe(150000000)
        ->and($banquet->status)->toBe(RestaurantBanquet::DEVIS);

    // La même salle, le même soir : refusé.
    $this->post(route('restaurant.banquets.store'), ['title' => 'Anniversaire'] + $devis)->assertSessionHasErrors('space_id');

    // La réservation tient quand l'acompte est encaissé.
    $this->post(route('restaurant.banquets.status', $banquet), ['status' => 'confirme'])->assertSessionHasErrors('status');

    $caissier = equipierDe('cashier', [$this->kotibe], 'Caissière');
    $this->actingAs($caissier);
    $this->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 0]);
    $this->post(route('restaurant.banquets.payments.store', $banquet), ['amount' => 500000, 'payment_method' => 'cash'])
        ->assertSessionHasNoErrors();

    $this->actingAs(User::where('name', 'Responsable')->first());
    $this->post(route('restaurant.banquets.status', $banquet), ['status' => 'confirme'])->assertSessionHasNoErrors();
    $this->post(route('restaurant.banquets.status', $banquet), ['status' => 'realise'])->assertSessionHasNoErrors();
    expect($banquet->fresh()->status)->toBe(RestaurantBanquet::REALISE);

    $this->actingAs($caissier);
    $this->post(route('restaurant.banquets.payments.store', $banquet), ['amount' => 1000001, 'payment_method' => 'transfer'])
        ->assertSessionHasErrors('amount');
    $this->post(route('restaurant.banquets.payments.store', $banquet), ['amount' => 1000000, 'payment_method' => 'transfer'])
        ->assertSessionHasNoErrors();

    expect($banquet->fresh()->status)->toBe(RestaurantBanquet::SOLDE)
        ->and($banquet->payments()->pluck('kind')->all())->toBe(['acompte', 'solde'])
        ->and(CashRegisterSession::where('user_id', $caissier->id)->sole()->theoreticalBalance())->toBe(50000000);
});

test("un banquet se tient dans un restaurant où l'on travaille, dans une de ses salles", function () {
    $salleDuJardin = Space::create(['name' => 'Terrasse', 'slug' => 'jardin-terrasse', 'point_of_sale_id' => $this->origine->id, 'is_active' => true]);
    $devis = [
        'title' => 'Séminaire', 'client_name' => 'Société X', 'event_date' => today()->addWeek()->toDateString(),
        'covers' => 30, 'price_per_cover' => 10000,
    ];

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe]));

    $this->post(route('restaurant.banquets.store'), ['point_of_sale_id' => $this->origine->id] + $devis)
        ->assertSessionHasErrors('point_of_sale_id');
    $this->post(route('restaurant.banquets.store'), ['point_of_sale_id' => $this->kotibe->id, 'space_id' => $salleDuJardin->id] + $devis)
        ->assertSessionHasErrors('space_id');

    expect(RestaurantBanquet::count())->toBe(0);
});

// ── Restaurants et équipes ────────────────────────────────────────────────

test('la direction crée un restaurant, ses services et ses modes de service', function () {
    $this->actingAs(directionGenerale())
        ->post(route('restaurant.restaurants.store'), [
            'name' => 'Le Baleng', 'code' => 'bal', 'series_prefix' => 'BAL-',
            'service_modes' => ['buffet'], 'services' => ['stock', 'salle', 'cuisine'],
        ])
        ->assertRedirect(route('settings.index', ['tab' => 'restaurant']))
        ->assertSessionHasNoErrors();

    $baleng = PointOfSale::where('code', 'BAL')->sole();
    expect($baleng->kind)->toBe(PointOfSale::KIND_RESTAURATION)
        ->and($baleng->sert('buffet'))->toBeTrue()
        ->and($baleng->sert('carte'))->toBeFalse()
        // Dans l'ordre du catalogue, quel que soit l'ordre coché.
        ->and($baleng->services)->toBe(['salle', 'cuisine', 'stock'])
        ->and($baleng->offre('bar'))->toBeFalse();

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe]))
        ->post(route('restaurant.restaurants.store'), ['name' => 'Autre', 'code' => 'AUT', 'service_modes' => ['carte'], 'services' => ['salle', 'bar']]);

    // Créer un restaurant relève de la direction.
    expect(PointOfSale::where('code', 'AUT')->exists())->toBeFalse();
});

test("le responsable compose l'équipe de son restaurant, pas celle d'un autre", function () {
    $serveur = equipierDe('restaurant_staff', [], 'Nouveau serveur');
    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe]));

    $this->put(route('restaurant.restaurants.team.update', $this->kotibe), ['users' => [$serveur->id]])->assertSessionHasNoErrors();
    expect($serveur->restaurants()->pluck('points_of_sale.id')->all())->toBe([$this->kotibe->id]);

    $this->put(route('restaurant.restaurants.team.update', $this->origine), ['users' => [$serveur->id]])->assertForbidden();
});

test('un membre de la restauration est créé dans le restaurant choisi', function () {
    $restauration = Department::where('code', Department::CODE_RESTAURATION)->sole();
    $compte = fn (array $champs) => $champs + [
        'name' => 'Serveuse Kotibe', 'email' => 'serveuse@hotel.test', 'roles' => ['restaurant_staff'],
        'department_id' => $restauration->id,
        'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'is_active' => 1,
    ];

    $this->actingAs(directionGenerale());

    // Le département Restauration exige son restaurant.
    $this->post(route('users.store'), $compte([]))->assertSessionHasErrors('restaurant_id');

    $this->post(route('users.store'), $compte(['restaurant_id' => $this->kotibe->id]))->assertSessionHasNoErrors();

    expect(User::where('email', 'serveuse@hotel.test')->sole()->restaurants()->pluck('points_of_sale.id')->all())
        ->toBe([$this->kotibe->id]);
});

test("changer le restaurant d'un membre de la restauration le mute, sans toucher à ses autres équipes", function () {
    $restauration = Department::where('code', Department::CODE_RESTAURATION)->sole();
    $responsable = equipierDe('restaurant_manager', [$this->origine, $this->kotibe], 'Responsable');
    $responsable->update(['department_id' => $restauration->id]);
    $champs = fn (int $restaurant) => [
        'name' => 'Responsable', 'email' => $responsable->email, 'roles' => ['restaurant_manager'],
        'department_id' => $restauration->id, 'restaurant_id' => $restaurant, 'is_active' => 1,
    ];
    $restaurantsDe = fn () => $responsable->restaurants()->orderBy('points_of_sale.id')->pluck('points_of_sale.id')->all();

    $this->actingAs(directionGenerale());

    // Un restaurant où il travaille déjà : ses deux équipes restent.
    $this->put(route('users.update', $responsable), $champs($this->kotibe->id))->assertSessionHasNoErrors();
    expect($restaurantsDe())->toBe([$this->origine->id, $this->kotibe->id]);

    // Un autre : il y est muté.
    $baleng = PointOfSale::create(['code' => 'BAL', 'slug' => 'baleng', 'name' => 'Baleng', 'kind' => PointOfSale::KIND_RESTAURATION, 'is_active' => true, 'sort_order' => 5]);
    app(RestaurantContext::class)->oublier();
    $this->put(route('users.update', $responsable), $champs($baleng->id))->assertSessionHasNoErrors();
    expect($restaurantsDe())->toBe([$baleng->id]);
});

test('le formulaire du personnel propose le restaurant au seul département Restauration', function () {
    $restauration = Department::where('code', Department::CODE_RESTAURATION)->sole();

    $this->actingAs(directionGenerale())->get(route('users.index'))
        ->assertOk()
        ->assertSee("Restaurant d'affectation", false)
        ->assertSee('name="restaurant_id"', false)
        ->assertSee('"restauration":true', false);

    expect(Department::where('code', '!=', Department::CODE_RESTAURATION)->get()->contains(fn ($d) => $d->estLaRestauration()))->toBeFalse()
        ->and($restauration->estLaRestauration())->toBeTrue();
});

// ── Écrans ────────────────────────────────────────────────────────────────

test("les écrans des restaurants, des buffets et des banquets s'affichent", function () {
    $salle = Space::create(['name' => 'Salle des fêtes', 'slug' => 'kot-fetes-2', 'point_of_sale_id' => $this->kotibe->id, 'is_active' => true]);
    $buffet = RestaurantBuffetService::create([
        'point_of_sale_id' => $this->kotibe->id, 'service_date' => today(), 'meal_service' => 'lunch',
        'adult_price' => 1200000, 'status' => 'open', 'opened_at' => now(),
    ]);
    $banquet = RestaurantBanquet::create([
        'point_of_sale_id' => $this->kotibe->id, 'reference' => 'BQT-TEST-001', 'title' => 'Gala annuel', 'client_name' => 'Club',
        'event_date' => today()->addDays(3), 'space_id' => $salle->id, 'covers' => 40, 'price_per_cover' => 1000000,
        'total_amount' => 40000000, 'deposit_required' => 0, 'status' => RestaurantBanquet::DEVIS,
    ]);

    $this->actingAs(directionGenerale());
    $this->get(route('restaurant.restaurants.index'))->assertRedirect(route('settings.index', ['tab' => 'restaurant']));
    $this->get(route('settings.index', ['tab' => 'restaurant']))->assertOk()
        ->assertSee('Kotibe')->assertSee('Salle des fêtes')->assertSee('Nouveau restaurant')->assertSee('Services du restaurant');
    $this->get(route('restaurant.buffets.index'))->assertOk()->assertSee('Déjeuner')->assertDontSee('Ouvrir un buffet');
    $this->get(route('restaurant.banquets.index'))->assertOk()->assertSee('Gala annuel');

    $this->actingAs(equipierDe('restaurant_manager', [$this->kotibe]));
    $this->get(route('restaurant.buffets.index'))->assertOk()->assertSee('Ouvrir un buffet');
    $this->get(route('restaurant.buffets.show', $buffet))->assertOk()->assertSee('Enregistrer une entrée');
    $this->get(route('restaurant.banquets.show', $banquet))->assertOk()->assertSee('BQT-TEST-001')->assertSee('Confirmer');

    $caissier = equipierDe('cashier', [$this->kotibe], 'Caissière');
    $this->actingAs($caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 0]);
    $this->post(route('restaurant.buffets.entries.store', $buffet), ['adults' => 1, 'payment_method' => 'cash']);
    $this->get(route('restaurant.cash_register.close'))->assertOk()->assertSee('Entrées au buffet en espèces');
});

// ── Services d'un restaurant ──────────────────────────────────────────────

test('un restaurant active au moins un service, et sa salle a une cuisine ou un bar', function () {
    $this->actingAs(directionGenerale());
    $base = ['name' => 'Le Baleng', 'code' => 'BAL', 'service_modes' => ['carte']];

    $this->post(route('restaurant.restaurants.store'), $base)->assertSessionHasErrors('services');
    $this->post(route('restaurant.restaurants.store'), $base + ['services' => ['salle', 'stock']])->assertSessionHasErrors('services');
    $this->post(route('restaurant.restaurants.store'), $base + ['services' => ['cuisine', 'stock']])->assertSessionHasNoErrors();

    expect(PointOfSale::where('code', 'BAL')->sole()->libellesServices())->toBe(['Cuisine', 'Stock']);
});

test('sans bar, les boissons partent en cuisine ; sans cuisine, les plats partent au bar', function () {
    $this->kotibe->update(['services' => ['salle', 'cuisine']]);
    $this->origine->update(['services' => ['salle', 'bar']]);

    commandeDe($this->kotibe, 'T1', [articleDe($this->kotibe, 'Jus de bissap', 'drink')]);
    commandeDe($this->origine, 'T2', [articleDe($this->origine, 'Club sandwich')]);

    expect(RestaurantCustomerOrderItem::where('item_name', 'Jus de bissap')->value('station'))->toBe('cuisine')
        ->and(RestaurantCustomerOrderItem::where('item_name', 'Club sandwich')->value('station'))->toBe('bar');
});

test('un restaurant sans salle ne prend ni commande à table, ni salle, ni buffet', function () {
    $this->kotibe->update(['services' => ['cuisine', 'stock'], 'service_modes' => ['carte', 'buffet']]);
    $ndole = articleDe($this->kotibe, 'Ndolè royal');

    $this->actingAs(equipierDe('restaurant_staff', [$this->kotibe]))
        ->post(route('restaurant.orders.store'), ['table_number' => '4', 'items_json' => json_encode([['id' => $ndole->id, 'qty' => 1]])])
        ->assertSessionHasErrors('restaurant');

    $this->actingAs(directionGenerale())
        ->post(route('restaurant.restaurants.spaces.store', $this->kotibe), ['name' => 'Terrasse'])
        ->assertSessionHasErrors('restaurant');

    expect(fn () => RestaurantBuffetService::create([
        'point_of_sale_id' => $this->kotibe->id, 'service_date' => today(), 'meal_service' => 'lunch',
        'adult_price' => 1200000, 'status' => 'open', 'opened_at' => now(),
    ]))->toThrow(ValidationException::class);

    expect(RestaurantCustomerOrder::count())->toBe(0)
        ->and(Space::where('point_of_sale_id', $this->kotibe->id)->exists())->toBeFalse();
});

test("un restaurant sans stock n'a ni garde-manger, ni inventaire, ni fiche de comptage", function () {
    $this->kotibe->update(['services' => ['salle', 'cuisine', 'bar']]);

    expect(fn () => RestaurantPantryItem::create([
        'point_of_sale_id' => $this->kotibe->id, 'name' => 'Riz', 'unit' => 'kg', 'current_stock' => 1, 'is_active' => true,
    ]))->toThrow(ValidationException::class, "« Kotibe » n'a pas de stock");

    $this->actingAs(equipierDe('restaurant_chief', [$this->kotibe]))
        ->post(route('restaurant.stock_counts.store'))->assertSessionHasErrors('restaurant');

    expect(RestaurantStockCount::count())->toBe(0)
        // Seul le Jardin tient un stock : une seule fiche de cuisine, la sienne.
        ->and(array_keys(app(CountSheetService::class)->services()))->toContain('garde-manger')
        ->not->toContain('garde-manger-' . $this->kotibe->id);
});

test("un service ne se retire pas tant qu'il a du travail en cours", function () {
    commandeDe($this->kotibe, 'T3', [articleDe($this->kotibe, 'Ndolè royal')]);
    $champs = fn (array $services) => [
        'name' => 'Kotibe', 'code' => 'KOT', 'series_prefix' => 'KOT-', 'service_modes' => ['carte'], 'services' => $services, 'is_active' => 1,
    ];

    $this->actingAs(directionGenerale());

    $this->put(route('restaurant.restaurants.update', $this->kotibe), $champs(['salle', 'bar', 'stock']))
        ->assertSessionHasErrors('services');
    expect($this->kotibe->fresh()->offre('cuisine'))->toBeTrue();

    RestaurantCustomerOrder::query()->update(['status' => RestaurantCustomerOrder::STATUS_SERVED]);

    $this->put(route('restaurant.restaurants.update', $this->kotibe), $champs(['salle', 'bar', 'stock']))
        ->assertSessionHasNoErrors();
    expect($this->kotibe->fresh()->offre('cuisine'))->toBeFalse();
});

test("le menu tait un service que le restaurant n'exploite pas, et l'écran le dit", function () {
    $this->kotibe->update(['services' => ['salle', 'cuisine', 'stock']]);
    $chef = equipierDe('restaurant_chief', [$this->kotibe]);

    $this->actingAs($chef)->get(route('restaurant.kitchen.index'))
        ->assertOk()
        ->assertSee('href="' . route('restaurant.kitchen.index') . '"', false)
        ->assertDontSee('href="' . route('restaurant.bar.index') . '"', false);

    $this->get(route('restaurant.bar.index'))->assertOk()->assertSee("Kotibe n'a pas de bar", false);
});

test("l'onglet Restaurant : la direction règle, le contrôle consulte, la cuisine n'y voit pas la structure", function () {
    $this->actingAs(directionGenerale('controller'))->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Kotibe')
        ->assertDontSee('Nouveau restaurant');

    $this->actingAs(equipierDe('restaurant_chief', [$this->kotibe]))->get(route('settings.index', ['tab' => 'restaurant']))
        ->assertOk()
        ->assertSee('tenus par la direction')
        ->assertDontSee('Composer l\'équipe', false);
});

test("sans le module Restaurant, l'onglet le dit et le personnel n'a pas de restaurant à choisir", function () {
    activerModules(['comptabilite', 'accounting', 'economat']);
    $restauration = Department::where('code', Department::CODE_RESTAURATION)->sole();

    $this->actingAs(directionGenerale())->get(route('settings.index', ['tab' => 'restaurant']))
        ->assertOk()
        ->assertSee("Le module Restaurant n'est pas activé", false)
        ->assertDontSee('Nouveau restaurant');

    $this->get(route('users.index'))->assertOk()->assertDontSee('name="restaurant_id"', false);

    $this->post(route('users.store'), [
        'name' => 'Commis', 'email' => 'commis@hotel.test', 'roles' => ['restaurant_staff'], 'department_id' => $restauration->id,
        'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'is_active' => 1,
    ])->assertSessionHasNoErrors();
});
