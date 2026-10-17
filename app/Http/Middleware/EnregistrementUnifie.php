<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paramètres : la barre « Enregistrer » envoie chaque formulaire modifié de
 * l'onglet à sa route habituelle (resources/js/reglages.js). Ces routes
 * répondent par une redirection et un message flashé ; pour la barre, on
 * traduit cette réponse en JSON — réussite, erreurs de validation par champ,
 * refus — sans rien changer aux contrôleurs.
 *
 * N'agit que sur les requêtes qui portent l'en-tête X-Enregistrement-Unifie.
 */
class EnregistrementUnifie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->has('X-Enregistrement-Unifie')) {
            return $next($request);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse || ! $response->isRedirection()) {
            return $response;
        }

        $session = $request->session();
        $nettoyer = fn () => $session->forget(['errors', 'error', 'success', 'warning', 'info', '_old_input', 'access_denied_popup']);

        $erreurs = $session->get('errors');
        if ($erreurs instanceof ViewErrorBag && $erreurs->any()) {
            $parChamp = $erreurs->getBag('default')->toArray();
            $nettoyer();

            return response()->json(['message' => collect($parChamp)->flatten()->first(), 'errors' => $parChamp], 422);
        }

        if ($session->has('access_denied_popup')) {
            $nettoyer();

            return response()->json(['message' => "Vous n'avez pas le droit d'enregistrer ce réglage."], 403);
        }

        if ($message = $session->get('error')) {
            $nettoyer();

            return response()->json(['message' => $message], 422);
        }

        $succes = $session->get('success');
        $nettoyer();

        return response()->json(['ok' => true, 'message' => $succes]);
    }
}
