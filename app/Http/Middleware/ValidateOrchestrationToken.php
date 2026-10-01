<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde le canal de la console d'orchestration : matrice des droits, comptes
 * administrateurs, départements.
 *
 * Son jeton n'est remis qu'à la console. Le jeton de reporting, lui, est
 * aussi remis au module GRC, qui lit les données financières : s'il ouvrait
 * ce canal, le GRC pourrait se créer un compte administrateur.
 *
 * Paramètre « repli-reporting » : tant que l'établissement n'a pas reçu de
 * secret d'orchestration — compose antérieur à sa création —, la matrice
 * accepte encore le jeton de reporting, comme avant. Les comptes et les
 * départements, eux, n'existaient pas sur ce canal : ils n'ont pas de repli.
 */
class ValidateOrchestrationToken
{
    public function handle(Request $request, Closure $next, ?string $repli = null): Response
    {
        $secret = (string) config('orchestration.secret');

        if ($secret === '' && $repli === 'repli-reporting') {
            $secret = (string) config('reporting.secret');
        }

        if ($secret === '') {
            return response()->json(['message' => "Canal d'orchestration fermé (secret non configuré)."], 503);
        }

        $fourni = (string) $request->bearerToken();

        if ($fourni === '' || ! hash_equals($secret, $fourni)) {
            return response()->json(['message' => 'Non autorisé.'], 401);
        }

        return $next($request);
    }
}
