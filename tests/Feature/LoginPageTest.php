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
        ->assertSee('storage/logos/palmier.png', false)
        ->assertSee('accueil@lepalmier.test', false)
        ->assertSee('+237 699 00 11 22', false)
        ->assertDontSee('interne-prive@lepalmier.test', false);
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
