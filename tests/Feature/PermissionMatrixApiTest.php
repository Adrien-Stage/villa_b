<?php

/**
 * Matrice des droits exposée à la console d'orchestration.
 *
 * L'ERP édite, l'application applique. Seuls les écarts au catalogue
 * transitent : le gabarit vit dans le code et suit les routes, le recopier en
 * base le périmerait au premier droit ajouté.
 */

use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['reporting.secret' => 'jeton-de-service']);
    activerModules(['api', 'economat']);
});

function entete(): array
{
    return ['Authorization' => 'Bearer jeton-de-service'];
}

test('sans jeton, la matrice reste fermée', function () {
    $this->getJson('/api/permissions/matrice')->assertStatus(401);
    $this->putJson('/api/permissions/matrice', ['ecarts' => []])->assertStatus(401);
});

test('un mauvais jeton ne passe pas davantage', function () {
    $this->getJson('/api/permissions/matrice', ['Authorization' => 'Bearer faux'])->assertStatus(401);
});

test('la lecture rend le gabarit, les écarts et les incompatibilités', function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => 'accountant',
        'permission'   => 'economat.items.creer',
        'effect'       => PermissionGrant::EFFET_DENY,
        'reason'       => 'Séparation des tâches.',
    ]);

    $reponse = $this->getJson('/api/permissions/matrice', entete())->assertOk();

    // Les clés du catalogue contiennent des points : on lit le tableau entier
    // plutôt que de passer par la notation pointée.
    $catalogue = $reponse->json('catalogue');

    expect($catalogue['economat.items.creer'])->toBe(['econome'])
        ->and($reponse->json('ecarts.0.permission'))->toBe('economat.items.creer')
        ->and($reponse->json('ecarts.0.effect'))->toBe('deny')
        ->and($reponse->json('incompatibilites'))->not->toBeEmpty()
        ->and($reponse->json('modules'))->toContain('economat');
});

test('la lecture décrit la hiérarchie des rôles', function () {
    $roles = collect($this->getJson('/api/permissions/matrice', entete())->assertOk()->json('roles'))
        ->keyBy('slug');

    // La console en tirera le regroupement par niveau et par service.
    expect($roles['admin']['level'])->toBe(1)
        ->and($roles['econome']['includes'])->toBe(['storekeeper'])
        ->and($roles['storekeeper']['statut'])->toBe('actif')
        ->and($roles['it_support']['statut'])->toBe('retire')
        ->and($roles['controller']['level'])->toBeNull();
});

test('les rôles envoyés remplacent les écarts précédents', function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'econome',
        'permission' => 'economat.items.supprimer', 'effect' => PermissionGrant::EFFET_DENY,
    ]);

    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [
            ['role' => 'accountant', 'permission' => 'economat.items.creer', 'effect' => 'deny', 'reason' => 'Séparation des tâches.'],
        ],
    ], entete())->assertOk()->assertJson(['appliques' => 1]);

    // Remplacement, non fusion : un refus retiré de l'écran doit disparaître
    // ici, sans quoi la matrice affichée cesserait de décrire la réalité.
    $this->assertDatabaseMissing('permission_grants', ['permission' => 'economat.items.supprimer']);
    $this->assertDatabaseHas('permission_grants', ['subject_id' => 'accountant', 'effect' => 'deny']);
});

test("les dérogations nominatives survivent à une mise à jour des rôles", function () {
    $employe = User::factory()->create(['role' => 'econome']);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER, 'subject_id' => (string) $employe->id,
        'permission' => 'economat.items.creer', 'effect' => PermissionGrant::EFFET_ALLOW,
        'reason' => 'Dérogation du directeur.',
    ]);

    $this->putJson('/api/permissions/matrice', ['ecarts' => []], entete())->assertOk();

    // Accordées sur place par le directeur : la console n'a pas à les écraser.
    $this->assertDatabaseHas('permission_grants', [
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) $employe->id,
    ]);
});

