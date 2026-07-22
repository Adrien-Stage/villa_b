<?php

namespace App\Support;

/**
 * Accès au référentiel pays (config/countries.php).
 *
 * Sert deux usages : alimenter les listes déroulantes de saisie du pays d'un
 * client, et enrichir l'analytique des marchés émetteurs (nom lisible,
 * continent, code ISO numérique pour le fond de carte).
 */
class Countries
{
    /** @var array<string, array{0:string,1:string,2:int}>|null */
    private static ?array $cache = null;

    /** @return array<string, array{0:string,1:string,2:int}> */
    public static function all(): array
    {
        return self::$cache ??= (array) config('countries', []);
    }

    /**
     * Normalise une valeur saisie en code ISO alpha-2 connu, ou null.
     * Tolère la casse et les espaces parasites d'un import ou d'un copier-coller.
     */
    public static function normalize(?string $iso): ?string
    {
        if ($iso === null) {
            return null;
        }

        $code = strtoupper(trim($iso));

        return isset(self::all()[$code]) ? $code : null;
    }

    /** Nom français du pays, ou le code brut s'il est inconnu. */
    public static function name(?string $iso): ?string
    {
        $code = self::normalize($iso);

        return $code ? self::all()[$code][0] : ($iso ?: null);
    }

    /** Continent du pays, ou null si le code est inconnu. */
    public static function continent(?string $iso): ?string
    {
        $code = self::normalize($iso);

        return $code ? self::all()[$code][1] : null;
    }

    /** Code ISO 3166-1 numérique (jointure avec le fond de carte), ou null. */
    public static function numeric(?string $iso): ?int
    {
        $code = self::normalize($iso);

        return $code ? self::all()[$code][2] : null;
    }

    /**
     * Liste ISO => nom, triée alphabétiquement sur le nom, pour les <select>.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $iso => [$name]) {
            $options[$iso] = $name;
        }

        // Tri sur le libellé affiché, en tenant compte des accents.
        $collator = class_exists(\Collator::class) ? new \Collator('fr_FR') : null;
        $collator
            ? uasort($options, fn ($a, $b) => $collator->compare($a, $b))
            : asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}
