<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('la topbar remplace l’inscription En ligne par une icône du monde verte avec info-bulle au survol', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $html = $response->getContent();

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);

    $wrapper = $xpath->query('//*[@id="topbar-connexion-statut"]')->item(0);
    expect($wrapper)->not->toBeNull();
    expect($wrapper->getAttribute('x-data'))->toBe('topbarConnectivityStatus(false)');

    $button = $xpath->query('.//button', $wrapper)->item(0);
    expect($button)->not->toBeNull()
        ->and($button->getAttribute('type'))->toBe('button')
        ->and($button->getAttribute('aria-label'))->toBe('Statut de connexion')
        ->and($button->getAttribute('aria-controls'))->toBe('topbar-connexion-info')
        ->and($button->getAttribute('data-online'))->toBe('true')
        ->and($button->hasAttribute('title'))->toBeFalse()
        ->and($button->hasAttribute('aria-haspopup'))->toBeFalse()
        ->and($button->getAttribute('class'))->toContain('text-green-600')
        ->and($button->getAttribute('class'))->toContain('data-[online=false]:text-red-600');

    // Aucune inscription texte ("En ligne") dans le bouton : uniquement l'icône SVG du monde.
    expect(trim($button->textContent))->toBe('');

    $svg = $xpath->query('.//svg', $button)->item(0);
    expect($svg)->not->toBeNull()
        ->and($svg->getAttribute('aria-hidden'))->toBe('true');
    expect($xpath->query('.//circle[@cx="12"][@cy="12"][@r="10"]', $svg)->length)->toBe(1);
    expect($xpath->query('.//path', $svg)->length)->toBe(2);

    $tooltip = $xpath->query('//*[@id="topbar-connexion-info"]', $wrapper)->item(0);
    expect($tooltip)->not->toBeNull()
        ->and($tooltip->getAttribute('role'))->toBe('tooltip')
        ->and($tooltip->hasAttribute('x-cloak'))->toBeTrue()
        ->and(trim($tooltip->textContent))->toContain('En ligne — connexion opérationnelle');

    // L'info-bulle suit immédiatement le bouton dans le DOM pour le lecteur d'écran.
    $nextElement = $button->nextSibling;
    while ($nextElement !== null && $nextElement->nodeType !== XML_ELEMENT_NODE) {
        $nextElement = $nextElement->nextSibling;
    }
    expect($nextElement)->toBe($tooltip);
});

test('la topbar utilise également l’icône du monde et son info-bulle lorsque le module site vitrine est actif', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    activerModules(['website']);
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $response->getContent());
    $xpath = new DOMXPath($dom);

    $wrapper = $xpath->query('//*[@id="topbar-connexion-statut"]')->item(0);
    expect($wrapper)->not->toBeNull()
        ->and($wrapper->getAttribute('x-data'))->toBe('topbarConnectivityStatus(true)');

    $button = $xpath->query('.//button', $wrapper)->item(0);
    expect($button)->not->toBeNull()
        ->and(trim($button->textContent))->toBe('');

    $tooltip = $xpath->query('//*[@id="topbar-connexion-info"]', $wrapper)->item(0);
    expect($tooltip)->not->toBeNull()
        ->and(trim($tooltip->textContent))->toContain('En ligne — vérification de la liaison avec le site vitrine');
});

test('la route site-sync.status reflète l’activation du module website', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'reception', 'is_active' => true]);

    $this->actingAs($user)
        ->getJson(route('site-sync.status'))
        ->assertOk()
        ->assertExactJson(['enabled' => false, 'online' => null]);

    activerModules(['website']);
    Http::fake(['*' => Http::response(['ok' => true], 200)]);

    $this->actingAs($user)
        ->getJson(route('site-sync.status'))
        ->assertOk()
        ->assertJsonStructure(['enabled', 'online'])
        ->assertJson(['enabled' => true]);
});
