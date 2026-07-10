<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Journalise toute action modifiante (POST/PUT/PATCH/DELETE) réalisée par un
 * utilisateur authentifié dans l'application, avec l'utilisateur concerné.
 * Alimente audit_logs, lue par le PMS (onglet Support > Logs applicatifs).
 *
 * Les GET (navigation, consultation) ne sont pas journalisés pour ne pas
 * noyer le journal ; les connexions/déconnexions sont déjà tracées par les
 * listeners d'événements d'auth (AppServiceProvider). On ne journalise
 * qu'après une réponse réussie (2xx/3xx) pour ne pas enregistrer les échecs
 * de validation ou les accès refusés.
 */
class LogUserActivity
{
    private const TRACKED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Actions déjà couvertes ailleurs ou sans intérêt d'audit (préfixes de
     * chemin), pour éviter les doublons et le bruit.
     */
    private const IGNORED_PREFIXES = [
        'login', 'logout', 'register', 'password', 'forgot-password',
        'reset-password', 'email/verification', 'livewire',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!Auth::check() || !in_array($request->method(), self::TRACKED_METHODS, true)) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 400) {
            return $response;
        }

        $path = ltrim($request->path(), '/');
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $response;
            }
        }

        try {
            AuditLog::record(
                Auth::id(),
                'activity',
                $this->describe($request),
                $this->moduleFromPath($path),
                ['method' => $request->method(), 'path' => $path]
            );
        } catch (\Throwable) {
            // Ne jamais casser la requête utilisateur pour un échec de log
        }

        return $response;
    }

    /**
     * Libellé lisible de l'action, à partir du nom de route quand il existe,
     * sinon de la méthode HTTP et du chemin.
     */
    private function describe(Request $request): string
    {
        $verbs = ['POST' => 'Création/action', 'PUT' => 'Modification', 'PATCH' => 'Modification', 'DELETE' => 'Suppression'];
        $verb  = $verbs[$request->method()] ?? 'Action';

        $routeName = optional($request->route())->getName();
        if ($routeName) {
            return $verb . ' — ' . str_replace(['.', '_'], [' › ', ' '], $routeName);
        }

        return $verb . ' — ' . $request->method() . ' /' . ltrim($request->path(), '/');
    }

    /**
     * Module déduit du premier segment du chemin (restaurant, housekeeping,
     * bookings, shop…), pour le regroupement côté PMS.
     */
    private function moduleFromPath(string $path): string
    {
        $segment = strtok($path, '/') ?: 'app';

        return preg_replace('/[^a-z0-9_-]/i', '', $segment) ?: 'app';
    }
}
