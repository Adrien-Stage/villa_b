<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('la page de connexion affiche le nouveau design et le nom de l’établissement', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $tenant = Tenant::current();

    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Wetchah', false)
        ->assertSee('Gestion hôtelière', false)
        ->assertSee('Une journée d’hôtel', false)
        ->assertSee('Heureux de vous retrouver.', false)
        ->assertSee('Connectez-vous à votre espace de travail.', false)
        ->assertSee($tenant->name, false)
        ->assertSee('Adresse email', false)
        ->assertSee('Mot de passe', false)
        ->assertSee('Se connecter', false)
        ->assertSee('Contacter l’administrateur', false)
        ->assertSee('autocomplete="username"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('spellcheck="false"', false)
        ->assertSee('id="login-help-dialog"', false)
        ->assertSee('closedby="any"', false)
        ->assertDontSee('Explorer la démonstration', false)
        ->assertDontSee('Prototype uniquement', false);
});

test('la page de connexion affiche le logo du tenant et ses coordonnées publiques si configurés', function () {
    Tenant::create([
        'name' => 'Hôtel Le Palmier',
        'slug' => 'le-palmier',
        'email' => 'accueil@lepalmier.test',
        'phone' => '+237 699 00 11 22',
        'settings' => [
            'logo' => 'logos/palmier.png',
            'general' => [
                'mail_from_address' => 'interne-prive@lepalmier.test',
            ],
        ],
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Hôtel Le Palmier', false)
        ->assertSee('<p class="login-brand-wordmark">Hôtel Le Palmier<span', false)
        ->assertSee('Les services de Hôtel Le Palmier.', false)
        ->assertSee('storage/logos/palmier.png', false)
        ->assertSee('accueil@lepalmier.test', false)
        ->assertSee('+237 699 00 11 22', false)
        ->assertDontSee('interne-prive@lepalmier.test', false);
});

test('la page de connexion applique le thème configuré dans Wetchah ERP et le nom de l’établissement', function () {
    Tenant::create([
        'name' => 'Kore-TecK',
        'slug' => 'kore-teck',
        'settings' => [
            'theme' => [
                'primary' => '#1E3A8A',
                'secondary' => '#3B82F6',
                'accent' => '#93C5FD',
                'dark' => '#0F172A',
                'surface_dark' => '#1E293B',
                'text_on_light' => '#FFFFFF',
                'text_on_dark' => '#93C5FD',
            ],
        ],
    ]);

    $html = $this->get(route('login'))->assertOk()->getContent();

    expect($html)
        ->toContain('--color-primary: #1E3A8A;')
        ->toContain('--color-secondary: #3B82F6;')
        ->toContain('--color-accent: #93C5FD;')
        ->toContain('--color-dark: #0F172A;')
        ->toContain('--color-surface-dark: #1E293B;')
        ->toContain('--color-text-on-dark: #93C5FD;')
        ->toContain('<meta name="theme-color" content="#1E3A8A">')
        ->toContain('var(--color-surface-dark)')
        ->toContain('var(--login-cta-bg)')
        ->toContain('<p class="login-brand-wordmark">Kore-TecK<span')
        ->toContain('<span>Kore-TecK</span>')
        ->toContain('<p class="login-foot">Kore-TecK · Wetchah PMS</p>')
        ->not->toContain('--login-brown')
        ->not->toContain('#3b281d')
        ->not->toContain('fill="#684a34"');
});

test('la page de connexion adapte le contraste du bouton lorsque la couleur principale est claire', function () {
    Tenant::create([
        'name' => 'Soleil Palace',
        'slug' => 'soleil-palace',
        'settings' => [
            'theme' => [
                'primary' => '#FACC15',
                'secondary' => '#FDE047',
                'accent' => '#FEF9C3',
                'dark' => '#1C1917',
                'surface_dark' => '#CA8A04',
                'text_on_light' => '#1C1917',
                'text_on_dark' => '#FEF9C3',
            ],
        ],
    ]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('--login-cta-fg: #181410;', false)
        ->assertSee('contrast-color(var(--login-cta-bg))', false);
});

test('la page de connexion gère l’authentification, les identifiants incorrects et les comptes désactivés', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();

    $actif = User::factory()->create([
        'role' => 'reception',
        'email' => 'agent@hotel.test',
        'password' => Hash::make('MotDePasseValide123!'),
        'is_active' => true,
    ]);
    $actif->roles()->sync(Role::where('slug', 'reception')->pluck('id'));

    // 1. Identifiants incorrects → retour /login avec bannière adaptée
    $mauvais = $this->from(route('login'))->followingRedirects()->post(route('login'), [
        'email' => 'agent@hotel.test',
        'password' => 'MauvaisMotDePasse',
    ]);
    $mauvais->assertOk()
        ->assertSee('Identifiants incorrects', false);
    $this->assertGuest();

    // 2. Compte désactivé → retour /login avec bannière « Compte désactivé »
    $inactif = User::factory()->create([
        'role' => 'reception',
        'email' => 'inactif@hotel.test',
        'password' => Hash::make('MotDePasseValide123!'),
        'is_active' => false,
    ]);
    $desactive = $this->from(route('login'))->followingRedirects()->post(route('login'), [
        'email' => $inactif->email,
        'password' => 'MotDePasseValide123!',
    ]);
    $desactive->assertOk()
        ->assertSee('Compte désactivé', false);
    $this->assertGuest();

    // 3. Session expirée par inactivité → pas de double bannière statut + erreur
    $expire = $this->withSession([
        'status' => 'Votre session a expiré après 20 minutes d\'inactivité. Veuillez vous reconnecter.',
        'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
            'login' => 'Votre session a expiré après 20 minutes d\'inactivité.',
            'email' => 'Votre session a expiré après 20 minutes d\'inactivité.',
        ])),
    ])->get(route('login'));
    $expire->assertOk()
        ->assertSee('Erreur de connexion', false)
        ->assertDontSee('Veuillez vous reconnecter.', false);

    // 4. Connexion réussie
    $ok = $this->post(route('login'), [
        'email' => $actif->email,
        'password' => 'MotDePasseValide123!',
    ]);
    $ok->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($actif);
});