test("la console ne remplace que sa couche : les écarts de l'hôtel survivent", function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'reception',
        'permission' => 'customers.export', 'effect' => PermissionGrant::EFFET_DENY,
        'origin' => PermissionGrant::ORIGINE_ETABLISSEMENT, 'reason' => "Décision de l'hôtel.",
    ]);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'econome',
        'permission' => 'economat.items.creer', 'effect' => PermissionGrant::EFFET_DENY,
        'origin' => PermissionGrant::ORIGINE_ERP, 'reason' => 'Décision de la console.',
    ]);

    $this->putJson('/api/permissions/matrice', ['ecarts' => []], entete())->assertOk();

    expect(PermissionGrant::where('origin', PermissionGrant::ORIGINE_ETABLISSEMENT)->count())->toBe(1)
        ->and(PermissionGrant::where('origin', PermissionGrant::ORIGINE_ERP)->count())->toBe(0);
});

test("la console peut poser un écart que l'hôtel a déjà posé", function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'econome',
        'permission' => 'economat.items.creer', 'effect' => PermissionGrant::EFFET_DENY,
        'origin' => PermissionGrant::ORIGINE_ETABLISSEMENT, 'reason' => "Décision de l'hôtel.",
    ]);

    $this->putJson('/api/permissions/matrice', ['ecarts' => [[
        'role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny',
        'reason' => 'Même décision, prise par la console.',
    ]]], entete())->assertOk();

    expect(PermissionGrant::where('permission', 'economat.items.creer')->count())->toBe(2);
});

test('un droit absent du catalogue est refusé', function () {
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [['role' => 'econome', 'permission' => 'economat.licornes.creer', 'effect' => 'deny']],
    ], entete())
        ->assertStatus(422)
        ->assertJson(['inconnus' => ['economat.licornes.creer']]);

    // Une case cochée sans effet est pire qu'une case absente.
    $this->assertDatabaseCount('permission_grants', 0);
});

test('un effet inconnu est rejeté', function () {
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'peut-etre']],
    ], entete())->assertStatus(422);
});

test("l'écart posé par la console agit immédiatement", function () {
    $econome = User::factory()->create(['role' => 'econome']);

    expect(app(PermissionResolver::class)->allows($econome, 'economat.items.creer'))->toBeTrue();

    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny']],
    ], entete())->assertOk();

    expect(app(PermissionResolver::class)->allows($econome->fresh(), 'economat.items.creer'))->toBeFalse();
});

test("la portée fait l'aller-retour", function () {
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [[
            'role' => 'controller', 'permission' => 'users.voir',
            'effect' => 'allow', 'scope' => 'departement', 'reason' => 'Cloisonnement par service.',
        ]],
    ], entete())->assertOk();

    $lu = $this->getJson('/api/permissions/matrice', entete())->json('ecarts.0');

    expect($lu['scope'])->toBe('departement');
    expect(app(PermissionResolver::class)->scopeFor(
        User::factory()->create(['role' => 'controller']),
        'users.voir'
    ))->toBe('departement');
});

test('une portée inconnue est rejetée', function () {
    $this->putJson('/api/permissions/matrice', [
        'ecarts' => [['role' => 'controller', 'permission' => 'users.voir', 'effect' => 'allow', 'scope' => 'planete']],
    ], entete())->assertStatus(422);
});

test('les portées possibles sont annoncées', function () {
    expect(collect($this->getJson('/api/permissions/matrice', entete())->json('portees'))->pluck('valeur')->all())
        ->toBe(['propre', 'departement', 'etablissement']);
});

// ── Contrat v2 : couches, personnel, cumuls, aperçu ─────────────────────────

test("le canal d'orchestration ne dépend pas du module api", function () {
    // Option commerciale coupée, la console doit encore administrer l'hôtel.
    activerModules(['economat']);

    $this->getJson('/api/permissions/matrice', entete())->assertOk();
});

test("avec un secret d'orchestration, le jeton de reporting n'ouvre plus la matrice", function () {
    // Le module GRC détient le jeton de reporting pour lire les chiffres : il
    // ne doit pas pouvoir régler les droits.
    config(['orchestration.secret' => 'jeton-console']);

    $this->getJson('/api/permissions/matrice', entete())->assertStatus(401);
    $this->getJson('/api/permissions/matrice', ['Authorization' => 'Bearer jeton-console'])->assertOk();
});

