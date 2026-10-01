<?php

namespace App\Support;

/**
 * Séparation des tâches : couples de rôles qu'une même personne ne doit pas
 * cumuler.
 *
 * Quatre fonctions doivent rester dans des mains différentes — autoriser,
 * détenir, enregistrer, contrôler. Qui en cumule deux sur un même cycle peut
 * commettre un acte et le dissimuler.
 *
 * Les rôles sont examinés avec ce qu'ils incluent (RoleCatalog) : un chef fait
 * le travail de ses membres, il en porte donc aussi les incompatibilités. Le
 * conflit est rapporté sous les rôles que la personne détient réellement.
 *
 * Le refus s'applique à l'attribution des rôles (rubrique Utilisateurs), avec
 * une dérogation motivée et tracée pour les petits établissements où trois
 * personnes ne peuvent pas tenir quatre fonctions.
 */
class DutySegregation
{
    /**
     * Rôle dont le travail consiste à contrôler celui des autres. Il ne se
     * cumule avec aucun rôle opérationnel : un contrôle exercé sur son propre
     * travail n'est plus un contrôle.
     */
    public const CONTROLE_INDEPENDANT = 'quality_auditor';

    /**
     * Contrôleur de gestion : il voit tous les services et n'écrit nulle part.
     * Sa vue d'ensemble n'a de valeur que s'il ne participe pas aux opérations
     * qu'il surveille.
     */
    public const CONTROLE_DE_GESTION = 'controller';

    /**
     * L'administrateur : il crée les comptes, attribue les rôles et règle les
     * droits. Il ne se cumule avec aucun autre rôle — celui qui distribue les
     * droits ne doit pas pouvoir s'en servir lui-même.
     */
    public const ACCES_TECHNIQUE = RoleCatalog::ADMIN;

    /**
     * Rôles qui conduisent une opération et ne peuvent donc pas la contrôler
     * ni la vérifier eux-mêmes.
     */
    private const OPERATIONNELS = [
        'reception_chief', 'reception',
        'housekeeping_leader', 'housekeeping_staff',
        'restaurant_manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier',
        'shop_manager', 'shop_cashier',
        'econome', 'storekeeper',
        'finance_manager', 'accountant',
    ];

    /**
     * Couples explicitement incompatibles, et la raison — qui doit rester
     * lisible : c'est elle qu'on affichera au directeur quand il demandera
     * une dérogation.
     *
     * @return list<array{roles: array{0: string, 1: string}, motif: string}>
     */
    public static function incompatibilities(): array
    {
        return [
            [
                'roles'  => ['econome', 'accountant'],
                'motif'  => "Détenir le stock et tenir les livres : un vol se couvre par une écriture de régularisation.",
            ],
            [
                'roles'  => ['storekeeper', 'accountant'],
                'motif'  => "Détenir le stock et tenir les livres : un vol se couvre par une écriture de régularisation.",
            ],
            [
                'roles'  => ['cashier', 'accountant'],
                'motif'  => "Encaisser et enregistrer : c'est le montage du lapping, où l'encaissement du jour couvre le trou de la veille.",
            ],
            [
                'roles'  => ['restaurant_manager', 'accountant'],
                'motif'  => "Le responsable de restaurant encaisse si nécessaire : même risque que pour la caisse.",
            ],
            [
                'roles'  => ['reception', 'accountant'],
                'motif'  => "La réception encaisse aussi, par le POS Réception. Même risque que pour la caisse.",
            ],
            [
                'roles'  => ['shop_cashier', 'accountant'],
                'motif'  => "Encaisser en boutique et enregistrer les écritures.",
            ],
        ];
    }

