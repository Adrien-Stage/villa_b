<?php

/**
 * Le catalogue des droits doit rester l'inventaire exact du comportement réel.
 *
 * Phase 0 de la matrice de droits : le catalogue existe, rien ne l'applique
 * encore. Sa seule valeur est d'être fidèle — s'il diverge des middlewares
 * « role: » qu'il décrit, la phase suivante remplacerait ces middlewares par
 * un comportement différent sans que personne le voie.
 *
 * Ce test parcourt la table de routage réelle et exige l'égalité. Il échouera
 * dès qu'une route changera de rôles sans que le catalogue suive — ce qui est
 * précisément le rappel voulu.
 */

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Route;

/** Rôles exigés par les middlewares d'une route, ou null si aucun. */
function rolesDeLaRoute(\Illuminate\Routing\Route $route): ?array
{
    $roles = [];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && str_starts_with($middleware, 'role:')) {
            $roles = array_merge($roles, explode(',', substr($middleware, 5)));
        }
    }

    if ($roles === []) {
        return null;
    }

    $roles = array_values(array_unique($roles));
    sort($roles);

    return $roles;
}

test('chaque route gardée par un rôle figure au catalogue, avec les mêmes rôles', function () {
    $ecarts = [];

    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();
        if ($nom === null) {
            continue;
        }

        $attendus = rolesDeLaRoute($route);
        if ($attendus === null) {
            continue;   // route publique ou seulement authentifiée : hors catalogue
        }

        $droit   = PermissionCatalog::permissionForRoute($nom);
        $declares = PermissionCatalog::roles($droit);
        sort($declares);

        if ($declares === []) {
            $ecarts[] = "{$nom} → droit « {$droit} » absent du catalogue";
        } elseif ($declares !== $attendus) {
            $ecarts[] = "{$droit} : catalogue [" . implode(',', $declares)
                . "] ≠ routes [" . implode(',', $attendus) . ']';
        }
    }

    expect($ecarts)->toBe([], "Catalogue et routes divergent :\n  " . implode("\n  ", $ecarts));
});

test('le catalogue ne déclare aucun droit sans route correspondante', function () {
    $droitsDesRoutes = [];

    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();
        if ($nom !== null && rolesDeLaRoute($route) !== null) {
            $droitsDesRoutes[] = PermissionCatalog::permissionForRoute($nom);
        }
    }

    $orphelins = array_values(array_diff(array_keys(PermissionCatalog::all()), $droitsDesRoutes));

    // Un droit sans route est un droit que rien ne peut accorder ni refuser :
    // il donnerait une case à cocher sans effet dans l'écran de l'ERP.
    expect($orphelins)->toBe([]);
});

test('la correspondance nom de route → droit normalise les verbes', function () {
    expect(PermissionCatalog::permissionForRoute('economat.items.store'))->toBe('economat.items.creer')
        ->and(PermissionCatalog::permissionForRoute('economat.items.index'))->toBe('economat.items.voir')
        ->and(PermissionCatalog::permissionForRoute('economat.items.destroy'))->toBe('economat.items.supprimer')
        // Les actions métier gardent leur nom : ce sont elles qui portent la
        // séparation des tâches.
        ->and(PermissionCatalog::permissionForRoute('economat.requisitions.approve'))->toBe('economat.requisitions.approve')
        ->and(PermissionCatalog::permissionForRoute('dashboard'))->toBe('dashboard.voir');
});

test("le catalogue couvre les modules attendus", function () {
    expect(PermissionCatalog::modules())
        ->toContain('economat', 'accounting', 'restaurant', 'bookings', 'rooms', 'shop', 'housekeeping');
});

test("il décrit le cumul de rôles qui pose problème aujourd'hui", function () {
    // marina@test.com porte econome + accountant + quality_auditor.
    // Le catalogue doit montrer que « accountant » ne tient aucun droit
    // d'économat : son accès vient entièrement du rôle econome.
    $droitsComptable = PermissionCatalog::forRole('accountant');
    $economat = array_filter($droitsComptable, fn ($d) => str_starts_with($d, 'economat.'));

    expect($economat)->toBe([]);

    // Et l'économe, lui, crée les articles — la fonction à retirer au comptable.
    expect(PermissionCatalog::roles('economat.items.creer'))->toContain('econome');
});
