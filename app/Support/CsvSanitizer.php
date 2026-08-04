<?php

namespace App\Support;

/**
 * Utilitaire de nettoyage et typage CSV.
 * Protège contre les injections de formules CSV (Excel/LibreOffice)
 * et formate les valeurs selon les types attendus.
 */
class CsvSanitizer
{
    /**
     * Neutralise l'injection de formule Excel à l'exportation.
     */
    public static function sanitizeCell(mixed $value): string
    {
        $str = (string) ($value ?? '');

        if ($str === '') {
            return '';
        }

        // Si le contenu commence par =, @, \t, \r, ou par +/- suivi d'une lettre (ex. +cmd / -cmd)
        if (preg_match('/^[\=\@\t\r]/', $str) || preg_match('/^[\+\-][a-zA-Z]/', $str)) {
            return "'" . $str;
        }

        return $str;
    }

    /**
     * Nettoie une valeur lue à l'importation (retire l'apostrophe de neutralisation Excel).
     */
    public static function unquoteCell(string $val): string
    {
        if (str_starts_with($val, "'") && strlen($val) > 1) {
            $sub = substr($val, 1);
            if (preg_match('/^[\=\@\t\r]/', $sub) || preg_match('/^[\+\-][a-zA-Z]/', $sub)) {
                return $sub;
            }
        }

        return $val;
    }

    /**
     * Convertit et valide une valeur selon son type cible.
     *
     * @return array{0: mixed, 1: ?string} [valeurCastée, erreur]
     */
    public static function castValue(string $type, mixed $rawValue): array
    {
        $val = self::unquoteCell(trim((string) $rawValue));

        return match (mb_strtolower($type)) {
            'integer', 'int' => self::castInteger($val),
            'decimal', 'float', 'double', 'numeric' => self::castDecimal($val),
            'boolean', 'bool' => [self::castBoolean($val), null],
            'json', 'array' => self::castJson($val),
            default => [$val, null],
        };
    }

    private static function castInteger(string $val): array
    {
        if ($val === '') {
            return [0, null];
        }
        $valClean = str_replace([' ', ','], ['', '.'], $val);
        if (!is_numeric($valClean)) {
            return [null, "Valeur « {$val} » invalide pour un nombre entier."];
        }

        return [(int) round((float) $valClean), null];
    }

    private static function castDecimal(string $val): array
    {
        if ($val === '') {
            return [0.0, null];
        }
        $valClean = str_replace([' ', ','], ['', '.'], $val);
        if (!is_numeric($valClean)) {
            return [null, "Valeur « {$val} » invalide pour un nombre décimal."];
        }

        return [(float) $valClean, null];
    }

    private static function castBoolean(string $val): bool
    {
        $lower = mb_strtolower(trim($val));
        return in_array($lower, ['1', 'true', 'oui', 'yes', 'vrai', 'o', 'y'], true);
    }

    private static function castJson(string $val): array
    {
        if ($val === '') {
            return [[], null];
        }
        $decoded = json_decode($val, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [null, "Valeur « {$val} » n'est pas un JSON valide."];
        }

        return [$decoded, null];
    }
}
