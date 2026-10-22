<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'identité de l'établissement, tenue par la console d'orchestration :
 * nom, coordonnées, devise, pays, couleurs.
 *
 * L'application ne les modifie pas (Paramètres › Général les montre en
 * lecture seule) ; le seeder de production ne les posait qu'au premier
 * démarrage. La console les pousse ici à chaque enregistrement de la fiche
 * de l'établissement. Le logo reste celui que la direction importe dans
 * l'application.
 */
class EstablishmentController extends Controller
{
    /** Couleurs du thème que l'application lit (layouts, classeurs, courriels). */
    private const COULEURS = ['primary', 'secondary', 'accent', 'dark', 'surface_dark', 'text_on_light', 'text_on_dark'];

    public function show(): JsonResponse
    {
        $tenant = Tenant::current();
        abort_if($tenant === null, 404, "L'établissement n'est pas encore initialisé.");

        return response()->json($this->identite($tenant));
    }

    public function update(Request $request): JsonResponse
    {
        $tenant = Tenant::current();
        abort_if($tenant === null, 404, "L'établissement n'est pas encore initialisé.");

        $valide = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'address'  => ['nullable', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'email'    => ['nullable', 'email', 'max:255'],
            'country'  => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'theme'    => ['nullable', 'array'],
            'theme.*'  => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'auteur'   => ['nullable', 'string', 'max:255'],
        ], [
            'theme.*.regex' => 'Chaque couleur doit être une valeur hexadécimale de la forme #RRGGBB.',
        ]);

        $avant = $this->identite($tenant);

        $tenant->fill([
            'name'    => trim($valide['name']),
            'address' => $valide['address'] ?? null,
            'phone'   => $valide['phone'] ?? null,
            'email'   => $valide['email'] ?? null,
        ]);

        if (!empty($valide['currency'])) {
            $tenant->currency = strtoupper($valide['currency']);
        }

        // Fusion : les autres réglages (horaires, taxes, courriels…) restent.
        $settings = $tenant->settings ?? [];
        if ($request->has('country')) {
            $settings['country'] = $valide['country'] ?? null;
        }
        $couleurs = array_filter(
            array_intersect_key($valide['theme'] ?? [], array_flip(self::COULEURS)),
            fn ($v) => $v !== null && $v !== ''
        );
        if ($couleurs !== []) {
            $settings['theme'] = array_merge($settings['theme'] ?? [], $couleurs);
        }
        $tenant->settings = $settings;
        $tenant->save();

        $apres = $this->identite($tenant);
        $changes = array_keys(array_filter($apres, fn ($valeur, $cle) => $valeur !== ($avant[$cle] ?? null), ARRAY_FILTER_USE_BOTH));

        if ($changes !== []) {
            AuditLog::record(null, 'tenant_console',
                "Informations de l'établissement mises à jour depuis la console", 'settings',
                ['champs' => $changes, 'auteur' => $valide['auteur'] ?? null]);
        }

        return response()->json(['ok' => true, 'modifies' => $changes] + $apres);
    }

    private function identite(Tenant $tenant): array
    {
        return [
            'name'     => $tenant->name,
            'address'  => $tenant->address,
            'phone'    => $tenant->phone,
            'email'    => $tenant->email,
            'currency' => $tenant->currency,
            'country'  => $tenant->settings['country'] ?? null,
            'theme'    => $tenant->settings['theme'] ?? null,
        ];
    }
}
