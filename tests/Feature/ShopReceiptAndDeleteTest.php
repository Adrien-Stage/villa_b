<?php

use App\Models\Role;
use App\Models\ShopCategory;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['shop']);
});

function gerantBoutique(): User
{
    $u = User::factory()->create(['role' => 'shop_manager', 'is_active' => true]);
    $u->roles()->sync(Role::where('slug', 'shop_manager')->pluck('id'));

    return $u;
}

function articleBoutique(): ShopProduct
{
    $cat = ShopCategory::create(['name' => 'Souvenirs', 'sort_order' => 1, 'is_active' => true]);

    return ShopProduct::create(['shop_category_id' => $cat->id, 'name' => 'Bracelet artisanal', 'sku' => 'ART-000001',
        'price' => 500000, 'stock_quantity' => 10, 'reorder_level' => 3, 'is_active' => true]);
}

test("le ticket d'une commande boutique s'ouvre", function () {
    $gerant = gerantBoutique();
    $article = articleBoutique();
    $commande = ShopOrder::create(['order_number' => 'SHOP-1', 'customer_name' => 'Client de passage', 'total_items' => 2, 'subtotal' => 1000000,
        'tax_amount' => 155000, 'total_amount' => 1000000, 'payment_status' => 'paid', 'payment_method' => 'cash', 'created_by' => $gerant->id]);
    $commande->items()->create(['shop_product_id' => $article->id, 'quantity' => 2, 'unit_price' => 500000, 'item_total' => 1000000]);

    $this->actingAs($gerant)->get(route('shop.orders.receipt', $commande))
        ->assertOk()
        ->assertSee('SHOP-1')
        ->assertSee('Bracelet artisanal');
});

test("supprimer un article de la boutique aboutit et laisse une trace", function () {
    $gerant = gerantBoutique();
    $article = articleBoutique();

    $this->actingAs($gerant)->delete(route('shop.products.destroy', $article))
        ->assertRedirect(route('shop.products.index'));

    expect(ShopProduct::find($article->id))->toBeNull();
    expect(\App\Models\AuditLog::where('action', 'like', '%Bracelet artisanal%')->exists())->toBeTrue();
});

test("le code d'un article laissé vide est généré à la suite du dernier", function () {
    \App\Models\Tenant::updateOrCreate(['slug' => 'wetchah-app'], ['name' => 'Hôtel de test', 'is_active' => true]);
    $gerant = gerantBoutique();
    $article = articleBoutique(); // ART-000001

    $this->actingAs($gerant)->post(route('shop.products.store'), ['shop_category_id' => $article->shop_category_id, 'name' => 'Collier',
        'sku' => '', 'price' => 12000, 'stock_quantity' => 3, 'reorder_level' => 1, 'is_active' => 1])->assertSessionHas('success');

    expect(ShopProduct::where('name', 'Collier')->value('sku'))->toBe('ART-000002');
    $this->actingAs($gerant)->get(route('shop.products.create'))->assertOk()->assertSee('ART-000003');
});

test("un code article déjà pris est refusé en français", function () {
    $gerant = gerantBoutique();
    $article = articleBoutique();

    $this->actingAs($gerant)->post(route('shop.products.store'), ['shop_category_id' => $article->shop_category_id, 'name' => 'Bague',
        'sku' => 'ART-000001', 'price' => 1000, 'stock_quantity' => 1, 'reorder_level' => 0])
        ->assertSessionHasErrors(['sku' => 'Ce code article est déjà utilisé : choisissez-en un autre.']);
});

test("le manager consulte les articles sans voir les actions d'écriture", function () {
    articleBoutique();
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $manager->roles()->sync(Role::where('slug', 'manager')->pluck('id'));

    $this->actingAs($manager)->get(route('shop.products.index'))
        ->assertOk()
        ->assertSee('Bracelet artisanal')
        ->assertDontSee('Supprimer')
        ->assertDontSee('Modifier');

    $this->actingAs(gerantBoutique())->get(route('shop.products.index'))
        ->assertSee('Supprimer')
        ->assertSee('Modifier');
});

