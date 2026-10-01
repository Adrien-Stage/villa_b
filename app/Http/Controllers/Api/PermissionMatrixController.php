<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\DutySegregation;
use App\Support\PermissionCatalog;
use App\Support\PermissionScope;
use App\Support\RoleCatalog;
use App\Support\RoleReview;
use App\Support\StaffDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matrice des droits, vue depuis la console d'orchestration.
 *
 * L'ERP édite la matrice d'un établissement ; l'application l'applique. Le
 * catalogue reste la source du gabarit — il vit dans le code et suit les
 * routes — tandis que cette API n'expose et ne reçoit que les écarts.
 *
 * Les droits se lisent en couches : le modèle (le catalogue), la couche de la
 * console, celle de l'établissement, puis les exceptions nominatives. La
 * console ne remplace jamais que la sienne.
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

    /** Rôles que la console ne règle pas : l'administrateur et le portail client. */
    private const NON_REGLABLES = [RoleCatalog::ADMIN, 'customer_guest'];

    /** Gabarit, couches, personnel, revue des comptes et règles de cumul. */
    public function show(): JsonResponse
    {
        $ecarts = PermissionGrant::query()
            ->enVigueur()
            ->orderBy('subject_type')->orderBy('subject_id')->orderBy('permission')
            ->get(['subject_type', 'subject_id', 'permission', 'effect', 'scope', 'origin', 'reason', 'expires_at']);

        $echues = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_USER)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderByDesc('expires_at')
            ->get(['subject_id', 'permission', 'effect', 'origin', 'reason', 'expires_at']);

        $comptes = StaffDirectory::comptes();
        [$regles, $cumuls] = $this->cumulsPossibles();

        return response()->json([
            'version' => self::VERSION,
            'catalogue' => PermissionCatalog::all(),
            'modules' => PermissionCatalog::modules(),
            'ecritures' => array_values(array_intersect(PermissionCatalog::ecritures(), array_keys(PermissionCatalog::all()))),
            'roles' => array_map(
                static fn (array $r): array => [
                    'slug' => $r['slug'],
                    'name' => $r['name'],
                    'description' => $r['description'] ?? null,
                    'module' => $r['module'],
                    'is_assignable' => $r['is_assignable'],
                    // Hiérarchie : niveau (1 administration … 4 membres, nul
                    // hors hiérarchie), rôles inclus, statut.
                    'level' => $r['level'] ?? null,
                    'includes' => $r['includes'] ?? [],
                    'statut' => $r['statut'] ?? RoleCatalog::ACTIF,
                    'reglable' => ! in_array($r['slug'], self::NON_REGLABLES, true)
                        && ($r['statut'] ?? RoleCatalog::ACTIF) !== RoleCatalog::RETIRE,
                    // Comptes actifs qui détiennent le rôle : ce que touche
                    // une case de sa colonne.
                    'titulaires' => count(array_filter(
                        $comptes,
                        static fn (array $c): bool => $c['actif'] && in_array($r['slug'], $c['roles'], true)
                    )),
                ],
                RoleCatalog::all()
            ),
            'ecarts' => $ecarts,
            'exceptions_echues' => $echues,
            'restrictions' => $this->restrictionsDeService(),
            'comptes' => $comptes,
            'constats' => RoleReview::constats(),
            'incompatibilites' => DutySegregation::incompatibilities(),
            'regles_de_cumul' => $regles,
            'cumuls' => $cumuls,
            'portees' => array_map(
                static fn (string $p): array => ['valeur' => $p, 'libelle' => PermissionScope::libelle($p)],
                PermissionScope::ORDRE
            ),
            'droits_bornes' => PermissionScope::DROITS_BORNES,
            'empreinte' => $this->empreinteDeLaConsole(),
        ]);
    }

    /**
     * Ce que changerait un lot, sans rien enregistrer : qui gagne ou perd
     * quel droit, et quels cumuls il ouvre.
     *
     * Le lot est appliqué dans une transaction annulée, et chaque compte est
     * passé au moteur de droits avant et après : l'aperçu ne réimplémente pas
     * la décision, il la constate. C'est ce qui le garde exact quand une
     * personne cumule plusieurs rôles, une affectation en lecture seule ou une
     * exception nominative.
     */
    public function apercu(Request $request): JsonResponse
    {
        $lot = $this->lotValide($request);

        if ($lot instanceof JsonResponse) {
            return $lot;
        }

        $comptes = User::query()
            ->where('is_active', true)
            ->with(['roles', 'modulePermissions'])
            ->orderBy('name')
            ->get()
            ->reject(static fn (User $u): bool => $u->rolesDetenus() === ['customer_guest'])
            ->values();

        $resolveur = app(PermissionResolver::class);
        $resolveur->forget();
        $avant = $this->droitsAccordes($comptes, $resolveur);

        DB::beginTransaction();

        try {
            $this->remplacerLaCoucheDeLaConsole($lot);
            $resolveur->forget();
            $apres = $this->droitsAccordes($comptes, $resolveur);
        } finally {
            DB::rollBack();
            $resolveur->forget();
        }

        $personnes = [];

        foreach ($comptes as $compte) {
            $gagnes = array_values(array_diff($apres[$compte->id]['droits'], $avant[$compte->id]['droits']));
            $perdus = array_values(array_diff($avant[$compte->id]['droits'], $apres[$compte->id]['droits']));

            $portees = [];
            foreach (PermissionScope::DROITS_BORNES as $droit) {
                if ($avant[$compte->id]['portees'][$droit] !== $apres[$compte->id]['portees'][$droit]) {
                    $portees[] = [
                        'permission' => $droit,
                        'avant' => $avant[$compte->id]['portees'][$droit],
                        'apres' => $apres[$compte->id]['portees'][$droit],
                    ];
                }
            }

            if ($gagnes !== [] || $perdus !== [] || $portees !== []) {
                $personnes[] = [
                    'id' => $compte->id,
                    'name' => $compte->name,
                    'roles' => $compte->rolesDetenus(),
                    'gagnes' => $gagnes,
                    'perdus' => $perdus,
                    'portees' => $portees,
                ];
            }
        }

        return response()->json([
            'ecarts' => count($lot),
            'personnes' => $personnes,
            'cumuls' => $this->cumulsDuLot($lot),
        ]);
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
        if (is_string($empreinte) && $empreinte !== '' && ! hash_equals($this->empreinteDeLaConsole(), $empreinte)) {
            return response()->json([
                'message' => "La matrice a changé depuis l'ouverture de l'écran : rechargez-la avant d'enregistrer.",
                'empreinte' => $this->empreinteDeLaConsole(),
            ], 409);
        }

        $cumuls = $this->cumulsDuLot($lot);

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

        DB::transaction(fn () => $this->remplacerLaCoucheDeLaConsole($lot));

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
            'empreinte' => $this->empreinteDeLaConsole(),
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

        $inconnus = array_values(array_unique(array_column(array_filter(
            $ecarts,
            static fn (array $e): bool => PermissionCatalog::roles($e['permission']) === []
        ), 'permission')));

        if ($inconnus !== []) {
            // Un droit hors catalogue n'est appliqué par aucune route : une
            // case cochée sans effet est pire qu'une case absente.
            return response()->json(['message' => 'Droits inconnus du catalogue.', 'inconnus' => $inconnus], 422);
        }

        // L'administrateur ne se règle pas : il consulte tout et n'écrit que
        // la configuration et les comptes, par construction. Un rôle retiré
        // ne donne plus aucun droit — une autorisation posée sur lui en
        // redonnerait à ceux qui le portent encore.
        $reglables = array_column(array_filter(
            RoleCatalog::all(),
            static fn (array $r): bool => ! in_array($r['slug'], self::NON_REGLABLES, true)
                && ($r['statut'] ?? RoleCatalog::ACTIF) !== RoleCatalog::RETIRE
        ), 'slug');

        $horsMatrice = array_values(array_unique(array_diff(array_column($ecarts, 'role'), $reglables)));

        if ($horsMatrice !== []) {
            return response()->json([
                'message' => 'Rôles que la console ne règle pas.',
                'roles' => $horsMatrice,
            ], 422);
        }

        // Une case ne porte qu'un écart : deux décisions contraires sur la
        // même case, laquelle vaudrait ?
        $cases = array_map(static fn (array $e): string => "{$e['role']}|{$e['permission']}", $ecarts);
        $doublons = array_values(array_unique(array_diff_assoc($cases, array_unique($cases))));

        if ($doublons !== []) {
            return response()->json(['message' => 'Écarts en double.', 'doublons' => $doublons], 422);
        }

        return $ecarts;
    }

    /** @param  list<array<string, mixed>>  $lot */
    private function remplacerLaCoucheDeLaConsole(array $lot): void
    {
        PermissionGrant::where('subject_type', PermissionGrant::SUJET_ROLE)
            ->where('origin', PermissionGrant::ORIGINE_ERP)
            ->delete();

        foreach ($lot as $ecart) {
            PermissionGrant::create([
                'subject_type' => PermissionGrant::SUJET_ROLE,
                'subject_id' => $ecart['role'],
                'permission' => $ecart['permission'],
                'effect' => $ecart['effect'],
                'scope' => $ecart['scope'] ?? null,
                'origin' => PermissionGrant::ORIGINE_ERP,
                'reason' => $ecart['reason'] ?? null,
            ]);
        }
    }

    /**
     * Cumuls qu'ouvrent les autorisations du lot.
     *
     * @param  list<array<string, mixed>>  $lot
     * @return list<array{role: string, permission: string, roles: array, motif: string, reason: ?string}>
     */
    private function cumulsDuLot(array $lot): array
    {
        $cumuls = [];

        foreach ($lot as $ecart) {
            if ($ecart['effect'] !== PermissionGrant::EFFET_ALLOW) {
                continue;
            }

            foreach (DutySegregation::conflitsDUneAutorisation($ecart['role'], $ecart['permission']) as $conflit) {
                $cumuls[] = [
                    'role' => $ecart['role'],
                    'permission' => $ecart['permission'],
                    'roles' => $conflit['roles'],
                    'motif' => $conflit['motif'],
                    'reason' => $ecart['reason'] ?? null,
                ];
            }
        }

        return $cumuls;
    }

    /**
     * Toutes les cases qui ouvriraient un cumul si on les cochait, pour que la
     * console alerte au clic plutôt qu'à l'enregistrement.
     *
     * Les règles sont listées une fois ; chaque case renvoie à leurs indices.
     *
     * @return array{0: list<array{roles: array, motif: string}>, 1: array<string, list<int>>}
     */
    private function cumulsPossibles(): array
    {
        $roles = array_column(array_filter(
            RoleCatalog::all(),
            static fn (array $r): bool => ! in_array($r['slug'], self::NON_REGLABLES, true)
                && ($r['statut'] ?? RoleCatalog::ACTIF) !== RoleCatalog::RETIRE
        ), 'slug');

        $regles = [];
        $indices = [];
        $cumuls = [];

        foreach (array_keys(PermissionCatalog::all()) as $droit) {
            if (PermissionCatalog::estLecture($droit)) {
                continue;
            }

            foreach ($roles as $role) {
                foreach (DutySegregation::conflitsDUneAutorisation($role, $droit) as $conflit) {
                    $cle = implode('|', $conflit['roles']).'|'.$conflit['motif'];

                    if (! isset($indices[$cle])) {
                        $indices[$cle] = count($regles);
                        $regles[] = $conflit;
                    }

                    $cumuls["{$role}|{$droit}"][] = $indices[$cle];
                }
            }
        }

        return [$regles, $cumuls];
    }

    /**
     * Restrictions de service posées sur des personnes (exclusion ou lecture
     * seule) : des refus nominatifs, à montrer comme tels.
     *
     * @return list<array{user_id: int, service: string, niveau: string}>
     */
    private function restrictionsDeService(): array
    {
        if (! Schema::hasTable('user_module_permissions')) {
            return [];
        }

        return DB::table('user_module_permissions')
            ->whereIn('access_level', ['none', 'read'])
            ->orderBy('user_id')->orderBy('module_key')
            ->get(['user_id', 'module_key', 'access_level'])
            ->map(static fn ($r): array => [
                'user_id' => (int) $r->user_id,
                'service' => $r->module_key,
                'niveau' => $r->access_level,
            ])
            ->all();
    }

    /**
     * Empreinte de la couche de la console : l'écran la garde à l'ouverture,
     * l'enregistrement la compare pour ne pas écraser le travail d'un autre.
     */
    private function empreinteDeLaConsole(): string
    {
        $lignes = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_ROLE)
            ->where('origin', PermissionGrant::ORIGINE_ERP)
            ->orderBy('subject_id')->orderBy('permission')->orderBy('id')
            ->get(['subject_id', 'permission', 'effect', 'scope', 'reason'])
            ->map(static fn (PermissionGrant $g): array => [
                $g->subject_id, $g->permission, $g->effect, $g->scope, $g->reason,
            ])
            ->all();

        return sha1(json_encode($lignes, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Droits que le moteur accorde à chaque compte, et la portée des droits
     * bornés.
     *
     * @param  Collection<int, User>  $comptes
     * @return array<int, array{droits: list<string>, portees: array<string, string>}>
     */
    private function droitsAccordes(Collection $comptes, PermissionResolver $resolveur): array
    {
        $droits = array_keys(PermissionCatalog::all());
        $resultat = [];

        foreach ($comptes as $compte) {
            $accordes = [];
            foreach ($droits as $droit) {
                if ($resolveur->allows($compte, $droit)) {
                    $accordes[] = $droit;
                }
            }

            $portees = [];
            foreach (PermissionScope::DROITS_BORNES as $droit) {
                $portees[$droit] = $resolveur->scopeFor($compte, $droit);
            }

            $resultat[$compte->id] = ['droits' => $accordes, 'portees' => $portees];
        }

        return $resultat;
    }
}
