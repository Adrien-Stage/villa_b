<?php

namespace App\Services;

use App\Models\PermissionGrant;
use App\Models\User;
use App\Support\DutySegregation;
use App\Support\PermissionCatalog;
use App\Support\PermissionScope;
use App\Support\RoleCatalog;
use App\Support\RoleReview;
use App\Support\StaffDirectory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La matrice des droits, en couches.
 *
 * Le modèle vient du catalogue ; au-dessus, la couche de la console
 * d'orchestration (origine « erp ») et celle de l'hôtel (origine
 * « etablissement »), chacune réglée par son propriétaire et remplacée en
 * entier par lui seul ; enfin les exceptions nominatives. Un refus, quelle
 * que soit sa couche, l'emporte.
 *
 * Partagée par l'API de la console et par l'écran « Rôles & droits » de
 * l'administrateur : la règle n'existe qu'à un endroit.
 */
class PermissionMatrix
{
    /** Rôles qu'aucune couche ne règle : l'administrateur, le support, le portail client. */
    public const NON_REGLABLES = [RoleCatalog::ADMIN, RoleCatalog::SUPPORT, 'customer_guest'];

    /** @return list<string> rôles qu'une couche peut régler */
    public static function reglables(): array
    {
        return array_values(array_column(array_filter(
            RoleCatalog::all(),
            static fn (array $r): bool => ! in_array($r['slug'], self::NON_REGLABLES, true)
                && ($r['statut'] ?? RoleCatalog::ACTIF) !== RoleCatalog::RETIRE
        ), 'slug'));
    }

    /**
     * Tout ce qu'il faut pour afficher la matrice : gabarit, couches,
     * personnel, revue des comptes, règles de cumul.
     *
     * @return array<string, mixed>
     */
    public function donnees(): array
    {
        $ecarts = PermissionGrant::query()
            ->enVigueur()
            ->orderBy('subject_type')->orderBy('subject_id')->orderBy('permission')
            ->get(['id', 'subject_type', 'subject_id', 'permission', 'effect', 'scope', 'origin', 'reason', 'expires_at']);

        $echues = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_USER)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderByDesc('expires_at')
            ->get(['id', 'subject_id', 'permission', 'effect', 'origin', 'reason', 'expires_at']);

        $comptes = StaffDirectory::comptes();
        [$regles, $cumuls] = $this->cumulsPossibles();
        $reglables = self::reglables();

