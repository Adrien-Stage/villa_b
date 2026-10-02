<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PermissionGrant;
use App\Services\PermissionMatrix;
use App\Services\PermissionResolver;
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
 * Les droits se lisent en couches (voir PermissionMatrix) : la console ne
 * remplace jamais que la sienne.
 *
 * Gardée par le jeton d'orchestration : aucune session, aucun rôle
 * d'établissement.
 */
class PermissionMatrixController extends Controller
{
    /**
     * Version du contrat. Une console qui ne la trouve pas garde l'ancien
     * écran ; les champs ajoutés ici sont ignorés par une console plus ancienne.
     */
    public const VERSION = 2;

    public function __construct(private readonly PermissionMatrix $matrice) {}

    /** Gabarit, couches, personnel, revue des comptes et règles de cumul. */
    public function show(): JsonResponse
    {
        return response()->json(['version' => self::VERSION] + $this->matrice->donnees() + [
            'empreinte' => $this->matrice->empreinte(PermissionGrant::ORIGINE_ERP),
        ]);
    }

    /** Ce que changerait un lot de la console, sans rien enregistrer. */
    public function apercu(Request $request): JsonResponse
    {
        $lot = $this->lotValide($request);

        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        return response()->json($this->matrice->apercu($lot, PermissionGrant::ORIGINE_ERP));
    }

    /**
     * Remplace les écarts que la console porte sur les rôles.
     *
     * Remplacement et non fusion : l'ERP envoie l'état complet de ce qu'il
     * pilote. Sans cela, retirer un refus depuis l'écran ne l'effacerait
     * jamais ici, et la matrice affichée cesserait de décrire la réalité.
     *
     * Seule la couche de la console est remplacée. Les écarts posés dans
     * l'établissement — sur ses rôles comme sur des personnes — ne sont pas
     * touchés : la console n'a pas à écraser les décisions de l'hôtel.
     */
    public function update(Request $request): JsonResponse
    {
        $lot = $this->lotValide($request);

        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        // L'écran a été ouvert sur une autre version de la couche : un second
        // opérateur l'a modifiée entre-temps. Remplacer effacerait son travail
        // sans qu'il le sache.
        $empreinte = $request->input('empreinte');
        $actuelle = $this->matrice->empreinte(PermissionGrant::ORIGINE_ERP);
        if (is_string($empreinte) && $empreinte !== '' && ! hash_equals($actuelle, $empreinte)) {
            return response()->json([
                'message' => "La matrice a changé depuis l'ouverture de l'écran : rechargez-la avant d'enregistrer.",
                'empreinte' => $actuelle,
            ], 409);
        }

        $cumuls = $this->matrice->cumulsDuLot($lot);

        if ($cumuls !== []) {
            // Un cumul n'est pas interdit : il se décide, et se motive. Le
            // petit établissement où trois personnes tiennent quatre
            // fonctions existe — la dérogation aussi.
            if (! $request->boolean('derogation')) {
                return response()->json([
                    'message' => 'Ce lot ouvre des cumuls de fonctions incompatibles : une dérogation motivée est nécessaire.',
                    'cumuls' => $cumuls,
                ], 422);
            }

            $sansMotif = array_filter($cumuls, static fn (array $c): bool => trim((string) ($c['reason'] ?? '')) === '');
            if ($sansMotif !== []) {
                return response()->json([
                    'message' => 'Chaque dérogation à la séparation des tâches doit être motivée.',
                    'cumuls' => array_values($sansMotif),
                ], 422);
            }
        }

        DB::transaction(fn () => $this->matrice->remplacerCouche($lot, PermissionGrant::ORIGINE_ERP));

        app(PermissionResolver::class)->forget();

        AuditLog::record(
            null,
            $cumuls === [] ? 'permission_matrix_console' : 'duty_segregation_override',
            'Matrice des droits réglée depuis la console : '.count($lot).' écart(s)'
                .($cumuls === [] ? '' : ' — dérogation : '.implode(' ; ', array_map(
                    static fn (array $c): string => "{$c['role']} reçoit {$c['permission']} ({$c['motif']})",
                    $cumuls
                ))),
            'security',
            ['ecarts' => count($lot), 'cumuls' => $cumuls, 'auteur' => $request->input('auteur')]
        );

        return response()->json([
            'appliques' => count($lot),
            'cumuls' => $cumuls,
            'empreinte' => $this->matrice->empreinte(PermissionGrant::ORIGINE_ERP),
        ]);
    }

    /**
     * Valide un lot d'écarts de la console.
     *
     * @return list<array{role: string, permission: string, effect: string, scope?: ?string, reason?: ?string}>|JsonResponse
     */
    private function lotValide(Request $request): array|JsonResponse
    {
        $valide = $request->validate([
            'ecarts' => ['present', 'array'],
            'ecarts.*.role' => ['required', 'string', 'max:64'],
            'ecarts.*.permission' => ['required', 'string', 'max:128'],
            'ecarts.*.effect' => ['required', 'in:allow,deny'],
            // Étendue des données. Absente : tout l'établissement, comme avant.
            'ecarts.*.scope' => ['nullable', 'in:propre,departement,etablissement'],
            'ecarts.*.reason' => ['nullable', 'string', 'max:255'],
            'derogation' => ['nullable', 'boolean'],
            'empreinte' => ['nullable', 'string', 'max:64'],
            'auteur' => ['nullable', 'string', 'max:255'],
        ]);

        $ecarts = array_values($valide['ecarts']);

        if ($refus = $this->matrice->refusDuLot($ecarts)) {
            return response()->json($refus, 422);
        }

        return $ecarts;
    }
}
