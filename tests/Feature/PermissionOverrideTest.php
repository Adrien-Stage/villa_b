<?php

/**
 * Surcharges de la matrice : ce que l'établissement ajoute ou retire au
 * catalogue, sans toucher au code.
 *
 * Le cas qui a ouvert ce chantier : un comptable rattaché à Comptabilité et
 * Finance cumulait econome, accountant et quality_auditor. Il consultait donc
 * l'économat — normal, il doit valoriser le stock — mais il y créait aussi des
 * articles, ce qui lui donnait la détention et l'enregistrement du même bien.
 */

use App\Models\PermissionGrant;
use App\Models\StockItem;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['economat', 'comptabilite', 'accounting', 'hebergement']);
});

function refuser(string $sujet, string $id, string $permission): PermissionGrant
{
    return PermissionGrant::create([
        'subject_type' => $sujet,
        'subject_id'   => $id,
        'permission'   => $permission,
        'effect'       => PermissionGrant::EFFET_DENY,
        'reason'       => 'Séparation des tâches : détention et enregistrement.',
    ]);
}

test('sans surcharge, la décision est celle du catalogue', function () {
    $resolveur = app(PermissionResolver::class);
    $econome   = User::factory()->create(['role' => 'econome']);

    expect($resolveur->allows($econome, 'economat.items.creer'))->toBeTrue()
        ->and($resolveur->allows($econome, 'accounting.voir'))->toBeFalse();
});

test("un refus posé sur un rôle retire le droit à ceux qui le portent", function () {
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.creer');

    $resolveur = app(PermissionResolver::class);
    $econome   = User::factory()->create(['role' => 'econome']);

    expect($resolveur->allows($econome, 'economat.items.creer'))->toBeFalse()
        // Le reste de l'économat lui reste ouvert.
        ->and($resolveur->allows($econome, 'economat.items.voir'))->toBeTrue();
});

test("le refus l'emporte sur l'autorisation d'un second rôle", function () {
    // C'est tout l'enjeu du cumul : sans cette règle, il suffirait d'un autre
    // rôle pour rendre le refus inopérant.
    refuser(PermissionGrant::SUJET_ROLE, 'accountant', 'economat.items.creer');

    $comptable = User::factory()->create(['role' => 'accountant']);
    $comptable->roles()->attach(\App\Models\Role::create([
        'name' => 'Économe', 'slug' => 'econome', 'module' => 'economat', 'is_assignable' => true,
    ]));

    // econome donne le droit, accountant le refuse : le refus gagne.
    expect(app(PermissionResolver::class)->allows($comptable->fresh(), 'economat.items.creer'))->toBeFalse();
});

test("une autorisation nominative ouvre un droit que le rôle ne donne pas", function () {
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) ($comptable = User::factory()->create(['role' => 'accountant']))->id,
        'permission'   => 'economat.items.voir',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'reason'       => 'Valorisation du stock en clôture.',
    ]);

    expect(app(PermissionResolver::class)->allows($comptable, 'economat.items.voir'))->toBeTrue()
        // Consulter, oui. Créer, non : l'exception ne porte que sur le droit nommé.
        ->and(app(PermissionResolver::class)->allows($comptable, 'economat.items.creer'))->toBeFalse();
});

test("la surcharge nominative écrase celle du rôle", function () {
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.creer');

    $econome = User::factory()->create(['role' => 'econome']);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) $econome->id,
        'permission'   => 'economat.items.creer',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'reason'       => "Dérogation du directeur : établissement de six personnes.",
    ]);

    expect(app(PermissionResolver::class)->allows($econome, 'economat.items.creer'))->toBeTrue();
});

test('le refus se voit depuis la route, pas seulement depuis le résolveur', function () {
    $econome = User::factory()->create(['role' => 'econome']);

    $this->actingAs($econome)->get('/economat/articles')->assertOk();

    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.voir');
    app(PermissionResolver::class)->forget();

    expect($this->actingAs($econome)->get('/economat/articles')->status())->not->toBe(200);
});

test('un compte désactivé ne détient plus rien', function () {
    $econome = User::factory()->create(['role' => 'econome', 'is_active' => false]);

    expect(app(PermissionResolver::class)->allows($econome, 'economat.items.voir'))->toBeFalse();
});

test('les droits retirés à une personne sont énumérables', function () {
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.creer');
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.supprimer');

    $retires = app(PermissionResolver::class)->deniedPermissions(User::factory()->create(['role' => 'econome']));
    sort($retires);

    // De quoi montrer au directeur ce que la matrice de son établissement
    // retire au gabarit d'origine.
    expect($retires)->toBe(['economat.items.creer', 'economat.items.supprimer']);
});

test('un même droit ne peut pas être posé deux fois sur le même sujet', function () {
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.creer');
    refuser(PermissionGrant::SUJET_ROLE, 'econome', 'economat.items.creer');
})->throws(\Illuminate\Database\QueryException::class);

test("le motif accompagne la décision", function () {
    $ligne = refuser(PermissionGrant::SUJET_ROLE, 'accountant', 'economat.stock.ajuster');

    // Une matrice qui change sans trace ne se contrôle pas.
    expect($ligne->reason)->not->toBeNull();
});
