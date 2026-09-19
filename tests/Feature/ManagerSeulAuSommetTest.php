<?php

/**
 * « manager » est le seul rôle qui détient tout dans un établissement.
 *
 * « admin » figurait dans 61 droits sur 226 et dans deux départements. Ce
 * n'est pourtant pas un rôle d'établissement : c'est l'identité de la console
 * de supervision, qui gère les hôtels eux-mêmes. Le laisser dans la matrice
 * entretenait deux sommets là où il n'en faut qu'un, et donnait à un compte
 * hors établissement des droits sur les opérations de cet établissement.
 */

use App\Models\User;
use App\Support\DepartmentRoles;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('aucun droit ne cite admin', function () {
    $restants = array_keys(array_filter(
        PermissionCatalog::all(),
        fn (array $roles) => in_array('admin', $roles, true)
    ));

    expect($restants)->toBe([]);
});

test("admin ne figure plus au référentiel des rôles d'établissement", function () {
    expect(array_column(RoleCatalog::all(), 'slug'))->not->toContain('admin');
});

test('aucun département ne confère admin', function () {
    foreach (DepartmentRoles::all() as $departement => $roles) {
        expect($roles)->not->toContain('admin', "Le département {$departement} confère encore admin.");
    }
});

test("le manager est au sommet partout, sauf dans les deux points de vente", function () {
    $sansManager = array_keys(array_filter(
        PermissionCatalog::all(),
        fn (array $roles) => !in_array('manager', $roles, true)
    ));

    // 49 droits lui échappent, tous sous restaurant. et shop. : gestes de
    // service réservés au chef de salle et au responsable boutique — prendre
    // une commande, l'envoyer en cuisine, ouvrir ou fermer la caisse du point
    // de vente. « admin » ne les détenait pas davantage : ce partage est
    // antérieur au présent changement, et reste à trancher.
    $horsPointDeVente = array_values(array_filter(
        $sansManager,
        fn (string $droit) => !str_starts_with($droit, 'restaurant.') && !str_starts_with($droit, 'shop.')
    ));

    expect($horsPointDeVente)->toBe([]);
});

test("tout droit autrefois tenu par admin est tenu par manager", function () {
    // La référence figée a été mise à jour en remplaçant admin par manager :
    // si un droit avait été perdu au passage, elle ne correspondrait plus.
    $reference = json_decode(
        file_get_contents(base_path('tests/Fixtures/route_roles_avant_phase1.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    $orphelins = [];
    foreach ($reference as $route => $roles) {
        if ($roles === []) {
            $orphelins[] = $route;
        }
    }

    // Aucune route ne doit se retrouver sans détenteur après le retrait.
    expect($orphelins)->toBe([]);
});

test("la console de supervision reste gardée, hors de la matrice", function () {
    // AdminOnly s'appuie sur isAdmin(), qui retombe sur la colonne users.role.
    // Retirer admin du référentiel ne touche donc pas la console.
    $support = User::factory()->create(['role' => 'admin']);

    expect($support->isAdmin())->toBeTrue();

    $this->actingAs($support)->get('/admin/dashboard')->assertOk();
});

test("un compte de supervision n'a plus de droits d'exploitation", function () {
    activerModules(['economat', 'comptabilite', 'accounting', 'hebergement']);

    $support = User::factory()->create(['role' => 'admin']);

    // Il gère les hôtels, il ne tient pas leur économat.
    expect($this->actingAs($support)->get('/economat/articles')->status())->not->toBe(200);
});

test('le manager, lui, entre partout', function (string $url) {
    activerModules(['economat', 'comptabilite', 'accounting', 'hebergement', 'reservations', 'utilisateurs']);

    $this->actingAs(User::factory()->create(['role' => 'manager']))->get($url)->assertOk();
})->with(['/economat/articles', '/accounting', '/users', '/rooms', '/bookings']);
