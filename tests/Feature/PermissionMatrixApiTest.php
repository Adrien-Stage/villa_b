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
