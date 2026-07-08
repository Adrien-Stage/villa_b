<?php

namespace App\Http\Middleware;

use App\Support\TenantModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque l'accès direct par URL aux routes d'un module désactivé pour cet
 * établissement (pas seulement masquer le lien dans la sidebar).
 * Usage : ->middleware('module:restaurant')
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!TenantModules::has($module)) {
            abort(403, "Ce module n'est pas activé pour cet établissement.");
        }

        return $next($request);
    }
}
