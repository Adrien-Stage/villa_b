<?php

use App\Models\Role;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('la synchronisation crée les rôles du catalogue, économe compris', function () {
    // Des migrations créent déjà certains rôles : on ne part pas d'une table vide.
    Role::where('slug', 'econome')->delete();
    expect(Role::where('slug', 'econome')->exists())->toBeFalse();

    RoleCatalog::sync();

    $econome = Role::where('slug', 'econome')->first();
    expect($econome)->not->toBeNull()
        ->and($econome->module)->toBe('economat')
        ->and($econome->icon)->toBe('warehouse')
        ->and($econome->is_assignable)->toBeTrue();

    // Tout le catalogue est présent après synchronisation.
    foreach (RoleCatalog::all() as $definition) {
        expect(Role::where('slug', $definition['slug'])->exists())->toBeTrue();
    }
});

test('la synchronisation est idempotente : aucun doublon au second passage', function () {
    RoleCatalog::sync();
    $countAfterFirst = Role::count();

    $result = RoleCatalog::sync();

    expect($result['created'])->toBe(0)
        ->and(Role::count())->toBe($countAfterFirst);
});

test('un rôle existant est mis à jour, pas dupliqué', function () {
    // Rôle déjà présent mais sans module ni icône (cas d'un établissement
    // installé avant l'ajout de ces colonnes).
    Role::create(['name' => 'Économe', 'slug' => 'econome', 'description' => 'ancienne description']);

    RoleCatalog::sync();

    expect(Role::where('slug', 'econome')->count())->toBe(1);
    $econome = Role::where('slug', 'econome')->first();
    expect($econome->module)->toBe('economat')
        ->and($econome->icon)->toBe('warehouse');
});

test('les rôles privilégiés ne sont jamais assignables', function () {
    RoleCatalog::sync();

    foreach (['admin', 'manager', 'customer_guest'] as $slug) {
        expect(Role::where('slug', $slug)->first()->is_assignable)->toBeFalse();
    }
});

test('la synchronisation ne casse pas les rattachements utilisateurs existants', function () {
    RoleCatalog::sync();

    $user = \App\Models\User::factory()->create(['role' => 'econome']);
    $econome = Role::where('slug', 'econome')->first();
    $user->roles()->sync([$econome->id => ['level' => 'read']]);

    RoleCatalog::sync();

    $user->refresh();
    expect($user->roles)->toHaveCount(1)
        ->and($user->roles->first()->pivot->level)->toBe('read');
});

test('la commande roles:sync s’exécute et rapporte son résultat', function () {
    $this->artisan('roles:sync')
        ->expectsOutputToContain('Rôles synchronisés')
        ->assertSuccessful();

    expect(Role::where('slug', 'econome')->exists())->toBeTrue();
});
