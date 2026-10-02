<?php

/**
 * L'administrateur — le service informatique — crée et tient les comptes du
 * personnel, managers compris. Les comptes administrateurs, eux, ne se
 * gèrent que depuis la console d'orchestration.
 */

use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['utilisateurs', 'hebergement', 'reservations']);
    RoleCatalog::sync();
});

function compteAvec(string $role, array $attributs = []): User
{
    $user = User::factory()->create(['role' => $role] + $attributs);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user;
}

test("l'administrateur crée un manager", function () {
    $admin = compteAvec('admin');

    $this->actingAs($admin)->post('/users', [
        'name' => 'Marie Abena', 'email' => 'marie@hotel.test',
        'roles' => ['manager'], 'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'marie@hotel.test')->first()?->rolesDetenus())->toBe(['manager']);
});

test("l'administrateur voit et modifie le compte d'un manager", function () {
    $admin = compteAvec('admin');
    $manager = compteAvec('manager', ['name' => 'Marie Abena']);

    $this->actingAs($admin)->get('/users')->assertOk()->assertSee('Marie Abena');

    $this->post("/users/{$manager->id}/toggle-status")->assertRedirect();

    expect($manager->fresh()->is_active)->toBeFalse();
});

test("un manager ne gère pas le compte d'un autre manager, ni n'en crée", function () {
    $manager = compteAvec('manager');
    $autre = compteAvec('manager');

    $this->actingAs($manager)->post("/users/{$autre->id}/toggle-status")->assertForbidden();

    $this->post('/users', [
        'name' => 'Faux manager', 'email' => 'faux@hotel.test',
        'roles' => ['manager'], 'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide',
    ])->assertSessionHasErrors('roles.0');

    expect(User::where('email', 'faux@hotel.test')->exists())->toBeFalse();
});

test("personne, dans l'établissement, ne touche un compte administrateur", function () {
    $admin = compteAvec('admin');
    $autreAdmin = compteAvec('admin');

    $this->actingAs($admin)->post("/users/{$autreAdmin->id}/toggle-status")->assertForbidden();

    // Ni ne s'attribue le rôle : il n'est pas proposé.
    $this->post('/users', [
        'name' => 'Second admin', 'email' => 'second@hotel.test',
        'roles' => ['admin'], 'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide',
    ])->assertSessionHasErrors('roles.0');
});
