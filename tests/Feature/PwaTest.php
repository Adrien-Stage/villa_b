<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('le manifeste est servi publiquement en JSON valide', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    // Non authentifié : le navigateur doit pouvoir le lire avant connexion.
    $response = $this->get('/manifest.webmanifest');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');

    $manifest = $response->json();

    // Champs exigés par les navigateurs pour rendre l'app installable.
    expect($manifest)->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons', 'theme_color'])
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['icons'])->not->toBeEmpty();
});

test('le manifeste porte le nom de l’établissement', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $tenant = Tenant::first();

    $manifest = $this->get('/manifest.webmanifest')->json();

    expect($manifest['name'])->toBe($tenant->name)
        // short_name est borné à 12 caractères (contrainte d'affichage mobile).
        ->and(strlen($manifest['short_name']))->toBeLessThanOrEqual(12);
});

test('le manifeste déclare les tailles d’icônes requises par Android', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    $sizes = collect($this->get('/manifest.webmanifest')->json('icons'))->pluck('sizes');

    expect($sizes)->toContain('192x192')->toContain('512x512');
});

test('les icônes sont générées aux bonnes dimensions', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    foreach ([192, 512] as $size) {
        $response = $this->get("/pwa/icon-{$size}.png");
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $info = getimagesizefromstring($response->getContent());
        expect($info[0])->toBe($size)
            ->and($info[1])->toBe($size)
            ->and($info['mime'])->toBe('image/png');
    }
});

test('une taille d’icône non prévue est refusée', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    // Borne volontaire : empêche de faire générer des images arbitraires.
    $this->get('/pwa/icon-4096.png')->assertNotFound();
});

test('la page hors connexion est accessible sans authentification', function () {
    // Indispensable : le service worker la met en cache avant toute connexion.
    $this->get('/offline')
        ->assertOk()
        ->assertSee('hors connexion', false);
});

test('le service worker est présent et gère cache et push', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)
        // Cache et mode hors-ligne
        ->toContain('caches.open')
        ->toContain('/offline')
        // Le push d'origine est préservé
        ->toContain("addEventListener('push'")
        ->toContain('showNotification')
        // Garde-fous : jamais de cache sur les écritures ni les routes sensibles
        ->toContain("request.method !== 'GET'")
        ->toContain('NEVER_CACHE');
});

test('les pages exposent les balises d’installation', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoleSeeder::class]);
    $manager = User::factory()->create(['role' => 'manager']);

    $html = $this->actingAs($manager)->get('/dashboard')->getContent();

    expect($html)
        ->toContain('rel="manifest"')
        ->toContain('name="theme-color"')
        // iOS ignore le manifeste : ces balises lui sont nécessaires.
        ->toContain('apple-mobile-web-app-capable')
        ->toContain('apple-touch-icon')
        // Enregistrement du service worker et invite d'installation
        ->toContain("serviceWorker.register('/sw.js')")
        ->toContain('pwa-install-banner');
});

test('la page de connexion est installable', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    // C'est l'écran d'entrée : souvent le moment où l'on installe l'app.
    $this->get('/login')
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('pwa-install-banner', false);
});
