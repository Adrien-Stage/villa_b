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
