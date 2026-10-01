<?php

/**
 * Les écrans s'ouvrent aux fonctions, pas aux rôles nommés.
 *
 * Un chef exerce le travail de ses membres : le chef de réception retrouve
 * les menus et les actions de la réception, sans qu'aucun écran ne le cite.
 * Chaque bouton pose le droit de sa route ; une section destinée à une
 * fonction s'ouvre à qui l'exerce.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
});

/** Libellés de la barre latérale pour ce rôle. */
function menuDe(string $role): array
{
    $html = test()->actingAs(User::factory()->create(['role' => $role]))->get('/dashboard')->getContent();
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);
    preg_match_all('#<span class="sidebar-libelle">(.*?)</span>#s', $nav[0] ?? '', $l);

    return array_map('trim', $l[1] ?? []);
}

test('le chef de réception retrouve les menus de la réception', function () {
    expect(menuDe('reception_chief'))->toContain('Chambres')
        ->and(menuDe('reception_chief'))->toContain('Agenda')
        ->and(menuDe('reception_chief'))->toContain('POS Réception');
});

test('le chef de réception règle les paramètres de son service, le réceptionniste non', function () {
    expect(menuDe('reception_chief'))->toContain('Paramètres')
        ->and(menuDe('reception'))->not->toContain('Paramètres');
});

test('le chef de réception voit les actions de réservation', function () {
    $this->actingAs(User::factory()->create(['role' => 'reception_chief']))
        ->get(route('bookings.index'))
        ->assertOk()
        // Sans caisse ouverte, l'action proposée est d'ouvrir la caisse.
        ->assertSee('Ouvrir la caisse');
});

test("le contrôleur de gestion consulte les réservations sans bouton d'action", function () {
    $this->actingAs(User::factory()->create(['role' => 'controller']))
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertDontSee('Nouvelle réservation')
        ->assertDontSee('Ouvrir la caisse');
});
