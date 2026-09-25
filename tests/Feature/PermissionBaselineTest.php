<?php

/**
 * Le catalogue doit accorder exactement ce que le code accordait avant la
 * phase 1 — ni plus, ni moins.
 *
 * Avant la bascule, chaque route nommait ses rôles dans un middleware
 * « role: ». Une fois ces middlewares remplacés par « permission », cette
 * référence disparaît du code : plus rien, dans la table de routage, ne dit
 * qui avait le droit d'entrer. tests/Fixtures/route_roles_avant_phase1.json
 * la fige donc, route par route.
 *
 * Un élargissement involontaire est passé pendant la bascule : 54 routes
 * portaient DEUX middlewares « role: » — un groupe large, une garde interne
 * étroite — et chacun devait passer. Les réunir au lieu de les intersecter
 * ouvrait aux commis ce que le chef seul pouvait faire. Ce test l'aurait
 * arrêté ; il existe pour qu'aucun suivant ne passe.
 *
 * Modifier délibérément un droit impose de mettre à jour ce fichier. C'est
 * voulu : une matrice de sécurité ne doit pas bouger sans qu'on le voie.
 */

use App\Support\PermissionCatalog;

if (!function_exists('routeGardee')) {
    function routeGardee(\Illuminate\Routing\Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'permission' || str_starts_with($middleware, 'role:'))) {
                return true;
            }
        }

        return false;
    }
}

test('chaque route accorde exactement les rôles de la référence', function () {
    $reference = json_decode(
        file_get_contents(base_path('tests/Fixtures/route_roles_avant_phase1.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect($reference)->toBeArray()->not->toBeEmpty();

    $ecarts = [];

    foreach ($reference as $route => $rolesAttendus) {
        $droit    = PermissionCatalog::permissionForRoute($route);
        $accordes = PermissionCatalog::roles($droit);
        sort($accordes);
        sort($rolesAttendus);

        if ($accordes === $rolesAttendus) {
            continue;
        }

        $enTrop     = array_diff($accordes, $rolesAttendus);
        $manquants  = array_diff($rolesAttendus, $accordes);
        $detail     = [];

        if ($enTrop !== []) {
            $detail[] = 'ÉLARGI à ' . implode(',', $enTrop);
        }
        if ($manquants !== []) {
            $detail[] = 'RESTREINT, perd ' . implode(',', $manquants);
        }

        $ecarts[] = "{$route} ({$droit}) : " . implode(' ; ', $detail);
    }

    expect($ecarts)->toBe([], "Droits modifiés sans mise à jour de la référence :\n  " . implode("\n  ", $ecarts));
});

test('la référence couvre toutes les routes gardées', function () {
    $reference = json_decode(
        file_get_contents(base_path('tests/Fixtures/route_roles_avant_phase1.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    $gardees = [];

    foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
        $nom = $route->getName();
        if ($nom !== null && routeGardee($route)) {
            $gardees[] = $nom;
        }
    }

    // Une route gardée absente de la référence est une route dont personne ne
    // sait ce qu'elle accordait : ajoutez-la sciemment.
    expect(array_values(array_diff($gardees, array_keys($reference))))->toBe([]);
});
