<?php

namespace App\Http\Middleware;

use App\Services\PermissionResolver;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde une route par le droit module.action qu'elle porte, et non par une
 * liste de rôles écrite sur place.
 *
 * Jusqu'ici chaque route nommait ses rôles : « role:econome,manager ».
 * Changer qui accède à quoi exigeait donc de modifier le code, et personne ne
 * pouvait voir l'ensemble. Le droit, lui, se déduit du nom de la route ; les
 * rôles qui le détiennent viennent du catalogue, que les surcharges en base
 * corrigent établissement par établissement.
 *
 * La décision revient à PermissionResolver. Le refus reste rendu par
 * EnsureRoleAccess : même entrée au journal d'audit, même message, même
 * format de réponse qu'avant la bascule.
 */
class EnsurePermission
{
    public function __construct(
        private readonly EnsureRoleAccess $roleAccess,
        private readonly PermissionResolver $resolver,
    ) {
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

        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        if ($this->resolver->allows(Auth::user(), $permission)) {
            return $next($request);
        }

        return $this->roleAccess->refuser($request, $roles);
    }
}
