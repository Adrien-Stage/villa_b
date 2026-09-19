<?php

namespace App\Http\Middleware;

use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde une route par le droit module.action qu'elle porte, et non par une
 * liste de rôles écrite sur place.
 *
 * Jusqu'ici chaque route nommait ses rôles : « role:econome,manager,admin ».
 * Changer qui accède à quoi exigeait donc de modifier le code, et personne ne
 * pouvait voir l'ensemble. Le droit, lui, se déduit du nom de la route, et les
 * rôles qui le détiennent vivent au catalogue — un seul endroit, bientôt
 * éditable depuis l'ERP.
 *
 * Ce middleware ne décide rien lui-même : il résout le droit, puis délègue à
 * EnsureRoleAccess, qui garde la responsabilité du refus — journal d'audit,
 * message selon le rôle attendu, réponse JSON ou redirection. Le comportement
 * reste donc identique à la ligne près, ce que prouve
 * PermissionCatalogConformityTest.
 */
class EnsurePermission
{
    public function __construct(private readonly EnsureRoleAccess $roleAccess)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        // Une route sans nom n'a pas de droit déductible. Refuser plutôt que
        // laisser passer : une route gardée qui perdrait son nom deviendrait
        // sinon ouverte, sans que rien ne le signale.
        if ($routeName === null) {
            abort(403, "Route sans nom : droit indéterminable.");
        }

        $permission = PermissionCatalog::permissionForRoute($routeName);
        $roles      = PermissionCatalog::roles($permission);

        if ($roles === []) {
            abort(403, "Droit « {$permission} » absent du catalogue.");
        }

        return $this->roleAccess->handle($request, $next, ...$roles);
    }
}
