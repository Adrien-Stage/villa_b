<?php

/**
 * Les rôles introduits par la refonte ouvrent leurs écrans de travail.
 *
 * Ils sont attribuables : un chef de réception, un responsable de restaurant,
 * un responsable administratif et financier ou un magasinier doit trouver,
 * le premier jour, chacun des écrans de sa fonction — sans erreur.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoleSeeder::class]);
    activerModules(['restaurant', 'shop', 'housekeeping', 'accounting', 'comptabilite', 'ledger', 'economat', 'analytics']);
});

test('chaque nouveau rôle ouvre les écrans de sa fonction', function (string $role, array $routes) {
    $this->actingAs(User::factory()->create(['role' => $role]));

    foreach (array_merge(['dashboard'], $routes) as $route) {
        $this->get(route($route))->assertOk();
    }
})->with([
    'chef de réception' => ['reception_chief', [
        'bookings.index', 'agenda.index', 'rooms.index', 'customers.index', 'groups.index',
        'reception.pos.index', 'settings.index', 'economat.requisitions.index',
    ]],
    'responsable de restaurant' => ['restaurant_manager', [
        'restaurant.orders.index', 'restaurant.menus.index', 'restaurant.billing.index',
        'restaurant.breakfast.index', 'settings.index', 'economat.requisitions.index',
    ]],
    'responsable administratif et financier' => ['finance_manager', [
        'accounting.index', 'accounting.cash_reviews', 'accounting.journal', 'economat.requisitions.index',
    ]],
    'magasinier' => ['storekeeper', [
        'economat.index', 'economat.items.index', 'economat.receipts.index',
        'economat.requisitions.index', 'economat.stock_counts.index', 'economat.orders.index',
    ]],
]);

test("l'administrateur consulte chaque service sans erreur", function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    foreach ([
        'dashboard', 'bookings.index', 'rooms.index', 'customers.index', 'restaurant.orders.index',
        'restaurant.billing.index', 'shop.orders.index', 'economat.index', 'accounting.index',
        'housekeeping.index',
    ] as $route) {
        $this->get(route($route))->assertOk();
    }
});
