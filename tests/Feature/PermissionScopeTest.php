<?php

/**
 * Portée des droits : le département borne les données.
 *
 * Décision prise, puis à moitié appliquée : le département avait cessé
 * d'accorder des rôles sans pour autant restreindre quoi que ce soit. Il ne
 * faisait donc plus rien. La portée lui rend un effet — borner les données,
 * pas ouvrir des portes.
 */

use App\Models\Department;
use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\PermissionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => activerModules(['utilisateurs', 'economat']));

function porterA(string $role, string $permission, string $portee): PermissionGrant
{
    return PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => $role,
        'permission'   => $permission,
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'scope'        => $portee,
        'reason'       => 'Cloisonnement par service.',
    ]);
}

function departement(string $slug): Department
{
    return Department::firstOrCreate(
        ['slug' => $slug],
        ['name' => ucfirst($slug), 'code' => mb_strtoupper(mb_substr($slug, 0, 3)), 'is_active' => true]
    );
}

test("sans portée déclarée, rien ne se restreint", function () {
    expect(app(PermissionResolver::class)->scopeFor(User::factory()->create(['role' => 'manager']), 'users.voir'))
        ->toBe(PermissionScope::ETABLISSEMENT);
});

test('une portée posée sur un rôle est rendue par le résolveur', function () {
    porterA('controller', 'users.voir', PermissionScope::DEPARTEMENT);

    expect(app(PermissionResolver::class)->scopeFor(User::factory()->create(['role' => 'controller']), 'users.voir'))
        ->toBe(PermissionScope::DEPARTEMENT);
});

test("la portée la plus étroite l'emporte sur le cumul de rôles", function () {
    // Même règle que pour le refus : une restriction ne se lève pas en
    // ajoutant un rôle.
    expect(PermissionScope::laPlusEtroite(PermissionScope::ETABLISSEMENT, PermissionScope::PROPRE))
        ->toBe(PermissionScope::PROPRE)
        ->and(PermissionScope::laPlusEtroite(PermissionScope::DEPARTEMENT, PermissionScope::ETABLISSEMENT))
        ->toBe(PermissionScope::DEPARTEMENT)
        ->and(PermissionScope::laPlusEtroite(null, null))->toBe(PermissionScope::ETABLISSEMENT);
});

test('la liste du staff se borne au département quand la matrice le demande', function () {
    $hk  = departement('housekeeping_hebergement');
    $fin = departement('comptabilite_finance');

    User::factory()->create(['name' => 'Agent Étage', 'role' => 'housekeeping_staff', 'department_id' => $hk->id]);
    User::factory()->create(['name' => 'Aide Comptable', 'role' => 'cashier', 'department_id' => $fin->id]);

    // Le contrôleur de gestion consulte le staff sans l'administrer : c'est
    // exactement le cas où la portée a un sens.
    $controleur = User::factory()->create(['role' => 'controller', 'department_id' => $hk->id]);

    // Sans portée : il voit les deux.
    $this->actingAs($controleur)->get('/users')->assertSee('Agent Étage')->assertSee('Aide Comptable');

    porterA('controller', 'users.voir', PermissionScope::DEPARTEMENT);
    app(PermissionResolver::class)->forget();

    $this->actingAs($controleur)->get('/users')
        ->assertSee('Agent Étage')
        ->assertDontSee('Aide Comptable');
});

test("sans département, la portée « département » ne montre que soi", function () {
    $hk = departement('housekeeping_hebergement');
    User::factory()->create(['name' => 'Agent Étage', 'role' => 'housekeeping_staff', 'department_id' => $hk->id]);

    porterA('controller', 'users.voir', PermissionScope::DEPARTEMENT);

    // Laisser tout voir ferait de l'absence de rattachement un passe-droit.
    $orphelin = User::factory()->create(['role' => 'controller', 'department_id' => null]);

    $this->actingAs($orphelin)->get('/users')->assertDontSee('Agent Étage');
});

test('le manager garde la vue sur tout le staff', function () {
    $fin = departement('comptabilite_finance');
    User::factory()->create(['name' => 'Aide Comptable', 'role' => 'cashier', 'department_id' => $fin->id]);

    porterA('controller', 'users.voir', PermissionScope::DEPARTEMENT);

    $this->actingAs(User::factory()->create(['role' => 'manager', 'department_id' => null]))
        ->get('/users')->assertSee('Aide Comptable');
});

test('une portée inconnue est ignorée plutôt que de tout fermer', function () {
    expect(PermissionScope::valide('planete'))->toBeFalse()
        ->and(PermissionScope::laPlusEtroite('planete', null))->toBe(PermissionScope::ETABLISSEMENT);
});
