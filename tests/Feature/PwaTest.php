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
        // Route nommée : le test suit l'URL réelle au lieu de se figer sur une
        // forme codée en dur — c'est ce décalage qui avait masqué le blocage.
        $response = $this->get(route('pwa.icon', ['size' => $size]));
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
    $this->get(route('pwa.icon', ['size' => 4096]))->assertNotFound();
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

test('les icônes déclarées dans le manifeste sont réellement servies', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

    expect($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        // On suit l'URL telle que le navigateur la lira dans le manifeste :
        // c'est le décalage entre manifeste et route qui avait cassé l'install.
        $path = parse_url($icon['src'], PHP_URL_PATH);

        $this->get($path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
});

test('aucune URL du manifeste ne porte une extension servie en statique', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    $manifest = $this->get(route('pwa.manifest'))->assertOk()->json();

    // nginx sert les .png/.jpg/.svg… depuis le disque. Une ressource générée
    // par Laravel ne doit donc pas porter ces extensions, sinon elle est
    // interceptée avant d'atteindre PHP et le navigateur ne l'obtient jamais.
    foreach ($manifest['icons'] as $icon) {
        $path = parse_url($icon['src'], PHP_URL_PATH);

        expect($path)->not->toMatch('/\.(png|jpe?g|gif|svg|webp|ico)$/i');
    }
});

// ── Favicon composé depuis le logo importé ────────────────────────────────────

test('toutes les URL d’icônes déclarées dans le head répondent', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

    // Extrait les href des <link rel="icon"> / apple-touch-icon réellement rendus.
    preg_match_all('/<link[^>]+rel="(?:icon|apple-touch-icon)"[^>]+href="([^"]+)"/i', $html, $m);

    expect($m[1])->not->toBeEmpty('Aucune balise d\'icône dans le head');

    foreach ($m[1] as $href) {
        $path = parse_url(html_entity_decode($href), PHP_URL_PATH)
            . '?' . (parse_url(html_entity_decode($href), PHP_URL_QUERY) ?? '');

        // Une URL d'icône morte laisse l'onglet sans logo, sans rien signaler.
        $this->get($path)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }
});

test('le head déclare une taille d’onglet adaptée', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    // 192 px pour un favicon d'onglet, c'est lourd et mal redimensionné :
    // les navigateurs préfèrent une déclaration 32x32.
    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('sizes="32x32"', false)
        ->assertSee('rel="apple-touch-icon"', false);
});

test('l’icône reprend le logo importé et change avec lui', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $tenant = Tenant::first();

    // Logo rouge uni : on vérifie que l'icône servie en porte la couleur.
    $logo = imagecreatetruecolor(200, 200);
    imagefilledrectangle($logo, 0, 0, 200, 200, imagecolorallocate($logo, 0xE1, 0x1D, 0x48));
    ob_start();
    imagepng($logo);
    \Illuminate\Support\Facades\Storage::disk('public')->put('logos/test-logo.png', ob_get_clean());
    imagedestroy($logo);

    $sansLogo = $this->get(\App\Http\Controllers\PwaController::iconUrl(32))->assertOk()->getContent();

    $tenant->settings = array_merge($tenant->settings ?? [], ['logo' => 'logos/test-logo.png']);
    $tenant->save();

    $avecLogo = $this->get(\App\Http\Controllers\PwaController::iconUrl(32))->assertOk()->getContent();

    // Le cache est indexé sur l'empreinte du logo : changer de logo doit
    // servir une image différente, sinon le manager garde l'ancienne icône.
    expect($avecLogo)->not->toBe($sansLogo);

    // Le rouge du logo doit se retrouver au centre de l'icône générée.
    $icone = imagecreatefromstring($avecLogo);
    $centre = imagecolorsforindex($icone, imagecolorat($icone, 16, 16));
    imagedestroy($icone);

    expect($centre['red'])->toBeGreaterThan(180)
        ->and($centre['green'])->toBeLessThan(80);
});

test('GD décode tous les formats de logo acceptés à l’import', function () {
    // Le formulaire Paramètres → Général accepte png, jpg, jpeg et gif. Un
    // format accepté que GD ne sait pas décoder produit le pire des bugs :
    // le logo s'affiche partout dans l'application — le navigateur le décode —
    // mais le favicon retombe en silence sur les initiales.
    $types = imagetypes();

    expect((bool) ($types & IMG_PNG))->toBeTrue('GD sans support PNG')
        ->and((bool) ($types & IMG_GIF))->toBeTrue('GD sans support GIF')
        ->and((bool) ($types & IMG_JPG))->toBeTrue(
            'GD sans support JPEG : reconstruire l\'image avec '
            . 'docker-php-ext-configure gd --with-jpeg'
        );
});

test('/favicon.ico sert l’icône de l’établissement', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    // Ce chemin servait un fichier vide de 0 octet hérité du squelette Laravel.
    // Les navigateurs le réclament d'office et les notifications push le
    // désignent comme icône : il doit porter la marque de l'établissement.
    $reponse = $this->get('/favicon.ico')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect(strlen($reponse->getContent()))->toBeGreaterThan(0);

    $icone = imagecreatefromstring($reponse->getContent());
    expect($icone)->not->toBeFalse();
    expect(imagesx($icone))->toBe(32);
    imagedestroy($icone);
});

