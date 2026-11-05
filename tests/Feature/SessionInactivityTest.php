<?php

use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Tests de déconnexion automatique après inactivité (20 minutes)
|--------------------------------------------------------------------------
*/

test('la durée de vie de session par défaut est de 20 minutes', function () {
    expect(config('session.lifetime'))->toBe(20);
});

test('un utilisateur avec une activité récente peut naviguer normalement', function () {
    RoleCatalog::sync();
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    $reponse = $this->actingAs($user)
        ->withSession(['last_user_activity_at' => now()->subMinutes(5)->timestamp])
        ->get(route('dashboard'));

    $reponse->assertOk();
    expect(Auth::check())->toBeTrue();
});

test('une requête interactive rafraîchit le timestamp last_user_activity_at', function () {
    RoleCatalog::sync();
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    $ancienTimestamp = now()->subMinutes(10)->timestamp;

    $this->actingAs($user)
        ->withSession(['last_user_activity_at' => $ancienTimestamp])
        ->get(route('dashboard'));

    $nouveauTimestamp = session('last_user_activity_at');
    expect($nouveauTimestamp)->toBeGreaterThan($ancienTimestamp);
});

test('un sondage en arrière-plan ne rafraîchit pas last_user_activity_at', function () {
    RoleCatalog::sync();
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    $timestampFixe = now()->subMinutes(10)->timestamp;

    $this->actingAs($user)
        ->withSession(['last_user_activity_at' => $timestampFixe])
        ->withHeaders(['X-Background-Poll' => '1'])
        ->get(route('dashboard'));

    // Le timestamp n'a pas été prolongé
    expect(session('last_user_activity_at'))->toBe($timestampFixe);
});

test('une inactivité supérieure à 20 minutes déconnecte l\'utilisateur sur une requête web', function () {
    RoleCatalog::sync();
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    // Activité il y a 21 minutes
    $reponse = $this->actingAs($user)
        ->withSession(['last_user_activity_at' => now()->subMinutes(21)->timestamp])
        ->get(route('dashboard'));

    $reponse->assertRedirect(route('login'));
    $reponse->assertSessionHas('status', 'Votre session a expiré après 20 minutes d\'inactivité. Veuillez vous reconnecter.');
    $reponse->assertSessionHasErrors(['email', 'login']);
    expect(Auth::check())->toBeFalse();
});

test('une inactivité supérieure à 20 minutes retourne 401 sur un appel JSON ou sondage', function () {
    RoleCatalog::sync();
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    // Activité il y a 25 minutes
    $reponse = $this->actingAs($user)
        ->withSession(['last_user_activity_at' => now()->subMinutes(25)->timestamp])
        ->getJson(route('dashboard'));

    $reponse->assertStatus(401);
    $reponse->assertJsonFragment([
        'message' => 'Votre session a expiré après 20 minutes d\'inactivité.',
    ]);
    expect(Auth::check())->toBeFalse();
});
