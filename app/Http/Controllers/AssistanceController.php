<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SupportSession;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrée en mode assistance depuis le PMS (Support > Mode assistance).
 *
 * Le PMS signe un jeton HMAC (secret partagé ASSISTANCE_SECRET) portant le
 * slug de l'établissement, une référence de session, le nom du technicien et
 * une expiration. Ce endpoint vérifie la signature et l'expiration, ouvre
 * une session sous le compte technique « Support Wetchah » de
 * l'établissement, marque la session comme "assistance" (bannière + audit)
 * puis redirige vers le tableau de bord.
 *
 * Le support n'entre plus sous le compte de l'administrateur : il a le sien,
 * qui consulte sans écrire, et chacune de ses sessions est enregistrée pour
 * que l'hôtel la voie. Ce compte n'a pas de mot de passe utilisable : seul
 * un jeton signé l'ouvre.
 *
 * Aucune authentification préalable requise (le technicien n'a pas de compte
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
        $expectedSlug = (string) config('orchestration.tenant_slug');
        if ($expectedSlug !== '' && ($payload['slug'] ?? null) !== $expectedSlug) {
            abort(403, 'Ce jeton ne concerne pas cet établissement.');
        }

        $support = $this->compteSupport();

        Auth::login($support);
        $request->session()->regenerate();

        $technicien = (string) ($payload['admin'] ?? 'Support');
        $trace = SupportSession::create([
            'user_id' => $support->id,
            'technicien' => $technicien,
            'reference' => (string) ($payload['session'] ?? ''),
            'debut' => now(),
            'ip_address' => $request->ip(),
        ]);

        // Marque la session courante comme session d'assistance (bannière UI)
        session(['assistance_mode' => [
            'admin' => $technicien,
            'ref' => (string) ($payload['session'] ?? ''),
            'since' => now()->toIso8601String(),
            'trace' => $trace->id,
        ]]);

        AuditLog::record(
            $support->id,
            'assistance_enter',
            "Ouverture d'une session d'assistance par le support ({$technicien})",
            'support',
            ['ref' => $payload['session'] ?? null, 'support_session_id' => $trace->id]
        );

        return redirect()->route('dashboard')
            ->with('success', 'Session d\'assistance ouverte — vos actions sont enregistrées.');
    }

    /**
     * Le compte technique du support, créé au premier passage. Son mot de
     * passe est tiré au hasard et jamais montré : la page de connexion le
     * refuse de toute façon.
     */
    private function compteSupport(): User
    {
        $support = User::query()->havingRole([RoleCatalog::SUPPORT])->first()
            ?? User::create([
                'name' => 'Support Wetchah',
                'email' => 'support@wetchah.invalid',
                'role' => RoleCatalog::SUPPORT,
                'is_active' => true,
                'password' => Hash::make(Str::random(64)),
            ]);

        $role = Role::firstOrCreate(
            ['slug' => RoleCatalog::SUPPORT],
            RoleCatalog::enregistrement(RoleCatalog::SUPPORT) ?? ['name' => 'Support Wetchah']
        );
        $support->roles()->syncWithoutDetaching([$role->id => ['level' => null]]);

        if (! $support->is_active) {
            $support->update(['is_active' => true]);
        }

        return $support->fresh();
    }
}
