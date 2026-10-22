<?php

/**
 * L'identité de l'établissement (nom, coordonnées, devise, pays, couleurs)
 * est tenue par la console : chaque enregistrement de sa fiche la pousse ici.
 * Paramètres › Général la montre en lecture seule.
 */

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['orchestration.secret' => 'jeton-console', 'reporting.secret' => 'jeton-reporting']);
    $this->seed(\Database\Seeders\TenantSeeder::class);
});

function jetonDeLaConsole(): array
{
    return ['Authorization' => 'Bearer jeton-console'];
}

test("la console met à jour le nom, les coordonnées, la devise et les couleurs, sans toucher aux autres réglages", function () {
    $tenant = Tenant::current();
    $tenant->update(['settings' => array_merge($tenant->settings ?? [], [
        'reception' => ['check_out_time' => '11:00'],
        'theme'     => ['primary' => '#111111', 'secondary' => '#222222'],
    ])]);

    $this->putJson('/api/etablissement', [
        'name'     => 'Hôtel Zingana',
        'address'  => 'Quartier Bastos, Yaoundé',
        'phone'    => '+237 6 99 00 11 22',
        'email'    => 'contact@zingana.cm',
        'country'  => 'Cameroun',
        'currency' => 'xaf',
        'theme'    => ['primary' => '#0F766E', 'inconnue' => '#FFFFFF'],
        'auteur'   => 'Équipe Wetchah',
    ], jetonDeLaConsole())->assertOk()->assertJsonPath('name', 'Hôtel Zingana');

    $tenant->refresh();
    expect($tenant->name)->toBe('Hôtel Zingana')
        ->and($tenant->address)->toBe('Quartier Bastos, Yaoundé')
        ->and($tenant->phone)->toBe('+237 6 99 00 11 22')
        ->and($tenant->email)->toBe('contact@zingana.cm')
        ->and($tenant->currency)->toBe('XAF')
        ->and($tenant->settings['country'])->toBe('Cameroun')
        ->and($tenant->settings['theme'])->toBe(['primary' => '#0F766E', 'secondary' => '#222222'])
        ->and($tenant->settings['reception']['check_out_time'])->toBe('11:00');

    expect(AuditLog::where('event_type', 'tenant_console')->sole()->payload['champs'])->toContain('name', 'address');

    // Le nouveau nom se voit dans l'application.
    $manager = \App\Models\User::factory()->create(['role' => 'manager', 'is_active' => true]);
    \App\Support\RoleCatalog::sync();
    $this->actingAs($manager)->get(route('settings.index', ['tab' => 'general']))->assertSee('Hôtel Zingana');
});

test("sans le jeton de la console, l'identité ne change pas ; une donnée invalide est refusée", function () {
    $nom = Tenant::current()->name;

    $this->putJson('/api/etablissement', ['name' => 'Pirate'])->assertStatus(401);
    $this->putJson('/api/etablissement', ['name' => 'Pirate'], ['Authorization' => 'Bearer jeton-reporting'])->assertStatus(401);
    $this->putJson('/api/etablissement', ['name' => '', 'email' => 'pas-un-courriel'], jetonDeLaConsole())
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);

    expect(Tenant::current()->name)->toBe($nom);
    $this->getJson('/api/etablissement', jetonDeLaConsole())->assertOk()->assertJsonPath('name', $nom);
});
