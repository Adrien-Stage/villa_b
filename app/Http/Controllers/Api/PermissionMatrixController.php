<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PermissionGrant;
use App\Support\DutySegregation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Matrice des droits, vue depuis la console d'orchestration.
 *
 * L'ERP édite la matrice d'un établissement ; l'application l'applique. Le
 * catalogue reste la source du gabarit — il vit dans le code et suit les
 * routes — tandis que cette API n'expose et ne reçoit que les écarts.
 *
 * Gardée par le jeton de service, comme les autres points d'entrée
 * d'orchestration : aucune session, aucun rôle d'établissement.
 */
class PermissionMatrixController extends Controller
{
    /** Gabarit du catalogue, écarts en vigueur, et règles de cumul. */
    public function show(): JsonResponse
    {
        $ecarts = PermissionGrant::query()
            ->orderBy('subject_type')->orderBy('subject_id')->orderBy('permission')
            ->get(['subject_type', 'subject_id', 'permission', 'effect', 'scope', 'reason']);

        return response()->json([
            'catalogue'          => PermissionCatalog::all(),
            'modules'            => PermissionCatalog::modules(),
            'roles'              => array_map(
                static fn (array $r): array => [
                    'slug'          => $r['slug'],
                    'name'          => $r['name'],
                    'module'        => $r['module'],
                    'is_assignable' => $r['is_assignable'],
                ],
                RoleCatalog::all()
            ),
            'ecarts'             => $ecarts,
            'incompatibilites'   => DutySegregation::incompatibilities(),
            'portees'            => array_map(
                static fn (string $p): array => ['valeur' => $p, 'libelle' => \App\Support\PermissionScope::libelle($p)],
                \App\Support\PermissionScope::ORDRE
            ),
        ]);
    }

    /**
     * Remplace les écarts portés par les rôles.
     *
     * Remplacement et non fusion : l'ERP envoie l'état complet de ce qu'il
     * pilote. Sans cela, retirer un refus depuis l'écran ne l'effacerait
     * jamais ici, et la matrice affichée cesserait de décrire la réalité.
     *
     * Les écarts nominatifs ne sont pas touchés : ce sont les dérogations
     * accordées sur place par le directeur, que la console n'a pas à écraser.
     */
    public function update(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'ecarts'              => ['present', 'array'],
            'ecarts.*.role'       => ['required', 'string', 'max:64'],
            'ecarts.*.permission' => ['required', 'string', 'max:128'],
            'ecarts.*.effect'     => ['required', 'in:allow,deny'],
            // Étendue des données. Absente : tout l'établissement, comme avant.
            'ecarts.*.scope'      => ['nullable', 'in:propre,departement,etablissement'],
            'ecarts.*.reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $inconnus = array_values(array_filter(
            $valide['ecarts'],
            static fn (array $e): bool => PermissionCatalog::roles($e['permission']) === []
        ));

        if ($inconnus !== []) {
            // Un droit hors catalogue n'est appliqué par aucune route : une
            // case cochée sans effet est pire qu'une case absente.
            return response()->json([
                'message'  => 'Droits inconnus du catalogue.',
                'inconnus' => array_values(array_unique(array_column($inconnus, 'permission'))),
            ], 422);
        }

        DB::transaction(function () use ($valide): void {
            PermissionGrant::where('subject_type', PermissionGrant::SUJET_ROLE)->delete();

            foreach ($valide['ecarts'] as $ecart) {
                PermissionGrant::create([
                    'subject_type' => PermissionGrant::SUJET_ROLE,
                    'subject_id'   => $ecart['role'],
                    'permission'   => $ecart['permission'],
                    'effect'       => $ecart['effect'],
                    'scope'        => $ecart['scope'] ?? null,
                    'reason'       => $ecart['reason'] ?? null,
                ]);
            }
        });

        app(\App\Services\PermissionResolver::class)->forget();

        return response()->json(['appliques' => count($valide['ecarts'])]);
    }
}
