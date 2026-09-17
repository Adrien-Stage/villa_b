<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique le niveau d'accès par module : un utilisateur en lecture seule sur
 * un module peut consulter ses pages (GET) mais pas y agir (POST/PUT/PATCH/
 * DELETE). Le contrôle de rôle en amont a déjà vérifié qu'il a accès au module ;
 * ce middleware distingue ensuite lecture et écriture.
 *
 * Usage : ->middleware('module.access:restaurant')
 */
class EnsureModuleWriteAccess
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // 1. Contrôle d'accès global au module (aucun accès si 'none' ou hors département sans rôle)
        if (!$user->hasModuleAccess($module)) {
            $message = "Accès refusé : vous n'avez pas l'autorisation d'accéder au module '{$module}'.";

            if ($request->expectsJson()
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->header('X-Expect-Popup') === 'true') {
                return response()->json(['access_denied' => true, 'message' => $message], 403);
            }

            return back()->with([
                'access_denied_popup'   => true,
                'access_denied_message' => $message,
            ])->withErrors(['module_access' => $message]);
        }

        // 2. La lecture seule laisse consulter (GET / HEAD)
        if (!in_array($request->method(), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        // 3. Contrôle des droits d'écriture
        if (!$user->canWrite($module)) {
            $message = 'Vous avez un accès en lecture seule sur ce module : action non autorisée.';

            if ($request->expectsJson()
                || $request->header('X-Requested-With') === 'XMLHttpRequest'
                || $request->header('X-Expect-Popup') === 'true') {
                return response()->json(['access_denied' => true, 'message' => $message], 403);
            }

            return back()->with([
                'access_denied_popup'   => true,
                'access_denied_message' => $message,
            ])->withErrors(['module_access' => $message]);
        }

        return $next($request);
    }
}
