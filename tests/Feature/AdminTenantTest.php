<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('an admin can update tenant general information and upload a logo', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $tenant = Tenant::create([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    $logo = UploadedFile::fake()->create('logo.png', 100);

    $response = $this->post(route('admin.tenants.update', $tenant), [
        'name' => 'New Tenant Name',
        'slug' => 'new-tenant-slug',
        'country' => 'Cameroun',
        'address' => 'New Address 123',
        'phone' => '+237 655 112 233',
        'email' => 'new@tenant.cm',
        'currency' => 'USD',
        'logo' => $logo,
        'theme' => [
            'primary' => '#1E3A8A',
            'secondary' => '#3B82F6',
            'accent' => '#93C5FD',
            'dark' => '#0F172A',
            'surface_dark' => '#1E293B',
            'text_on_light' => '#FFFFFF',
            'text_on_dark' => '#93C5FD',
        ],
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $tenant->refresh();
    expect($tenant->name)->toBe('New Tenant Name');
    expect($tenant->slug)->toBe('new-tenant-slug');
    expect($tenant->address)->toBe('New Address 123');
    expect($tenant->phone)->toBe('+237 655 112 233');
    expect($tenant->email)->toBe('new@tenant.cm');
    expect($tenant->currency)->toBe('USD');
    expect($tenant->settings['country'])->toBe('Cameroun');
    expect($tenant->settings['theme']['primary'])->toBe('#1E3A8A');
    expect($tenant->settings['theme']['secondary'])->toBe('#3B82F6');
    expect($tenant->settings['theme']['accent'])->toBe('#93C5FD');
    expect($tenant->settings['theme']['dark'])->toBe('#0F172A');
    expect($tenant->settings['theme']['surface_dark'])->toBe('#1E293B');
    expect($tenant->settings['theme']['text_on_light'])->toBe('#FFFFFF');
    expect($tenant->settings['theme']['text_on_dark'])->toBe('#93C5FD');
    
    // Check logo upload
    expect($tenant->settings['logo'])->not->toBeEmpty();
    Storage::disk('public')->assertExists($tenant->settings['logo']);

    // Check AuditLog entry
    $log = AuditLog::where('event_type', 'sensitive_action')->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toContain("Modification des informations générales");
    expect($log->user_id)->toBe($admin->id);
});

test('a non-admin user cannot update tenant information', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $tenant = Tenant::create([
        'name' => 'Original Name',
        'slug' => 'original-slug',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $manager = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'manager',
        'is_active' => true,
    ]);

    $this->actingAs($manager);

    $response = $this->post(route('admin.tenants.update', $tenant), [
        'name' => 'Hacked Name',
        'slug' => 'hacked-slug',
        'currency' => 'USD',
    ]);

    $response->assertStatus(403);
    
    $tenant->refresh();
    expect($tenant->name)->toBe('Original Name');
});
