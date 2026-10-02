<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;

uses(RefreshDatabase::class);

test('logging in records an audit log and updates last_login_at', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $tenant = Tenant::create([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true]);

    $user = User::factory()->create([
        'role' => 'manager',
        'is_active' => true]);

    expect($user->last_login_at)->toBeNull();

    // Trigger Login Event
    event(new Login('web', $user, false));

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();

    $log = AuditLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('login');
    expect($log->user_id)->toBe($user->id);
    expect($log->module)->toBe('auth');
});

test('failed login attempts record an audit log', function () {
    event(new Failed('web', null, ['email' => 'hacker@example.com', 'password' => 'secret']));

    $log = AuditLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->event_type)->toBe('failed_login');
    expect($log->action)->toContain('hacker@example.com');
});

test('access denied is recorded in audit logs', function () {
    \App\Support\RoleCatalog::sync();

    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $manager->roles()->sync(\App\Models\Role::where('slug', 'manager')->pluck('id'));

    // Le journal d'audit relève de l'administrateur et du contrôle.
    $this->actingAs($manager)->get(route('audit.index'));

    $log = AuditLog::where('event_type', 'access_denied')->first();
    expect($log)->not->toBeNull()
        ->and($log->module)->toBe('security')
        ->and($log->user_id)->toBe($manager->id);
});

test("l'administrateur désactive un compte et le journal le garde", function () {
    \App\Support\RoleCatalog::sync();
    activerModules(['utilisateurs']);

    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin->roles()->sync(\App\Models\Role::where('slug', 'admin')->pluck('id'));
    $staff = User::factory()->create(['role' => 'reception', 'is_active' => true]);
    $staff->roles()->sync(\App\Models\Role::where('slug', 'reception')->pluck('id'));

    $this->actingAs($admin)->post(route('users.toggleStatus', $staff))->assertRedirect();

    expect($staff->fresh()->is_active)->toBeFalse();

    // On cible l'événement attendu plutôt que la dernière ligne du journal :
    // le middleware de suivi d'activité en écrit une après la réponse.
    $log = AuditLog::where('event_type', 'user_management')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->action)->toContain('désactivé');
});

test("le journal d'audit se filtre par événement", function () {
    \App\Support\RoleCatalog::sync();

    $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    $admin->roles()->sync(\App\Models\Role::where('slug', 'admin')->pluck('id'));

    AuditLog::create(['user_id' => $admin->id, 'event_type' => 'sensitive_action', 'action' => 'Action sur une réservation', 'module' => 'bookings']);
    AuditLog::create(['user_id' => $admin->id, 'event_type' => 'login', 'action' => 'Connexion du matin', 'module' => 'auth']);

    $this->actingAs($admin)->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Action sur une réservation')
        ->assertSee('Connexion du matin');

    $this->get(route('audit.index', ['event_type' => 'login']))
        ->assertOk()
        ->assertSee('Connexion du matin')
        ->assertDontSee('Action sur une réservation');
});
