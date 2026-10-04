<?php

/**
 * La lecture seule est une question de droits, posée au moteur unique.
 *
 * Elle était tenue par un second verrou, le middleware module.access, à côté
 * de la garde des droits : deux questions différentes pour une même route.
 * Le moteur de droits la porte désormais lui-même : un rôle affecté en
 * lecture seule ne donne que ses droits de consultation.
 */

use App\Models\PermissionGrant;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    activerModules(['restaurant', 'shop', 'economat']);
    $this->droits = app(PermissionResolver::class);
});

/** Personne affectée à un rôle, au niveau donné. */
function affecte(string $role, ?string $niveau): User
{
    $user = User::factory()->create(['role' => $role]);
    $user->roles()->sync([Role::where('slug', $role)->value('id') => ['level' => $niveau]]);

    return $user->fresh();
}

test('un rôle en lecture seule ne donne que ses droits de consultation', function () {
    $serveur = affecte('restaurant_staff', 'read');

    expect($this->droits->allows($serveur, 'restaurant.orders.voir'))->toBeTrue()
        ->and($this->droits->allows($serveur, 'restaurant.orders.creer'))->toBeFalse();
});

test('le même rôle en écriture, ou sans niveau, écrit', function (?string $niveau) {
    expect($this->droits->allows(affecte('restaurant_staff', $niveau), 'restaurant.orders.creer'))->toBeTrue();
})->with(['write', null]);

test("un compte sans affectation ne détient aucun droit", function () {
    $ancien = User::factory()->create(['role' => 'restaurant_staff']);
    $ancien->roles()->detach();

    expect($ancien->fresh()->role)->toBeNull()
        ->and($this->droits->allows($ancien->fresh(), 'restaurant.orders.voir'))->toBeFalse();
});

test("une autorisation posée sur un rôle tenu en lecture seule n'ouvre pas d'écriture", function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'restaurant_staff',
        'permission' => 'restaurant.menus.items.creer', 'effect' => PermissionGrant::EFFET_ALLOW,
        'reason' => 'Le serveur complète la carte du jour.',
    ]);

    expect($this->droits->allows(affecte('restaurant_staff', 'write'), 'restaurant.menus.items.creer'))->toBeTrue()
        ->and($this->droits->allows(affecte('restaurant_staff', 'read'), 'restaurant.menus.items.creer'))->toBeFalse();
});

test("une autorisation hors de ses rôles prend effet sans second verrou", function () {
    // Le comptable n'a aucun rôle au restaurant : l'ancien verrou de module
    // rendait cette autorisation inopérante.
    $comptable = User::factory()->create(['role' => 'accountant']);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'accountant',
        'permission' => 'restaurant.billing.voir', 'effect' => PermissionGrant::EFFET_ALLOW,
        'reason' => 'Rapprochement des encaissements du restaurant.',
    ]);

    $this->actingAs($comptable)->get(route('restaurant.billing.index'))->assertOk();
});
