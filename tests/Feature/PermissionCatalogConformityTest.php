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

/** La route est-elle gardée par un droit ? */
function routeGardee(\Illuminate\Routing\Route $route): bool
{
    foreach ($route->gatherMiddleware() as $middleware) {
        // « permission » depuis la phase 1 ; « role: » n'existe plus dans
        // routes/web.php, mais on le reconnaît encore pour qu'une route
        // oubliée lors de la bascule soit signalée, non ignorée.
        if (is_string($middleware) && ($middleware === 'permission' || str_starts_with($middleware, 'role:'))) {
            return true;
        }
    }

    return false;
}

test('chaque route gardée porte un droit que le catalogue déclare', function () {
    $ecarts = [];
    $gardees = 0;

    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();

        if ($nom === null || !routeGardee($route)) {
            continue;   // route publique ou seulement authentifiée : hors catalogue
        }

        $gardees++;
        $droit = PermissionCatalog::permissionForRoute($nom);

        if (PermissionCatalog::roles($droit) === []) {
            $ecarts[] = "{$nom} : droit « {$droit} » absent du catalogue";
        }
    }

    expect($ecarts)->toBe([], "Routes sans droit déclaré :\n  " . implode("\n  ", $ecarts));

    // Sans ce garde-fou, retirer toutes les gardes ferait passer le test :
    // il ne resterait rien à comparer.
    expect($gardees)->toBeGreaterThan(200);
});

test('une route sans nom ne peut pas être gardée', function () {
    // EnsurePermission déduit le droit du nom de la route. Une route gardée
    // sans nom serait refusée à tout le monde, silencieusement.
    $anonymes = [];

    foreach (Route::getRoutes() as $route) {
        if ($route->getName() === null && routeGardee($route)) {
            $anonymes[] = $route->uri();
        }
    }

    expect($anonymes)->toBe([]);
});

test('le catalogue ne déclare aucun droit sans route correspondante', function () {
    $droitsDesRoutes = [];

    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();
        if ($nom !== null && routeGardee($route)) {
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

test("les droits déclarés en écriture sont ceux servis par une route qui écrit", function () {
    $ecrituresReelles = [];

    foreach (Route::getRoutes() as $route) {
        $nom = $route->getName();

        if ($nom === null || !routeGardee($route)) {
            continue;
        }

        if (array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
            $ecrituresReelles[] = PermissionCatalog::permissionForRoute($nom);
        }
    }

    $ecrituresReelles = array_values(array_unique($ecrituresReelles));
    sort($ecrituresReelles);

    $declarees = PermissionCatalog::ecritures();
    sort($declarees);

    // Lire cette qualité dans le nom ne marche pas : « claim » écrit sans le
    // dire, « revenue_journal » lit sans porter de verbe. La déclaration doit
    // donc suivre la table de routage, comme le reste du catalogue.
    expect($declarees)->toBe($ecrituresReelles);
});

test('un droit servi en GET et en POST compte comme une écriture', function () {
    // C'est le pouvoir le plus large qu'il confère qui décide.
    expect(PermissionCatalog::estLecture('economat.items.creer'))->toBeFalse()
        ->and(PermissionCatalog::estLecture('accounting.revenue_journal'))->toBeTrue()
        ->and(PermissionCatalog::estLecture('restaurant.orders.claim'))->toBeFalse();
});
