<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège l'API de reporting business : n'autorise que les requêtes portant
 * le bon jeton de service (Authorization: Bearer <REPORTING_SECRET>), envoyé
 * par la console business de pms. Ces endpoints exposent des données
 * financières sensibles — jamais publics.
 */
class ValidateReportingToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('reporting.secret');

        if ($secret === '') {
            return response()->json(['message' => 'Reporting API désactivée (secret non configuré).'], 503);
        }

        $provided = (string) $request->bearerToken();

        if ($provided === '' || !hash_equals($secret, $provided)) {
            return response()->json(['message' => 'Non autorisé.'], 401);
        }

        return $next($request);
    }
}