test('la lecture annonce sa version, les écritures et les droits bornés', function () {
    $reponse = $this->getJson('/api/permissions/matrice', entete())->assertOk();

    expect($reponse->json('version'))->toBe(2)
        ->and($reponse->json('ecritures'))->toContain('economat.items.creer')
        ->and($reponse->json('ecritures'))->not->toContain('economat.items.voir')
        ->and($reponse->json('droits_bornes'))->toBe(['users.voir'])
        ->and($reponse->json('empreinte'))->toBeString();
});

test('la lecture dit quels rôles se règlent et combien de comptes les portent', function () {
    \App\Support\RoleCatalog::sync();
    $econome = User::factory()->create(['role' => 'econome']);
    $econome->roles()->sync(\App\Models\Role::where('slug', 'econome')->pluck('id'));
    User::factory()->create(['role' => 'econome', 'is_active' => false]);

    $roles = collect($this->getJson('/api/permissions/matrice', entete())->json('roles'))->keyBy('slug');

    // L'administrateur consulte tout et n'écrit que la configuration et les
    // comptes, par construction ; un rôle retiré ne donne plus rien.
    expect($roles['admin']['reglable'])->toBeFalse()
        ->and($roles['it_support']['reglable'])->toBeFalse()
        ->and($roles['manager']['reglable'])->toBeTrue()
        // Seuls les comptes actifs comptent.
        ->and($roles['econome']['titulaires'])->toBe(1)
        ->and($roles['econome']['description'])->not->toBeEmpty();
});

test("une case qui ouvrirait un cumul est signalée avant qu'on la coche", function () {
    $reponse = $this->getJson('/api/permissions/matrice', entete())->assertOk();

    $cumuls = $reponse->json('cumuls');
    $regles = $reponse->json('regles_de_cumul');

    // Le caissier qui contresigne des caisses est un caissier comptable.
    expect($cumuls)->toHaveKey('cashier|accounting.cash_reviews.creer');

    $regle = $regles[$cumuls['cashier|accounting.cash_reviews.creer'][0]];
    expect($regle['roles'])->toContain('cashier')->toContain('accountant');

    // Une consultation n'ouvre aucun cumul.
    expect($cumuls)->not->toHaveKey('cashier|accounting.voir');
});

test('un cumul sans dérogation est refusé, et rien ne change', function () {
    $this->putJson('/api/permissions/matrice', ['ecarts' => [[
        'role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow',
        'reason' => 'Manque de personnel.',
    ]]], entete())
        ->assertStatus(422)
        ->assertJsonPath('cumuls.0.role', 'cashier');

    $this->assertDatabaseCount('permission_grants', 0);
});

test('une dérogation motivée passe, et le journal la garde', function () {
    $this->putJson('/api/permissions/matrice', [
        'derogation' => true,
        'auteur' => 'Ada <ada@wetchah.test>',
        'ecarts' => [[
            'role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow',
            'reason' => 'Établissement de six personnes : pas de comptable sur place le soir.',
        ]],
    ], entete())->assertOk()->assertJsonPath('cumuls.0.permission', 'accounting.cash_reviews.creer');

    $this->assertDatabaseHas('audit_logs', ['event_type' => 'duty_segregation_override', 'module' => 'security']);
});

test('une dérogation sans motif est refusée', function () {
    $this->putJson('/api/permissions/matrice', [
        'derogation' => true,
        'ecarts' => [['role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow']],
    ], entete())->assertStatus(422);

    $this->assertDatabaseCount('permission_grants', 0);
});

test("le contrôle ne reçoit aucune écriture sans dérogation", function () {
    // Bug corrigé en phase 0 : le contrôleur de gestion approuvait des achats.
    $this->putJson('/api/permissions/matrice', ['ecarts' => [[
        'role' => 'controller', 'permission' => 'economat.purchase_requests.approve', 'effect' => 'allow',
        'reason' => 'Essai.',
    ]]], entete())->assertStatus(422);
});

test("la console ne règle ni l'administrateur ni un rôle retiré", function () {
    $this->putJson('/api/permissions/matrice', ['ecarts' => [
        ['role' => 'admin', 'permission' => 'economat.items.creer', 'effect' => 'allow'],
        ['role' => 'it_support', 'permission' => 'economat.items.voir', 'effect' => 'allow'],
    ]], entete())
        ->assertStatus(422)
        ->assertJson(['roles' => ['admin', 'it_support']]);
});

