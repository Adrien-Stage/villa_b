<?php

/**
 * La lecture seule est une question de droits, posée au moteur unique.
 *
 * Elle était tenue par un second verrou, le middleware module.access, à côté
 * de la garde des droits : deux questions différentes pour une même route.
 * Le moteur de droits la porte désormais lui-même — un rôle affecté en
 * lecture seule ne donne que ses droits de consultation, et une restriction
 * de module posée par la console l'emporte sur tout.
 */

use App\Models\PermissionGrant;
use App\Models\Role;
use App\Models\User;
use App\Models\UserModulePermission;
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

test("la colonne héritée ne contourne pas une affectation en lecture seule", function () {
    // users.role porte aussi « restaurant_staff » : il ne doit pas rendre
    // l'écriture que l'affectation retire.
    $serveur = affecte('restaurant_staff', 'read');

    expect($serveur->role)->toBe('restaurant_staff')
        ->and($this->droits->allows($serveur, 'restaurant.orders.creer'))->toBeFalse();
});

test("un compte sans affectation garde l'écriture de son rôle hérité", function () {
    $ancien = User::factory()->create(['role' => 'restaurant_staff']);
    $ancien->roles()->detach();

    expect($this->droits->allows($ancien->fresh(), 'restaurant.orders.creer'))->toBeTrue();
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

test("une exclusion de module posée par la console l'emporte sur les rôles et les autorisations", function () {
    $serveur = affecte('restaurant_staff', 'write');
    UserModulePermission::create(['user_id' => $serveur->id, 'module_key' => 'restaurant', 'access_level' => 'none']);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER, 'subject_id' => (string) $serveur->id,
        'permission' => 'restaurant.orders.voir', 'effect' => PermissionGrant::EFFET_ALLOW,
        'reason' => 'Autorisation contredite par l\'exclusion.',
    ]);

    $serveur = $serveur->fresh();

    expect($this->droits->allows($serveur, 'restaurant.orders.voir'))->toBeFalse()
        ->and($this->droits->allows($serveur, 'restaurant.orders.creer'))->toBeFalse();
});

test('une lecture seule de module bloque les écritures de ce seul service', function () {
    $chef = affecte('restaurant_chief', 'write');
    UserModulePermission::create(['user_id' => $chef->id, 'module_key' => 'restaurant', 'access_level' => 'read']);
    $chef = $chef->fresh();

    expect($this->droits->allows($chef, 'restaurant.menus.voir'))->toBeTrue()
        ->and($this->droits->allows($chef, 'restaurant.menus.items.creer'))->toBeFalse()
        // Hors du restaurant, ses droits restent entiers.
        ->and($this->droits->allows($chef, 'economat.requisitions.creer'))->toBeTrue();
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