test('la page de connexion standard n’active pas la veille alors que ?expired=inactivity et /veille l’affichent', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $tenant = Tenant::current();

    $normalHtml = $this->get(route('login'))->assertOk()->getContent();
    expect($normalHtml)
        ->toContain('data-standby="false"')
        ->not->toContain('class="login-page is-standby"');

    foreach ([route('login', ['expired' => 'inactivity']), route('veille')] as $standbyUrl) {
        $standbyHtml = $this->get($standbyUrl)->assertOk()->getContent();

        expect($standbyHtml)
            ->toContain('class="login-page is-standby"')
            ->toContain('data-standby="true"')
            ->toContain('id="veille-panel"')
            ->toContain('id="veille-resume-btn"')
            ->toContain('Cliquez sur la porte pour reprendre le travail')
            ->toContain('Veille · 20 min d’inactivité')
            ->toContain('id="login-content-panel" class="login-content" aria-labelledby="login-title" inert')
            ->toContain('loginPanelSlideIn')
            ->toContain($tenant->name);
    }
});

test('une tentative de connexion échouée depuis l’écran de veille réaffiche directement le formulaire avec l’erreur', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();

    User::factory()->create([
        'role' => 'reception',
        'email' => 'agent@hotel.test',
        'password' => Hash::make('MotDePasseValide123!'),
        'is_active' => true,
    ]);

    $response = $this->from(route('login', ['expired' => 'inactivity']))
        ->followingRedirects()
        ->post(route('login'), [
            'email' => 'agent@hotel.test',
            'password' => 'MauvaisMotDePasse',
        ]);

    $response->assertOk()
        ->assertSee('data-standby="false"', false)
        ->assertSee('Identifiants incorrects', false)
        ->assertDontSee('class="login-page is-standby"', false);
    $this->assertGuest();
});
