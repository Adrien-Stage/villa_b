<?php

/**
 * Le département restreint les données. Il n'accorde plus de rôles.
 *
 * Il en conférait, sans que rien ne le montre : la carte vivait dans un
 * `match` au milieu d'EnsureRoleAccess. Rattacher un réceptionniste à
 * « Direction Générale » lui donnait manager et admin — donc la gestion des
 * utilisateurs et la comptabilité. Mesuré avant retrait, la même personne
 * passait de 302 à 200 sur /users par le seul effet de son rattachement.
 */

use App\Models\Department;
use App\Models\User;
use App\Support\DepartmentRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['hebergement', 'reservations', 'comptabilite', 'accounting', 'utilisateurs', 'economat']);
});

function membreDe(string $slugDepartement, string $role): User
{
    $departement = Department::firstOrCreate(
        ['slug' => $slugDepartement],
        ['name' => ucfirst($slugDepartement), 'code' => mb_strtoupper(mb_substr($slugDepartement, 0, 3)), 'is_active' => true]
    );

    return User::factory()->create(['role' => $role, 'department_id' => $departement->id]);
}

test("le rattachement à la direction ne donne plus la gestion des utilisateurs", function () {
    $reponse = $this->actingAs(membreDe('direction_generale', 'reception'))->get('/users');

    // Auparavant : 200, par l'octroi implicite de « manager, admin ».
    expect($reponse->status())->not->toBe(200);
});

test("le rattachement à la comptabilité ne donne plus la comptabilité", function () {
    $reponse = $this->actingAs(membreDe('comptabilite_finance', 'housekeeping_staff'))->get('/accounting');

    expect($reponse->status())->not->toBe(200);
});

test("le rôle réellement détenu continue d'ouvrir l'accès", function () {
    // Le département ne retire rien non plus : seul le rôle compte désormais.
    $this->actingAs(membreDe('comptabilite_finance', 'accountant'))
        ->get('/accounting')
        ->assertOk();
});

test("un département n'a aucun effet sur l'accès, quel qu'il soit", function (string $departement) {
    $sans = $this->actingAs(User::factory()->create(['role' => 'reception']))->get('/users')->status();
    $avec = $this->actingAs(membreDe($departement, 'reception'))->get('/users')->status();

    expect($avec)->toBe($sans);
})->with(['direction_generale', 'comptabilite_finance', 'informatique_it', 'qualite_controle']);

test("la carte historique reste disponible pour l'audit", function () {
    // DepartmentRoles n'est plus consultée par le middleware, mais la commande
    // roles:audit-departements en a besoin pour dire qui perd quoi.
    expect(DepartmentRoles::for('direction_generale'))->toBe(['manager', 'admin'])
        ->and(DepartmentRoles::for('departement_inconnu'))->toBe([]);
});

test("la commande d'audit nomme les comptes concernés", function () {
    membreDe('direction_generale', 'reception');

    $this->artisan('roles:audit-departements')
        ->expectsOutputToContain("dépendaient de l'octroi implicite")
        ->assertExitCode(0);
});

test("la commande reste muette quand aucun compte ne dépendait du département", function () {
    // « ressources_humaines » ne conférait que « manager ». Un manager qui y
    // est rattaché détient déjà tout ce que le département donnait : il ne
    // perd rien, et n'a donc pas à figurer au rapport.
    membreDe('ressources_humaines', 'manager');

    $this->artisan('roles:audit-departements')
        ->expectsOutputToContain('Aucun compte ne dépendait')
        ->assertExitCode(0);
});
