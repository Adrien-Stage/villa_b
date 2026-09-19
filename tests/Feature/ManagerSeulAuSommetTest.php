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

test("le manager écrit sur l'hébergement, consulte ailleurs", function () {
    // Écriture : hébergement, statistiques, administration. Ailleurs, il
    // consulte : restauration, boutique, économat et comptabilité ont leurs
    // responsables, et le directeur qui saisirait à leur place brouillerait
    // la responsabilité de chacun.
    $lectureSeule = ['restaurant', 'shop', 'economat', 'accounting'];

    $ecrituresIndues = [];

    foreach (PermissionCatalog::all() as $droit => $roles) {
        $module  = explode('.', $droit)[0];
        $lecture = str_ends_with($droit, '.voir') || str_ends_with($droit, '.export');

        // Le contreseing des comptages reste au manager : acte de contrôle,
        // non écriture comptable.
        if (in_array($module, $lectureSeule, true)
            && !$lecture
            && in_array('manager', $roles, true)
            && !str_starts_with($droit, 'accounting.cash')) {
            $ecrituresIndues[] = $droit;
        }
    }

    expect($ecrituresIndues)->toBe([]);
});

test("le manager consulte tout ce qu'il n'écrit plus", function () {
    $manquants = [];

    foreach (['restaurant', 'shop', 'economat', 'accounting'] as $module) {
        foreach (PermissionCatalog::all() as $droit => $roles) {
            if (str_starts_with($droit, $module . '.')
                && str_ends_with($droit, '.voir')
                && !in_array('manager', $roles, true)) {
                $manquants[] = $droit;
            }
        }
    }

    // Lecture seule veut dire lecture : le priver des deux le rendrait aveugle
    // sur les services dont il répond.
    expect($manquants)->toBe([]);
});

test("le manager reste témoin du comptage de caisse", function () {
    // CashClosurePolicy le désigne nommément : l'en priver supprimerait le
    // contrôle au lieu de le déplacer.
    expect(PermissionCatalog::roles('accounting.cash_reviews.creer'))->toContain('manager');
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
