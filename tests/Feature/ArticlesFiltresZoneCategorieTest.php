<?php

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StorageZone;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function creerUtilisateurArticles(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test('la page des articles filtre par catégorie et par zone de stockage', function () {
    $user = creerUtilisateurArticles('purchasing_manager');

    $catNourriture = StockCategory::create(['name' => 'Alimentation', 'sort_order' => 1]);
    $catEntretien  = StockCategory::create(['name' => 'Entretien', 'sort_order' => 2]);

    $zoneChambreFroide = StorageZone::create(['name' => 'Chambre Froide', 'is_active' => true, 'sort_order' => 1]);
    $zoneReserve       = StorageZone::create(['name' => 'Réserve Sèche', 'is_active' => true, 'sort_order' => 2]);

    // Article 1 : Alimentation + Chambre Froide
    $viande = StockItem::create([
        'name' => 'Viande de bœuf',
        'unit' => 'kg',
        'stock_category_id' => $catNourriture->id,
        'storage_zone_id' => $zoneChambreFroide->id,
        'is_active' => true,
    ]);

    // Article 2 : Alimentation + Réserve Sèche
    $riz = StockItem::create([
        'name' => 'Riz parfumé 25kg',
        'unit' => 'sac',
        'stock_category_id' => $catNourriture->id,
        'storage_zone_id' => $zoneReserve->id,
        'is_active' => true,
    ]);

    // Article 3 : Entretien + Réserve Sèche
    $javel = StockItem::create([
        'name' => 'Eau de javel',
        'unit' => 'litre',
        'stock_category_id' => $catEntretien->id,
        'storage_zone_id' => $zoneReserve->id,
        'is_active' => true,
    ]);

    // 1. Sans filtre : voit les articles et les sélecteurs de filtre
    $resp = $this->actingAs($user)->get(route('economat.items.index'));
    $resp->assertOk();
    $resp->assertSee('name="category"', false);
    $resp->assertSee('name="zone"', false);
    $resp->assertSee('Toutes les catégories');
    $resp->assertSee('Toutes les zones');
    $resp->assertSee('Viande de bœuf');
    $resp->assertSee('Riz parfumé 25kg');
    $resp->assertSee('Eau de javel');

    // 2. Filtre par Catégorie Alimentation
    $respCat = $this->actingAs($user)->get(route('economat.items.index', ['category' => $catNourriture->id]));
    $respCat->assertOk();
    $respCat->assertSee('Viande de bœuf');
    $respCat->assertSee('Riz parfumé 25kg');
    $respCat->assertDontSee('Eau de javel');

    // 3. Filtre par Zone Chambre Froide
    $respZone = $this->actingAs($user)->get(route('economat.items.index', ['zone' => $zoneChambreFroide->id]));
    $respZone->assertOk();
    $respZone->assertSee('Viande de bœuf');
    $respZone->assertDontSee('Riz parfumé 25kg');
    $respZone->assertDontSee('Eau de javel');

    // 4. Filtre combiné Alimentation + Réserve Sèche
    $respBoth = $this->actingAs($user)->get(route('economat.items.index', [
        'category' => $catNourriture->id,
        'zone' => $zoneReserve->id,
    ]));
    $respBoth->assertOk();
    $respBoth->assertSee('Riz parfumé 25kg');
    $respBoth->assertDontSee('Viande de bœuf');
    $respBoth->assertDontSee('Eau de javel');
});