test('deux écarts sur la même case sont refusés', function () {
    $this->putJson('/api/permissions/matrice', ['ecarts' => [
        ['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny'],
        ['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'allow'],
    ]], entete())->assertStatus(422)->assertJson(['doublons' => ['econome|economat.items.creer']]);
});

test("un écran ouvert sur une couche périmée n'écrase pas le travail d'un autre", function () {
    $empreinte = $this->getJson('/api/permissions/matrice', entete())->json('empreinte');

    // Un autre opérateur enregistre entre-temps.
    $this->putJson('/api/permissions/matrice', ['ecarts' => [
        ['role' => 'econome', 'permission' => 'economat.items.supprimer', 'effect' => 'deny', 'reason' => 'Autre opérateur.'],
    ]], entete())->assertOk();

    $this->putJson('/api/permissions/matrice', ['empreinte' => $empreinte, 'ecarts' => []], entete())
        ->assertStatus(409);

    $this->assertDatabaseHas('permission_grants', ['permission' => 'economat.items.supprimer']);
});

test("l'aperçu dit qui perd quoi, sans rien enregistrer", function () {
    \App\Support\RoleCatalog::sync();
    $econome = User::factory()->create(['name' => 'Rose Ngo', 'role' => 'econome']);
    $econome->roles()->sync(\App\Models\Role::where('slug', 'econome')->pluck('id'));

    $reponse = $this->postJson('/api/permissions/matrice/apercu', ['ecarts' => [
        ['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny'],
    ]], entete())->assertOk();

    $personne = collect($reponse->json('personnes'))->firstWhere('id', $econome->id);

    expect($personne['name'])->toBe('Rose Ngo')
        ->and($personne['perdus'])->toBe(['economat.items.creer'])
        ->and($personne['gagnes'])->toBe([]);

    // Rien n'a été écrit : la décision reste celle d'avant.
    $this->assertDatabaseCount('permission_grants', 0);
    expect(app(PermissionResolver::class)->allows($econome->fresh(), 'economat.items.creer'))->toBeTrue();
});

test("l'aperçu signale les cumuls du lot", function () {
    $this->postJson('/api/permissions/matrice/apercu', ['ecarts' => [
        ['role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow'],
    ]], entete())->assertOk()->assertJsonPath('cumuls.0.role', 'cashier');
});

test("l'aperçu suit une portée resserrée", function () {
    \App\Support\RoleCatalog::sync();
    $controleur = User::factory()->create(['role' => 'controller']);
    $controleur->roles()->sync(\App\Models\Role::where('slug', 'controller')->pluck('id'));

    $personne = collect($this->postJson('/api/permissions/matrice/apercu', ['ecarts' => [
        ['role' => 'controller', 'permission' => 'users.voir', 'effect' => 'allow', 'scope' => 'departement'],
    ]], entete())->json('personnes'))->firstWhere('id', $controleur->id);

    expect($personne['portees'][0])->toBe(['permission' => 'users.voir', 'avant' => 'etablissement', 'apres' => 'departement']);
});

test('les exceptions échues sont montrées', function () {
    $employe = User::factory()->create(['role' => 'accountant']);

    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER, 'subject_id' => (string) $employe->id,
        'permission' => 'economat.items.creer', 'effect' => PermissionGrant::EFFET_ALLOW,
        'origin' => PermissionGrant::ORIGINE_ETABLISSEMENT, 'reason' => 'Inventaire de fin d’année.',
        'expires_at' => now()->subDay(),
    ]);

    $reponse = $this->getJson('/api/permissions/matrice', entete())->assertOk();

    expect($reponse->json('exceptions_echues.0.permission'))->toBe('economat.items.creer')
        // Échue : elle n'est plus en vigueur.
        ->and(collect($reponse->json('ecarts'))->where('subject_type', 'user')->count())->toBe(0);
});

test('la revue des comptes accompagne la matrice', function () {
    User::factory()->create(['role' => 'reception']);

    $codes = collect($this->getJson('/api/permissions/matrice', entete())->json('constats'))->pluck('code');

    expect($codes)->toContain('aucun_comptable')->toContain('aucun_administrateur');
});
