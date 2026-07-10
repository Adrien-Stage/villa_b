<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrée en mode assistance depuis le PMS (Support > Mode assistance).
 *
 * Le PMS signe un jeton HMAC (secret partagé ASSISTANCE_SECRET) portant le
 * slug de l'établissement, une référence de session, le nom de l'admin et
 * une expiration. Ce endpoint vérifie la signature et l'expiration, ouvre
 * une session en se connectant comme administrateur de l'établissement,
 * marque la session comme "assistance" (bannière + audit) puis redirige
 * vers le tableau de bord.
 *
 * Aucune authentification préalable requise (l'admin TECH n'a pas de compte
 * dans cette base) : la confiance vient entièrement de la signature du jeton.
 */
class AssistanceController extends Controller
{
    public function enter(Request $request)
    {
        $secret = (string) config('assistance.secret');
        if ($secret === '') {
            abort(403, "Le mode assistance n'est pas activé pour cet établissement.");
        }

        $raw = (string) $request->query('token', '');
        if (!str_contains($raw, '.')) {
            abort(403, 'Jeton d\'assistance invalide.');
        }

        [$encoded, $signature] = explode('.', $raw, 2);

        // Vérification de la signature (comparaison à temps constant)
        $expected = hash_hmac('sha256', $encoded, $secret);
        if (!hash_equals($expected, $signature)) {
            abort(403, 'Signature du jeton d\'assistance invalide.');
        }

        $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true);
        if (!is_array($payload)) {
            abort(403, 'Jeton d\'assistance illisible.');
        }

        // Expiration
        if (($payload['exp'] ?? 0) < now()->timestamp) {
            abort(403, 'La session d\'assistance a expiré.');
        }

        // Cohérence de l'établissement ciblé
        $expectedSlug = (string) env('TENANT_SLUG', '');
        if ($expectedSlug !== '' && ($payload['slug'] ?? null) !== $expectedSlug) {
            abort(403, 'Ce jeton ne concerne pas cet établissement.');
        }

        // Cible : un administrateur (ou à défaut un manager) actif
        $target = User::where('role', User::ROLE_ADMIN)->where('is_active', true)->first()
            ?? User::where('role', User::ROLE_MANAGER)->where('is_active', true)->first();

        if (!$target) {
            abort(409, 'Aucun compte administrateur actif disponible pour l\'assistance.');
        }

        Auth::login($target);

        // Marque la session courante comme session d'assistance (bannière UI)
        $adminName = (string) ($payload['admin'] ?? 'Support');
        session(['assistance_mode' => [
            'admin' => $adminName,
            'ref'   => (string) ($payload['session'] ?? ''),
            'since' => now()->toIso8601String(),
        ]]);

        AuditLog::record(
            $target->id,
            'assistance_enter',
            "Ouverture d'une session d'assistance par le support ({$adminName})",
            'support',
            ['ref' => $payload['session'] ?? null, 'impersonated' => $target->name]
        );

        return redirect()->route('dashboard')
            ->with('success', 'Session d\'assistance ouverte — vos actions sont enregistrées.');
    }
}
