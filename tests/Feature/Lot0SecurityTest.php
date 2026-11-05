<?php

use App\Models\PointOfSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Lot 0 : Fermer les accès indus (APP)
| S1, S3, S5, S10
|--------------------------------------------------------------------------
*/

test('S1 : un utilisateur désactivé ayant une session active est immédiatement déconnecté', function () {
    RoleCatalog::sync();
    $reception = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $reception->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    // Connecté et actif -> accès autorisé
    $this->actingAs($reception)->get(route('dashboard'))->assertOk();

    // Désactivation
    $reception->update(['is_active' => false]);

    // Tentative suivante sur route protégée
    $reponse = $this->actingAs($reception)->get(route('dashboard'));
    $reponse->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

test('S1 : la désactivation par toggleStatus révoque sessions et remember_token', function () {
    RoleCatalog::sync();
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    $target = User::factory()->create([
        'role' => 'reception',
        'is_active' => true,
        'remember_token' => 'token-session-avant',
    ]);
    $target->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    DB::table('sessions')->insert([
        'id' => 'sess-app-target',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestBrowser',
        'payload' => 'dummy',
        'last_activity' => time(),
    ]);

    config(['session.driver' => 'database']);

    $this->actingAs($admin)->post(route('users.toggleStatus', $target))->assertSessionHasNoErrors();

    $target->refresh();
    expect($target->is_active)->toBeFalse()
        ->and($target->remember_token)->not->toBe('token-session-avant');

    expect(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0);
});

test('S3 : le jeton de reporting ne peut pas modifier la matrice quand le secret d\'orchestration existe', function () {
    activerModules(['api', 'economat']);
    config([
        'orchestration.secret' => 'jeton-console-unique',
        'reporting.secret' => 'jeton-reporting-seul',
    ]);

    // Tentative d'écriture avec le jeton de reporting
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [],
    ], ['Authorization' => 'Bearer jeton-reporting-seul'])->assertStatus(401);
});

test('S3 : le jeton de reporting ne peut jamais écrire même si le secret d\'orchestration est vide', function () {
    activerModules(['api', 'economat']);
    config([
        'orchestration.secret' => '',
        'reporting.secret' => 'jeton-reporting-seul',
    ]);

    // Tentative d'écriture PUT avec repli impossible
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [],
    ], ['Authorization' => 'Bearer jeton-reporting-seul'])->assertStatus(503);

    // Tentative de POST aperçu avec repli impossible
    $this->postJson('/api/permissions/matrice/apercu', [
        'ecarts' => [],
    ], ['Authorization' => 'Bearer jeton-reporting-seul'])->assertStatus(503);

    // En revanche, la lecture GET en repli fonctionne pour assurer la compatibilité
    $this->getJson('/api/permissions/matrice', [
        'Authorization' => 'Bearer jeton-reporting-seul',
    ])->assertOk();
});

test('S5 : les commandes du personnel ne sont pas consultables anonymement sur le portail', function () {
    activerModules(['restaurant']);
    $tenant = Tenant::create(['name' => 'Hôtel Fictif', 'slug' => 'hotel-fictif', 'is_active' => true]);

    $staffOrder = RestaurantCustomerOrder::create([
        'source' => 'staff',
        'table_number' => 'STAFF-12',
        'status' => 'pending',
        'total_amount' => 50000,
        'placed_at' => now(),
    ]);

    $this->get(route('portal.restaurant.order', [
        'tenant' => $tenant->slug,
        'order' => $staffOrder->id,
    ]))->assertNotFound();
});

test('S5 : une commande portail exige la session du créateur ou un jeton de suivi valide', function () {
    activerModules(['restaurant']);
    $tenant = Tenant::create(['name' => 'Hôtel Fictif', 'slug' => 'hotel-fictif', 'is_active' => true]);

    $trackingToken = Str::random(40);
    $order = RestaurantCustomerOrder::create([
        'source' => 'portal',
        'table_number' => 'TABLE-5',
        'status' => 'pending',
        'total_amount' => 15000,
        'tracking_token' => $trackingToken,
        'tracking_token_expires_at' => now()->addHours(24),
        'placed_at' => now(),
    ]);

    // 1. Visiteur étranger sans jeton ni session -> 403
    $this->get(route('portal.restaurant.order', [
        'tenant' => $tenant->slug,
        'order' => $order->id,
    ]))->assertForbidden();

    // 2. Visiteur étranger avec faux jeton -> 403
    $this->get(route('portal.restaurant.order', [
        'tenant' => $tenant->slug,
        'order' => $order->id,
        'token' => 'faux-token',
    ]))->assertForbidden();

    // 3. Visiteur avec jeton valide dans l'URL -> 200
    $this->get(route('portal.restaurant.order', [
        'tenant' => $tenant->slug,
        'order' => $order->id,
        'token' => $trackingToken,
    ]))->assertOk()->assertSee('TABLE-5');

    // 4. Visiteur avec commande dans sa session -> 200
    $this->withSession(['portal_orders' => [$order->id => $trackingToken]])
        ->get(route('portal.restaurant.order', [
            'tenant' => $tenant->slug,
            'order' => $order->id,
        ]))->assertOk()->assertSee('TABLE-5');
});

test('S5 : un jeton de suivi expiré est refusé', function () {
    activerModules(['restaurant']);
    $tenant = Tenant::create(['name' => 'Hôtel Fictif', 'slug' => 'hotel-fictif', 'is_active' => true]);

    $trackingToken = Str::random(40);
    $order = RestaurantCustomerOrder::create([
        'source' => 'portal',
        'table_number' => 'TABLE-9',
        'status' => 'served',
        'total_amount' => 12000,
        'tracking_token' => $trackingToken,
        'tracking_token_expires_at' => now()->subMinutes(10), // Expiré
        'placed_at' => now()->subDays(2),
    ]);

    $this->get(route('portal.restaurant.order', [
        'tenant' => $tenant->slug,
        'order' => $order->id,
        'token' => $trackingToken,
    ]))->assertForbidden();
});

test('S10 : la création d\'un utilisateur staff impose must_change_password=true', function () {
    RoleCatalog::sync();
    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Nouveau Staff',
        'email' => 'nouveau-staff@example.test',
        'roles' => ['reception'],
        'password' => 'Password-Init-9284',
        'password_confirmation' => 'Password-Init-9284',
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $staff = User::where('email', 'nouveau-staff@example.test')->sole();
    expect($staff->must_change_password)->toBeTrue();

    // À la première connexion, redirection vers la page de choix du mot de passe
    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('password.nouveau'));
});

test('S10 : la création d\'un administrateur par API console impose must_change_password=true', function () {
    RoleCatalog::sync();
    config(['orchestration.secret' => 'secret-console']);

    $reponse = $this->postJson(route('api.comptes.administrateurs.store'), [
        'name' => 'Admin Console',
        'email' => 'admin-console@example.test',
        'password' => 'Admin-Strong-Pass-1234',
    ], ['Authorization' => 'Bearer secret-console'])->assertStatus(201);

    $admin = User::where('email', 'admin-console@example.test')->sole();
    expect($admin->must_change_password)->toBeTrue();
});
