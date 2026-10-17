<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Paramètres : un seul bouton « Enregistrer » par onglet. La barre fixe envoie
 * chaque formulaire modifié de l'onglet à sa route habituelle ; le serveur lui
 * répond en JSON — réussite, erreurs par champ, refus — pour qu'elle les
 * affiche sur place sans recharger la page.
 */

beforeEach(function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'housekeeping', 'economat', 'restaurant']);
});

const UNIFIE = ['X-Enregistrement-Unifie' => '1', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

function gerantParametres(): User
{
    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

test('chaque onglet porte une barre unique ; ses formulaires de réglage lui sont rattachés', function () {
    $this->actingAs(gerantParametres());

    $general = $this->get(route('settings.index', ['tab' => 'general']))->assertOk()->getContent();
    expect($general)->toContain('data-reglages-onglet="general"')
        ->and($general)->toContain('id="barre-reglages"')
        ->and($general)->toContain('id="dialogue-reglages"')
        ->and(substr_count($general, '<form data-reglages '))->toBe(2);

    $hebergement = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();
    expect($hebergement)->toContain('data-reglages-fenetre="Pack"')
        ->and($hebergement)->toContain("data-reglages-fenetre=\"Politique d'annulation\"")
        ->and(substr_count($hebergement, '<form data-reglages '))->toBe(2);

    $this->get(route('settings.index', ['tab' => 'services']))->assertSee('data-reglages-fenetre="Prestation"', false);
    $this->get(route('settings.index', ['tab' => 'quarts']))->assertSee('<form data-reglages ', false);
});

test('la barre reçoit la réussite en JSON, sans message flashé pour la page suivante', function () {
    $this->actingAs(gerantParametres());

    $this->withHeaders(UNIFIE)->post(route('settings.update', ['tab' => 'general']), [
        'settings' => ['mail_from_address' => 'reception@hotel.cm', 'mail_from_name' => 'Hôtel'],
    ])->assertOk()->assertJson(['ok' => true]);

    expect(Tenant::first()->settings['general']['mail_from_address'])->toBe('reception@hotel.cm')
        ->and(session()->has('success'))->toBeFalse();

    // Après le dernier formulaire, la page rechargée le dit.
    $this->get(route('settings.index', ['tab' => 'general', 'enregistre' => 1]))
        ->assertSee("Les modifications de l'onglet ont été enregistrées.", false);
});

test('une erreur de validation revient par champ, pour s’afficher sous le champ', function () {
    $this->actingAs(gerantParametres());

    $this->withHeaders(UNIFIE)->post(route('settings.update', ['tab' => 'general']), [
        'settings' => ['mail_from_address' => 'pas-une-adresse'],
    ])->assertStatus(422)->assertJsonValidationErrors(['settings.mail_from_address']);

    expect(Tenant::first()->settings['general']['mail_from_address'] ?? null)->toBeNull();
});

test('un refus du contrôleur ou des droits revient en JSON', function () {
    // Mini-bar sans dépôt : le contrôleur refuse par un message flashé.
    $responsable = User::factory()->create(['role' => 'housekeeping_leader', 'is_active' => true]);
    $article = \App\Models\StockItem::create(['name' => 'Eau minérale', 'unit' => 'bouteille', 'is_active' => true]);

    $this->actingAs($responsable)->withHeaders(UNIFIE)
        ->post(route('housekeeping.minibar.articles.store'), ['stock_item_id' => $article->id, 'price' => 1000])
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, "demandez à l'économe"));

    // Sans le droit : refus, sans fenêtre « accès refusé » pour la suite.
    $valet = User::factory()->create(['role' => 'housekeeping_staff', 'is_active' => true]);
    $this->actingAs($valet)->withHeaders(UNIFIE)
        ->post(route('housekeeping.minibar.articles.store'), ['stock_item_id' => $article->id, 'price' => 1000])
        ->assertStatus(403);
    expect(session()->has('access_denied_popup'))->toBeFalse();
});

test("sans l'en-tête de la barre, les formulaires répondent comme avant", function () {
    $this->actingAs(gerantParametres());

    $this->post(route('settings.update', ['tab' => 'general']), ['settings' => ['mail_from_name' => 'Hôtel']])
        ->assertRedirect()->assertSessionHas('success');
});
