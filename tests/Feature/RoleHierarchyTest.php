<?php

/**
 * La hiérarchie des rôles : niveaux, inclusions, statuts.
 *
 * Un hôtel s'organise en services, chacun dirigé par un chef qui fait aussi
 * le travail de ses membres. Le référentiel le déclare une fois (RoleCatalog)
 * et le catalogue des droits en tire les conséquences : un chef détient les
 * droits de ses membres sans qu'on les recopie à la main.
 */

use App\Models\Role;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Rôles de la hiérarchie : ni retirés, ni hors hiérarchie. */
function rolesHierarchiques(): array
{
    return array_values(array_filter(
        RoleCatalog::all(),
        fn (array $r) => $r['statut'] !== RoleCatalog::RETIRE && $r['level'] !== null
    ));
}

test('chaque rôle de la hiérarchie a un niveau de 1 à 4 et un service', function () {
    foreach (rolesHierarchiques() as $role) {
        expect($role['level'])->toBeIn([1, 2, 3, 4], "Niveau invalide pour {$role['slug']}")
            ->and(Role::MODULES)->toHaveKey($role['module']);
    }
});

test('un chef détient tous les droits de ses membres', function () {
    foreach (RoleCatalog::all() as $chef) {
        foreach ($chef['includes'] ?? [] as $membre) {
            $manquants = array_diff(PermissionCatalog::forRole($membre), PermissionCatalog::forRole($chef['slug']));

            expect($manquants)->toBe([], "{$chef['slug']} n'a pas tous les droits de {$membre}");
        }
    }
});

test("un chef dirige des membres de son service, d'un niveau inférieur", function () {
    foreach (RoleCatalog::all() as $chef) {
        foreach ($chef['includes'] ?? [] as $slug) {
            $membre = RoleCatalog::find($slug);

            expect($membre)->not->toBeNull()
                ->and($membre['module'])->toBe($chef['module'])
                ->and($membre['level'])->toBeGreaterThan($chef['level']);
        }
    }
});

test('les inclusions se suivent dans les deux sens', function () {
    expect(RoleCatalog::developper(['finance_manager']))->toContain('accountant')
        ->and(RoleCatalog::developper(['restaurant_manager']))->toContain('cashier')
        ->and(RoleCatalog::avecCeuxQuiLesIncluent(['storekeeper']))->toContain('econome')
        // Un rôle sans chef ne gagne personne.
        ->and(RoleCatalog::avecCeuxQuiLesIncluent(['controller']))->toBe(['controller']);
});

test('seuls les rôles actifs non privilégiés s\'attribuent', function () {
    foreach (RoleCatalog::all() as $role) {
        if ($role['statut'] !== RoleCatalog::ACTIF) {
            expect($role['is_assignable'])->toBeFalse("{$role['slug']} n'est pas en service");
        }
    }

    foreach (['admin', 'manager', 'customer_guest'] as $privilegie) {
        expect(RoleCatalog::find($privilegie)['is_assignable'])->toBeFalse();
    }
});

test('les rôles retirés restent connus mais ne donnent aucun droit', function () {
    foreach (['rh_manager', 'it_support'] as $retire) {
        expect(RoleCatalog::find($retire)['statut'])->toBe(RoleCatalog::RETIRE)
            ->and(PermissionCatalog::forRole($retire))->toBe([]);
    }
});

test("il n'y a pas de caissier à l'hébergement : le rôle historique est celui du restaurant", function () {
    $caissier = RoleCatalog::find('cashier');

    expect($caissier['name'])->toBe('Caissier restaurant')
        ->and($caissier['module'])->toBe('restaurant')
        ->and(PermissionCatalog::forRole('cashier'))->toContain('restaurant.billing.paid');
});

test('le magasinier réceptionne et sert, le chef économe commande', function () {
    $magasinier = PermissionCatalog::forRole('storekeeper');

    expect($magasinier)->toContain('economat.receipts.creer')
        ->and($magasinier)->toContain('economat.orders.receive')
        ->and($magasinier)->toContain('economat.requisitions.deliver')
        ->and($magasinier)->not->toContain('economat.orders.creer')
        ->and($magasinier)->not->toContain('economat.items.creer')
        ->and($magasinier)->not->toContain('economat.purchase_requests.approve');
});

test('le responsable de restaurant encaisse par le caissier qu\'il inclut', function () {
    expect(PermissionCatalog::forRole('restaurant_manager'))->toContain('restaurant.billing.paid')
        ->and(PermissionCatalog::forRole('restaurant_manager'))->toContain('restaurant.menus.items.creer');
});

test('la synchronisation écrit le référentiel sans ses champs de hiérarchie', function () {
    // Niveau, inclusions et statut vivent dans le code : la table n'a pas ces
    // colonnes, et les y écrire ferait échouer la synchronisation.
    $resultat = RoleCatalog::sync();

    expect($resultat['created'] + $resultat['updated'])->toBe(count(RoleCatalog::all()))
        ->and(Role::where('slug', 'storekeeper')->first()->is_assignable)->toBeFalse()
        ->and(Role::where('slug', 'it_support')->first()->is_assignable)->toBeFalse()
        ->and(Role::where('slug', 'econome')->first()->name)->toBe('Chef économe');
});
