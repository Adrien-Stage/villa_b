<?php

namespace App\Support;

/**
 * Étendue des données qu'un droit laisse voir.
 *
 * Le rôle dit ce qu'on peut faire ; la portée dit sur quoi. Sans elle, « voir
 * les employés » veut dire tous les employés de l'établissement, et un chef de
 * service consulte le dossier de ceux qu'il n'encadre pas.
 *
 * Trois étendues, de la plus étroite à la plus large. L'absence de portée
 * déclarée vaut ÉTABLISSEMENT : c'est le comportement d'avant, et rien ne se
 * restreint tant que personne ne l'a demandé.
 */
class PermissionScope
{
    /** Ce que la personne a elle-même créé ou reçu. */
    public const PROPRE = 'propre';

    /** Ce qui relève de son département. */
    public const DEPARTEMENT = 'departement';

    /** Tout l'établissement. */
    public const ETABLISSEMENT = 'etablissement';

    public const DEFAUT = self::ETABLISSEMENT;

    /** De la plus étroite à la plus large. */
    public const ORDRE = [self::PROPRE, self::DEPARTEMENT, self::ETABLISSEMENT];

    public static function valide(?string $portee): bool
    {
        return $portee !== null && in_array($portee, self::ORDRE, true);
    }

    /**
     * La plus étroite de deux portées.
     *
     * Une restriction ne se lève pas en ajoutant un rôle : c'est la même règle
     * que pour le refus d'un droit, appliquée à l'étendue.
     */
    public static function laPlusEtroite(?string $a, ?string $b): string
    {
        $a = self::valide($a) ? $a : self::DEFAUT;
        $b = self::valide($b) ? $b : self::DEFAUT;

        return array_search($a, self::ORDRE, true) <= array_search($b, self::ORDRE, true) ? $a : $b;
    }

    public static function libelle(string $portee): string
    {
        return match ($portee) {
            self::PROPRE      => 'Ses propres données',
            self::DEPARTEMENT => 'Son département',
            default           => "Tout l'établissement",
        };
    }
}