test("le manager consulte une commande sans les boutons d'encaissement ni d'ouverture de caisse", function () {
    $gerant = gerantBoutique();
    $article = articleBoutique();
    $commande = ShopOrder::create(['order_number' => 'SHOP-9', 'customer_name' => 'Client de passage', 'total_items' => 1, 'subtotal' => 500000,
        'tax_amount' => 77500, 'total_amount' => 500000, 'payment_status' => 'unpaid', 'payment_method' => 'cash', 'created_by' => $gerant->id]);
    $commande->items()->create(['shop_product_id' => $article->id, 'quantity' => 1, 'unit_price' => 500000, 'item_total' => 500000]);
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $manager->roles()->sync(Role::where('slug', 'manager')->pluck('id'));

    $this->actingAs($manager)->get(route('shop.orders.show', $commande))->assertOk()->assertDontSee('Marquer payée');
    $this->actingAs($manager)->get(route('shop.orders.index'))->assertOk()->assertDontSee('Ouvrir la caisse');

    $this->actingAs($gerant)->get(route('shop.orders.show', $commande))->assertSee('Marquer payée');
    $this->actingAs($gerant)->get(route('shop.orders.index'))->assertSee('Ouvrir la caisse');
});

test("rembourser une vente portée sur une chambre la retire du folio du séjour", function () {
    \App\Models\Tenant::updateOrCreate(['slug' => 'wetchah-app'], ['name' => 'Hôtel de test', 'is_active' => true]);
    $gerant = gerantBoutique();
    $article = articleBoutique();
    $rt = \App\Models\RoomType::create(['name' => 'Standard', 'code' => 'STD', 'base_price' => 3500000, 'base_capacity' => 2, 'max_capacity' => 3, 'is_active' => true]);
    $room = \App\Models\Room::create(['room_type_id' => $rt->id, 'number' => '203', 'status' => 'occupied', 'is_active' => true]);
    $client = \App\Models\Customer::create(['first_name' => 'Paul', 'last_name' => 'Mbarga', 'email' => 'paul@demo.test', 'phone' => '+237 600 00 00 00', 'nationality' => 'CM', 'loyalty_points' => 0, 'total_nights' => 0]);
    $sejour = \App\Models\Booking::create(['room_id' => $room->id, 'customer_id' => $client->id, 'status' => 'checked_in', 'check_in' => now()->toDateString(), 'check_out' => now()->addDays(2)->toDateString(),
        'adults_count' => 2, 'total_nights' => 2, 'price_per_night' => 3500000, 'total_room_amount' => 7000000, 'total_amount' => 7000000, 'paid_amount' => 0, 'balance_due' => 7000000, 'booking_number' => 'VB-1']);
    \App\Models\CashRegisterSession::create(['user_id' => $gerant->id, 'module' => 'shop', 'opening_amount' => 0, 'opened_at' => now()]);

    $this->actingAs($gerant)->post(route('shop.orders.store'), ['payment_method' => 'room_charge', 'booking_id' => $sejour->id,
        'items' => [['product_id' => $article->id, 'quantity' => 2]]])->assertSessionHas('success');
    $commande = ShopOrder::latest('id')->first();
    expect($sejour->fresh()->total_amount)->toBe(8000000)
        ->and($article->fresh()->stock_quantity)->toBe(8);

    $this->actingAs($gerant)->patch(route('shop.orders.refund', $commande))->assertSessionHas('success');

    expect($commande->fresh()->payment_status)->toBe('refunded')
        ->and(\App\Models\FolioItem::where('booking_id', $sejour->id)->where('type', \App\Models\FolioItem::TYPE_SHOP)->count())->toBe(0)
        ->and($sejour->fresh()->total_amount)->toBe(7000000)
        ->and($article->fresh()->stock_quantity)->toBe(10);
});
