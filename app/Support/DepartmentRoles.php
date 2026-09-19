<?php

namespace App\Support;

/**
 * Rôles qu'un département confère implicitement à ses membres.
 *
 * Cette carte vivait au milieu d'un `match` dans EnsureRoleAccess, invisible
 * depuis l'extérieur. C'est pourtant une seconde voie d'octroi : un employé
 * rattaché à « Comptabilité et Finance » se voit reconnaître accountant,
 * cashier et controller sans détenir aucun de ces rôles.
 *
 * La sortir ici ne change rien au comportement. Elle la rend lisible,
 * testable, et prête à devenir éditable quand le département cessera de
 * conférer des rôles pour ne plus faire que restreindre les données.
 */
class DepartmentRoles
{
    /**
     * @return array<string, list<string>> slug du département => rôles conférés
     */
    public static function all(): array
    {
        return [
            'direction_generale'       => ['manager'],
            'reception_front_office'   => ['reception', 'cashier'],
            'housekeeping_hebergement' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff'],
            'restauration_fb'          => ['restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier'],
            'comptabilite_finance'     => ['accountant', 'cashier', 'controller'],
            'boutique_commerce'        => ['shop_manager', 'shop_cashier'],
            'ressources_humaines'      => ['manager'],
            'informatique_it'          => ['it_support'],
            'qualite_controle'         => ['controller', 'manager'],
        ];
    }

    /** Rôles conférés par un département. Vide si le département est inconnu. */
    public static function for(?string $departmentSlug): array
    {
        if ($departmentSlug === null) {
            return [];
        }

        return self::all()[$departmentSlug] ?? [];
    }
}
