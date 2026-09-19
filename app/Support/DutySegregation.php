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
 * Rien n'est bloqué à ce stade : cette classe déclare la règle et sait dire
 * quels cumuls la violent. L'application du refus, et la dérogation tracée du
 * directeur pour les petits établissements où trois personnes ne peuvent pas
 * tenir quatre fonctions, viendront avec l'écran d'édition de la matrice.
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
     * Rôle dont l'accès technique contourne les contrôles applicatifs. Il doit
     * rester nu de tout rôle métier.
     */
    public const ACCES_TECHNIQUE = 'it_support';

    /**
     * Rôles qui conduisent une opération et ne peuvent donc pas la contrôler
     * ni la vérifier eux-mêmes.
     */
    private const OPERATIONNELS = [
        'reception', 'cashier', 'housekeeping_leader', 'housekeeping_staff',
        'restaurant_chief', 'restaurant_staff', 'restaurant_cook',
        'shop_manager', 'shop_cashier', 'econome', 'accountant', 'rh_manager',
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
                'roles'  => ['cashier', 'accountant'],
                'motif'  => "Encaisser et enregistrer : c'est le montage du lapping, où l'encaissement du jour couvre le trou de la veille.",
            ],
            [
                'roles'  => ['reception', 'accountant'],
                'motif'  => "La réception encaisse aussi, par le POS Réception. Même risque que pour la caisse.",
            ],
            [
                'roles'  => ['shop_cashier', 'accountant'],
                'motif'  => "Encaisser en boutique et enregistrer les écritures.",
            ],
            [
                'roles'  => ['rh_manager', 'accountant'],
                'motif'  => "Créer l'employé et le payer : c'est l'employé fantôme.",
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
        $conflits = [];

        foreach (self::incompatibilities() as $paire) {
            [$a, $b] = $paire['roles'];
            if (in_array($a, $roles, true) && in_array($b, $roles, true)) {
                $conflits[] = $paire;
            }
        }

        // Règle générale plutôt que couple à couple : le contrôle indépendant
        // et l'accès technique sont incompatibles avec *tout* rôle opérationnel.
        foreach ([self::CONTROLE_INDEPENDANT, self::CONTROLE_DE_GESTION, self::ACCES_TECHNIQUE] as $transverse) {
            if (!in_array($transverse, $roles, true)) {
                continue;
            }

            foreach (array_intersect(self::OPERATIONNELS, $roles) as $operationnel) {
                $conflits[] = [
                    'roles' => [$transverse, $operationnel],
                    'motif' => match ($transverse) {
                        self::CONTROLE_INDEPENDANT => "Un contrôle exercé sur son propre travail n'est plus un contrôle.",
                        self::CONTROLE_DE_GESTION  => "Le contrôle de gestion voit tous les services : il perd sa vue d'ensemble s'il participe à l'un d'eux.",
                        default                    => "L'accès technique contourne les contrôles applicatifs : il doit rester nu de tout rôle métier.",
                    },
                ];
            }
        }

        return $conflits;
    }

    /** Le jeu de rôles respecte-t-il la séparation des tâches ? */
    public static function isCompatible(array $roles): bool
    {
        return self::conflictsFor($roles) === [];
    }
}
