<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles Liste des rôles autorisés (ex: 'admin', 'manager,reception')
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Vérifier si l'utilisateur est authentifié
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        $user = Auth::user();

        // Si aucun rôle n'est spécifié, autoriser (middleware neutre)
        if (empty($roles)) {
            return $next($request);
        }

        // Parser les rôles (supporte 'admin' ou 'admin,manager,reception')
        $authorizedRoles = [];
        foreach ($roles as $roleParam) {
            $authorizedRoles = array_merge($authorizedRoles, explode(',', $roleParam));
        }
        $authorizedRoles = array_map('trim', $authorizedRoles);

        // Vérifier si l'utilisateur a l'un des rôles autorisés
        if (!$user->hasAnyRole($authorizedRoles)) {
            // Log l'accès refusé pour audit dans la base de données
            \App\Models\AuditLog::record(
                $user->id,
                'access_denied',
                'Accès refusé. Rôles requis: ' . implode(', ', $authorizedRoles) . ' (URL: ' . $request->getPathInfo() . ')',
                'security',
                ['url' => $request->fullUrl(), 'required_roles' => $authorizedRoles]
            );

            // Messages d'erreur personnalisés par contexte
            $customMessages = [
                'admin' => 'Accès réservé à l\'administration.',
                'manager' => 'Seul un manager peut effectuer cette action.',
                'reception' => 'Réservé au personnel de réception.',
                'housekeeping_leader' => 'Réservé aux chefs d\'équipe housekeeping.',
                'housekeeping_staff' => 'Réservé au personnel housekeeping.',
                'housekeeping' => 'Réservé au personnel housekeeping.',
                'accountant' => 'Accès réservé à la comptabilité.',
                'cashier' => 'Accès réservé aux caissiers.',
            ];

            $message = 'Accès refusé. Rôles requis: ' . implode(', ', $authorizedRoles);

            // Essayer de trouver un message plus spécifique
            foreach ($authorizedRoles as $role) {
                if (isset($customMessages[$role])) {
                    $message = $customMessages[$role];
                    break;
                }
            }

            // Pour les requêtes AJAX ou qui attendent un popup
            if ($request->expectsJson() ||
                $request->header('X-Requested-With') === 'XMLHttpRequest' ||
                $request->header('X-Expect-Popup') === 'true') {

                return response()->json([
                    'access_denied' => true,
                    'message' => $message,
                    'required_roles' => $authorizedRoles
                ], 403);
            }

            // Log l'accès refusé pour audit
            \Log::warning('RBAC Access Denied', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_role' => $user->role,
                'required_roles' => $authorizedRoles,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            $fallbackUrl = url()->previous();
            $currentUrl = $request->fullUrl();

            if (empty($fallbackUrl) || $fallbackUrl === $currentUrl) {
                $fallbackUrl = route('dashboard');
            }

            return redirect($fallbackUrl)->with([
                'access_denied_popup' => true,
                'access_denied_message' => $message,
            ]);
        }

        return $next($request);
    }

    /**
     * Valider l'accès multi-tenant
     */
    private function validateTenantAccess(Request $request, $user): void
    {
        // Extraire tenant_id depuis l'URL ou les paramètres
        $tenantId = $this->extractTenantIdFromRequest($request);

        if ($tenantId && !$user->canViewTenant($tenantId)) {
            \Log::warning('Multi-tenant Access Violation', [
                'user_id' => $user->id,
                'user_tenant_id' => $user->tenant_id,
                'requested_tenant_id' => $tenantId,
                'url' => $request->fullUrl(),
            ]);

            abort(403, 'Accès refusé. Isolation multi-tenant violée.');
        }
    }

    /**
     * Extraire tenant_id depuis la requête (route parameters, query string, etc.)
     */
    private function extractTenantIdFromRequest(Request $request): ?int
    {
        // Vérifier les paramètres de route
        if ($request->route() && $request->route()->hasParameter('tenant')) {
            return $request->route()->parameter('tenant');
        }

        // Vérifier les paramètres de requête
        if ($request->has('tenant_id')) {
            return (int) $request->get('tenant_id');
        }

        // Pour les modèles liés à tenant, vérifier si l'ID dans l'URL correspond à un tenant
        // Cette logique peut être étendue selon les besoins spécifiques

        return null;
    }
}
