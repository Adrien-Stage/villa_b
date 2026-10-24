<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Paramètres › Général : le logo de l'établissement se met, se remplace et
 * se retire. Retiré, son fichier part avec lui et l'établissement retrouve
 * son initiale.
 */

beforeEach(function () {
    Storage::fake('public');
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();
    $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
});

function poserUnLogo(object $test): string
{
    $test->post(route('settings.update', ['tab' => 'general']), [
        'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
    ])->assertRedirect();

    return Tenant::first()->settings['logo'];
}

test('un logo posé propose de le retirer', function () {
    $this->get(route('settings.index', ['tab' => 'general']))->assertOk()->assertDontSee('name="remove_logo"', false);

    poserUnLogo($this);

    $this->get(route('settings.index', ['tab' => 'general']))->assertOk()
        ->assertSee('name="remove_logo"', false)->assertSee('Retirer le logo');
});

test('retirer le logo efface la clé et le fichier', function () {
    $chemin = poserUnLogo($this);
    Storage::disk('public')->assertExists($chemin);

    $this->post(route('settings.update', ['tab' => 'general']), ['remove_logo' => '1'])->assertRedirect();

    expect(Tenant::first()->settings)->not->toHaveKey('logo');
    Storage::disk('public')->assertMissing($chemin);
});

test('un nouveau fichier l\'emporte sur le retrait : le logo est remplacé, pas retiré', function () {
    $ancien = poserUnLogo($this);

    $this->post(route('settings.update', ['tab' => 'general']), [
        'remove_logo' => '1',
        'logo' => UploadedFile::fake()->image('nouveau.png', 120, 120),
    ])->assertRedirect();

    $nouveau = Tenant::first()->settings['logo'] ?? null;
    expect($nouveau)->not->toBeNull()->not->toBe($ancien);
    Storage::disk('public')->assertMissing($ancien);
    Storage::disk('public')->assertExists($nouveau);
});

test('le retrait passe aussi par la barre d\'enregistrement unique', function () {
    poserUnLogo($this);

    $this->withHeaders(['X-Enregistrement-Unifie' => '1', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('settings.update', ['tab' => 'general']), ['remove_logo' => '1'])
        ->assertOk()->assertJson(['ok' => true]);

    expect(Tenant::first()->settings)->not->toHaveKey('logo');
});
