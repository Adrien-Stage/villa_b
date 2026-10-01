<?php

/**
 * Revue des comptes : ce que l'administrateur doit trancher.
 */

use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\RoleReview;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => RoleCatalog::sync());

function constat(string $code): ?array
{
    return collect(RoleReview::constats())->firstWhere('code', $code);
}

function avecRoles(string $nom, array $roles): User
{
    $user = User::factory()->create(['name' => $nom, 'role' => $roles[0]]);
    $user->roles()->sync(Role::whereIn('slug', $roles)->pluck('id'));

    return $user;
}

test('le cumul cuisine et salle est à confirmer, nommément', function () {
    avecRoles('Ange Ondoa', ['restaurant_chief', 'restaurant_manager']);

    expect(constat('cumul_cuisine_salle')['gravite'])->toBe(RoleReview::A_CONFIRMER)
        ->and(constat('cumul_cuisine_salle')['comptes'][0])->toContain('Ange Ondoa');
});

test('un rôle retiré est à corriger', function () {
    avecRoles('Paul Essomba', ['it_support']);

    expect(constat('role_retire')['comptes'][0])->toContain('Paul Essomba');
});

test('un cumul interdit est signalé', function () {
    avecRoles('Rose Ngo', ['econome', 'accountant']);

    expect(constat('cumul_interdit')['comptes'][0])->toContain('Rose Ngo');
});

test("sans comptable, l'établissement est prévenu que les caisses attendront", function () {
    avecRoles('Jean Mvondo', ['reception']);

    expect(constat('aucun_comptable')['gravite'])->toBe(RoleReview::A_CORRIGER);

    avecRoles('Marie Abena', ['accountant']);

    expect(constat('aucun_comptable'))->toBeNull();
});

test('la commande de revue ne modifie rien et rend compte', function () {
    $compte = avecRoles('Ange Ondoa', ['restaurant_chief', 'restaurant_manager']);

    $this->artisan('roles:revue')
        ->expectsOutputToContain('Chef de cuisine et responsable de restaurant')
        ->assertExitCode(0);

    expect($compte->fresh()->rolesDetenus())->toContain('restaurant_chief', 'restaurant_manager');
});
