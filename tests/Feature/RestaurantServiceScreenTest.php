<?php

namespace Tests\Feature;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Écran de salle : « Menus » sert la prise de commande au serveur et la
 * carte à administrer au chef. La gestion — coûts, stocks, inventaires —
 * reste hors de portée de la salle, lien comme URL.
 */

function serveurSalle(string $role = 'restaurant_staff'): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    $prop = new \ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['restaurant']);

    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

function platDuJour(string $nom, int $prix = 250000, string $categorie = 'Plats'): RestaurantMenuItem
{
    $cat = RestaurantMenuCategory::firstOrCreate(['name' => $categorie], ['is_active' => true]);

    return RestaurantMenuItem::create([
        'restaurant_menu_category_id' => $cat->id,
        'name' => $nom,
        'price' => $prix,
        'type' => 'food',
        'is_active' => true,
    ]);
}

test('le serveur reçoit l\'écran de prise de commande sur la page Menus', function () {
    platDuJour('Poulet DG', 350000);

    $html = $this->actingAs(serveurSalle())->get(route('restaurant.menus.index'))->assertOk()->getContent();

    expect($html)->toContain('Prise de commande')
        ->and($html)->toContain('Envoyer en cuisine')
        ->and($html)->toContain('Poulet DG')
        // La commande part vers l'enregistrement des commandes, pas vers la carte.
        ->and($html)->toContain(route('restaurant.orders.store'))
        // L'administration de la carte n'a rien à faire ici.
        ->and($html)->not->toContain('Ajouter un article');
});

test('le chef garde la carte à administrer', function () {
    platDuJour('Poulet DG');

    $html = $this->actingAs(serveurSalle('restaurant_chief'))->get(route('restaurant.menus.index'))->assertOk()->getContent();

    expect($html)->toContain('Ajouter un article')
        ->and($html)->not->toContain('Envoyer en cuisine');
});

test('seuls les plats actifs sont proposés à la vente', function () {
    platDuJour('Poulet DG');
    $retire = platDuJour('Plat retiré de la carte');
    $retire->update(['is_active' => false]);

    $html = $this->actingAs(serveurSalle())->get(route('restaurant.menus.index'))->assertOk()->getContent();

    expect($html)->toContain('Poulet DG')
        ->and($html)->not->toContain('Plat retiré de la carte');
});

test('la salle ne voit plus les rubriques de gestion', function () {
    platDuJour('Poulet DG');

    $html = $this->actingAs(serveurSalle())->get(route('restaurant.menus.index'))->assertOk()->getContent();

    expect($html)->not->toContain('Fiches techniques')
        ->and($html)->not->toContain('Garde-manger')
        ->and($html)->not->toContain('Inventaires');
});

test('le chef conserve les rubriques de gestion dans son menu', function () {
    $html = $this->actingAs(serveurSalle('restaurant_chief'))->get(route('restaurant.menus.index'))->assertOk()->getContent();

    expect($html)->toContain('Fiches techniques')
        ->and($html)->toContain('Garde-manger')
        ->and($html)->toContain('Inventaires');
});

test('les URL de gestion sont fermées à la salle', function () {
    $serveur = serveurSalle();

    // Masquer un lien sans fermer l'URL n'aurait rien masqué. Le contrôle de
    // rôle renvoie le serveur vers son écran plutôt que de lui claquer un 403.
    foreach (['restaurant.recipes.index', 'restaurant.pantry.index', 'restaurant.stock_counts.index'] as $route) {
        $this->actingAs($serveur)->get(route($route))->assertRedirect();
    }
});

test('le serveur transmet sa commande en cuisine depuis cet écran', function () {
    $serveur = serveurSalle();
    $plat = platDuJour('Poulet DG', 350000);

    $this->actingAs($serveur)->post(route('restaurant.orders.store'), [
        'table_number' => '12',
        'items_json' => json_encode([['id' => $plat->id, 'qty' => 2]]),
    ])->assertRedirect();

    $commande = RestaurantCustomerOrder::first();

    expect($commande)->not->toBeNull()
        ->and($commande->table_number)->toBe('12')
        ->and($commande->total_amount)->toBe(700000)
        ->and($commande->assigned_server_id)->toBe($serveur->id)
        // Prise et transmission dans le même geste : la cuisine l'a déjà.
        ->and($commande->sent_to_kitchen_at)->not->toBeNull();
});
