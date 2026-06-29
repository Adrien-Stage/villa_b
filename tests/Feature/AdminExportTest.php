<?php

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a guest or non-admin user cannot access export routes', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $tenant = Tenant::create([
        'name' => 'Villa Boutanga Test',
        'slug' => 'villa-boutanga-test',
        'currency' => 'XAF',
        'is_active' => true]);

    $manager = User::factory()->create([
        'role' => 'manager',
        'is_active' => true]);

    // Test Guest access
    $this->get(route('admin.export.supervision'))->assertRedirect('/login');
    $this->get(route('admin.export.backup'))->assertRedirect('/login');

    // Test Non-admin access
    $this->actingAs($manager);
    $this->get(route('admin.export.supervision'))->assertStatus(403);
    $this->get(route('admin.export.backup'))->assertStatus(403);
});

test('an admin can download supervision csv', function () {
    \Carbon\Carbon::setTestNow(now());
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true]);

    $tenant = Tenant::create([
        'name' => 'Villa Boutanga Test',
        'slug' => 'villa-boutanga-test',
        'currency' => 'XAF',
        'is_active' => true]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.export.supervision'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename=rapport_supervision_' . now()->format('Y-m-d_H-i-s') . '.csv');

    $content = $response->streamedContent();

    // Check BOM UTF-8
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify Headers and data
    expect($content)->toContain('"Villa Boutanga Test";villa-boutanga-test');

    // Verify AuditLog entry was recorded for the supervision export
    $exportLog = AuditLog::where('event_type', 'sensitive_action')
        ->where('action', "Export du rapport de supervision des établissements au format CSV")
        ->first();
    
    expect($exportLog)->not->toBeNull();
    expect($exportLog->user_id)->toBe($admin->id);
});

test('an admin can download database backup zip', function () {
    \Carbon\Carbon::setTestNow(now());
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true]);

    $this->actingAs($admin);

    $response = $this->get(route('admin.export.backup'));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/zip');
    $response->assertHeader('Content-Disposition', 'attachment; filename=villa_boutanga_backup_' . now()->format('Y-m-d_H-i-s') . '.zip');

    $zipPath = $response->getFile()->getPathname();
    
    $zip = new \ZipArchive();
    expect($zip->open($zipPath))->toBeTrue();
    expect($zip->numFiles)->toBe(1);

    $sqlFilename = $zip->getNameIndex(0);
    expect($sqlFilename)->toContain('villa_boutanga_backup_');
    expect($sqlFilename)->toContain('.sql');

    $sqlContent = $zip->getFromIndex(0);
    expect($sqlContent)->toContain('Structure de la table "users"');
    expect($sqlContent)->toContain('Contenu de la table "users"');

    // Validate driver-specific statements
    if (str_contains($sqlContent, 'Driver: sqlite')) {
        expect($sqlContent)->toContain('PRAGMA foreign_keys = OFF;');
        expect($sqlContent)->toContain('PRAGMA foreign_keys = ON;');
    } else {
        expect($sqlContent)->toContain('SET session_replication_role = \'replica\';');
        expect($sqlContent)->toContain('SET session_replication_role = \'origin\';');
    }

    $zip->close();

    // Verify AuditLog entry was recorded for the backup export
    $exportLog = AuditLog::where('event_type', 'sensitive_action')
        ->where('action', "Exportation d'une sauvegarde complète de la base de données au format ZIP")
        ->first();
    
    expect($exportLog)->not->toBeNull();
    expect($exportLog->user_id)->toBe($admin->id);
});