    /**
     * Cumuls interdits présents dans un jeu de rôles.
     *
     * @param  list<string>  $roles
     * @return list<array{roles: array{0: string, 1: string}, motif: string}>
     */
    public static function conflictsFor(array $roles): array
    {
        $roles = array_values(array_unique($roles));

        // Rôle exercé => rôle détenu qui l'apporte : un chef exerce aussi le
        // rôle de ses membres.
        $porteurs = [];
        foreach ($roles as $detenu) {
            foreach (RoleCatalog::developper([$detenu]) as $exerce) {
                $porteurs[$exerce] ??= $detenu;
            }
        }

        $conflits = [];
        $ajouter  = static function (string $a, string $b, string $motif) use (&$conflits): void {
            $cle = [$a, $b];
            sort($cle);
            $conflits[implode('|', $cle)] ??= ['roles' => [$a, $b], 'motif' => $motif];
        };

        foreach (self::incompatibilities() as $paire) {
            [$a, $b] = $paire['roles'];

            // Un même rôle détenu qui apporterait les deux côtés serait une
            // erreur du référentiel, non un cumul : le test du catalogue la garde.
            if (isset($porteurs[$a], $porteurs[$b]) && $porteurs[$a] !== $porteurs[$b]) {
                $ajouter($porteurs[$a], $porteurs[$b], $paire['motif']);
            }
        }

        // Règle générale plutôt que couple à couple : le contrôle est
        // incompatible avec tout rôle opérationnel…
        foreach ([self::CONTROLE_INDEPENDANT, self::CONTROLE_DE_GESTION] as $transverse) {
            if (!in_array($transverse, $roles, true)) {
                continue;
            }

            foreach ($roles as $detenu) {
                if (array_intersect(RoleCatalog::developper([$detenu]), self::OPERATIONNELS) !== []) {
                    $ajouter($transverse, $detenu, $transverse === self::CONTROLE_INDEPENDANT
                        ? "Un contrôle exercé sur son propre travail n'est plus un contrôle."
                        : "Le contrôle de gestion voit tous les services : il perd sa vue d'ensemble s'il participe à l'un d'eux.");
                }
            }
        }

        // … et l'administrateur, avec tout autre rôle.
        if (in_array(self::ACCES_TECHNIQUE, $roles, true)) {
            foreach ($roles as $detenu) {
                if ($detenu !== self::ACCES_TECHNIQUE) {
                    $ajouter(self::ACCES_TECHNIQUE, $detenu,
                        "L'administrateur distribue les droits : il ne doit pas pouvoir s'en servir lui-même.");
                }
            }
        }

        return array_values($conflits);
    }

    /** Le jeu de rôles respecte-t-il la séparation des tâches ? */
    public static function isCompatible(array $roles): bool
    {
        return self::conflictsFor($roles) === [];
    }

    /**
     * Cumuls qu'ouvrirait l'autorisation d'un droit à un rôle qui ne le
     * détient pas.
     *
     * Écrire, c'est exercer une fonction : le rôle qui reçoit une écriture
     * fait désormais le travail de ceux qui la détiennent. Il en porte donc
     * les incompatibilités — un caissier qui contresigne des caisses est un
     * caissier comptable. Le contrôle, qui n'écrit nulle part, ne peut rien
     * recevoir de tel.
     *
     * Une consultation n'ouvre aucun cumul, et l'administrateur n'est pas un
     * détenteur à imiter : régler la configuration n'est pas une fonction
     * d'exploitation.
     *
     * @return list<array{roles: array{0: string, 1: string}, motif: string}>
     */
    public static function conflitsDUneAutorisation(string $role, string $permission): array
    {
        if (PermissionCatalog::estLecture($permission)
            || in_array($role, PermissionCatalog::roles($permission), true)) {
            return [];
        }

        $detenteurs = array_values(array_diff(PermissionCatalog::roles($permission), [self::ACCES_TECHNIQUE]));
        $conflits = [];

        if (in_array($role, [self::CONTROLE_INDEPENDANT, self::CONTROLE_DE_GESTION], true)) {
            $conflits[] = [
                'roles' => [$role, $detenteurs[0] ?? $permission],
                'motif' => $role === self::CONTROLE_INDEPENDANT
                    ? "Un contrôle exercé sur son propre travail n'est plus un contrôle."
                    : "Le contrôle de gestion voit tous les services : il perd sa vue d'ensemble s'il participe à l'un d'eux.",
            ];
        }

        foreach ($detenteurs as $detenteur) {
            foreach (self::conflictsFor([$role, $detenteur]) as $conflit) {
                $conflits[] = $conflit;
            }
        }

        // Un même motif reviendrait pour chaque chef qui inclut le rôle
        // incompatible : une alerte par raison suffit.
        $uniques = [];
        foreach ($conflits as $conflit) {
            $uniques[$conflit['motif']] ??= $conflit;
        }

        return array_values($uniques);
    }
}