        return [
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
                    'level' => $r['level'] ?? null,
                    'includes' => $r['includes'] ?? [],
                    'statut' => $r['statut'] ?? RoleCatalog::ACTIF,
                    'reglable' => in_array($r['slug'], $reglables, true),
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
        ];
    }

    /**
     * Refus d'un lot, ou null s'il est recevable.
     *
     * @param  list<array<string, mixed>>  $ecarts
     * @return array<string, mixed>|null
     */
    public function refusDuLot(array $ecarts): ?array
    {
        $inconnus = array_values(array_unique(array_column(array_filter(
            $ecarts,
            static fn (array $e): bool => PermissionCatalog::roles($e['permission']) === []
        ), 'permission')));

        if ($inconnus !== []) {
            // Un droit hors catalogue n'est appliqué par aucune route : une
            // case cochée sans effet est pire qu'une case absente.
            return ['message' => 'Droits inconnus du catalogue.', 'inconnus' => $inconnus];
        }

        // L'administrateur ne se règle pas : il consulte tout et n'écrit que
        // la configuration et les comptes, par construction. Un rôle retiré
        // ne donne plus aucun droit — une autorisation posée sur lui en
        // redonnerait à ceux qui le portent encore.
        $horsMatrice = array_values(array_unique(array_diff(array_column($ecarts, 'role'), self::reglables())));

        if ($horsMatrice !== []) {
            return ['message' => 'Rôles qui ne se règlent pas.', 'roles' => $horsMatrice];
        }

        // Une case ne porte qu'un écart : deux décisions contraires sur la
        // même case, laquelle vaudrait ?
        $cases = array_map(static fn (array $e): string => "{$e['role']}|{$e['permission']}", $ecarts);
        $doublons = array_values(array_unique(array_diff_assoc($cases, array_unique($cases))));

        if ($doublons !== []) {
            return ['message' => 'Écarts en double.', 'doublons' => $doublons];
        }

        return null;
    }

    /** @param  list<array<string, mixed>>  $lot */
    public function remplacerCouche(array $lot, string $origine): void
    {
        PermissionGrant::where('subject_type', PermissionGrant::SUJET_ROLE)
            ->where('origin', $origine)
            ->delete();

        foreach ($lot as $ecart) {
            PermissionGrant::create([
                'subject_type' => PermissionGrant::SUJET_ROLE,
                'subject_id' => $ecart['role'],
                'permission' => $ecart['permission'],
                'effect' => $ecart['effect'],
                'scope' => $ecart['scope'] ?? null,
                'origin' => $origine,
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
    public function cumulsDuLot(array $lot): array
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
     * Toutes les cases qui ouvriraient un cumul si on les cochait, pour
     * alerter au clic plutôt qu'à l'enregistrement. Les règles sont listées
     * une fois ; chaque case renvoie à leurs indices.
     *
     * @return array{0: list<array{roles: array, motif: string}>, 1: array<string, list<int>>}
     */
    public function cumulsPossibles(): array
    {
        $regles = [];
        $indices = [];
        $cumuls = [];

        foreach (array_keys(PermissionCatalog::all()) as $droit) {
            if (PermissionCatalog::estLecture($droit)) {
                continue;
            }

            foreach (self::reglables() as $role) {
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
     * Ce que changerait un lot, sans rien enregistrer : qui gagne ou perd
     * quel droit, et quels cumuls il ouvre.
     *
     * Le lot est appliqué dans une transaction annulée, et chaque compte est
     * passé au moteur de droits avant et après : l'aperçu ne réimplémente pas
     * la décision, il la constate.
     *
     * @param  list<array<string, mixed>>  $lot
     * @return array{ecarts: int, personnes: list<array<string, mixed>>, cumuls: list<array<string, mixed>>}
     */
    public function apercu(array $lot, string $origine): array
    {
        $comptes = User::query()
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->reject(static fn (User $u): bool => in_array($u->rolesDetenus(), [['customer_guest'], [RoleCatalog::SUPPORT]], true))
            ->values();

        $resolveur = app(PermissionResolver::class);
        $resolveur->forget();
        $avant = $this->droitsAccordes($comptes, $resolveur);

        DB::beginTransaction();

        try {
            $this->remplacerCouche($lot, $origine);
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

        return ['ecarts' => count($lot), 'personnes' => $personnes, 'cumuls' => $this->cumulsDuLot($lot)];
    }

    /**
     * Empreinte d'une couche : l'écran la garde à l'ouverture, l'enregistrement
     * la compare pour ne pas écraser le travail d'un autre.
     */
    public function empreinte(string $origine): string
    {
        $lignes = PermissionGrant::query()
            ->where('subject_type', PermissionGrant::SUJET_ROLE)
            ->where('origin', $origine)
            ->orderBy('subject_id')->orderBy('permission')->orderBy('id')
            ->get(['subject_id', 'permission', 'effect', 'scope', 'reason'])
            ->map(static fn (PermissionGrant $g): array => [
                $g->subject_id, $g->permission, $g->effect, $g->scope, $g->reason,
            ])
            ->all();

        return sha1(json_encode($lignes, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Cumuls qu'ouvrirait une autorisation nominative : la personne ferait le
     * travail de ceux qui détiennent le droit, sans qu'aucun de ses rôles ne
     * le lui donne déjà.
     *
     * @return list<array{roles: array, motif: string}>
     */
    public function cumulsDUneException(User $user, string $permission): array
    {
        $detenus = $user->rolesDetenus();
        $detenteurs = PermissionCatalog::roles($permission);

        if (PermissionCatalog::estLecture($permission)
            || array_intersect(RoleCatalog::developper($detenus), $detenteurs) !== []) {
            return [];
        }

        $conflits = [];
        foreach ($detenus as $role) {
            foreach (DutySegregation::conflitsDUneAutorisation($role, $permission) as $conflit) {
                $conflits[$conflit['motif']] ??= $conflit;
            }
        }

        return array_values($conflits);
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
