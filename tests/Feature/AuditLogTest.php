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
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

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
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $tenant = Tenant::create([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'manager',
        'is_active' => true,
    ]);
    
    $this->actingAs($manager);

    // Access admin route which calls AdminOnly middleware
    $response = $this->get('/admin/dashboard');
    $response->assertStatus(403);

    $log = AuditLog::where('event_type', 'access_denied')->first();
    expect($log)->not->toBeNull();
    expect($log->module)->toBe('security');
    expect($log->user_id)->toBe($manager->id);
});

test('admin can toggle user status and reset password', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $tenant = Tenant::create([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $staff = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'reception',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    // Toggle active status
    $response = $this->post(route('admin.users.toggle-active', $staff));
    $response->assertRedirect();
    
    $staff->refresh();
    expect($staff->is_active)->toBeFalse();

    $log = AuditLog::latest('id')->first();
    expect($log->event_type)->toBe('user_management');
    expect($log->action)->toContain('désactivé');

    // Force password reset
    $response = $this->post(route('admin.users.reset-password', $staff));
    $response->assertRedirect();
    $response->assertSessionHas('temp_password_info');

    $log = AuditLog::latest('id')->first();
    expect($log->event_type)->toBe('user_management');
    expect($log->action)->toContain('réinitialisé');
});

test('admin can filter audit logs', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    
    $tenant1 = Tenant::create([
        'name' => 'Villa A',
        'slug' => 'villa-a',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $tenant2 = Tenant::create([
        'name' => 'Villa B',
        'slug' => 'villa-b',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
    
    AuditLog::create([
        'tenant_id' => $tenant1->id,
        'user_id' => $admin->id,
        'event_type' => 'sensitive_action',
        'action' => 'Action on A',
        'module' => 'bookings',
    ]);

    AuditLog::create([
        'tenant_id' => $tenant2->id,
        'user_id' => $admin->id,
        'event_type' => 'login',
        'action' => 'Login action',
        'module' => 'auth',
    ]);

    $this->actingAs($admin);

    // Filter by tenant
    $response = $this->get(route('admin.dashboard', ['tab' => 'audit', 'tenant_id' => $tenant1->id]));
    $response->assertStatus(200);
    $response->assertSee('Action on A');
    $response->assertDontSee('Login action');

    // Filter by event_type
    $response = $this->get(route('admin.dashboard', ['tab' => 'audit', 'event_type' => 'login']));
    $response->assertStatus(200);
    $response->assertSee('Login action');
    $response->assertDontSee('Action on A');
});
