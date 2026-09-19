<?php

namespace App\Support\Document;

/**
 * Une colonne d'un document imprimable.
 *
 * Elle sait trois choses : où prendre la valeur, comment l'afficher, et
 * comment l'aligner. Séparer la mise en forme de la donnée évite que chaque
 * écran réinvente son formatage des montants ou des dates — et qu'un export
 * Excel affiche « 73 200 FCFA » là où une feuille de calcul attend 73200.
 */
class Colonne
{
    public const TEXTE  = 'texte';
    public const NOMBRE = 'nombre';
    public const MONTANT = 'montant';
    public const DATE   = 'date';
    public const DATE_HEURE = 'date_heure';

    private function __construct(
        public readonly string $cle,
        public readonly string $libelle,
        public readonly string $type = self::TEXTE,
        public readonly bool $totalise = false,
    ) {
    }

    public static function texte(string $cle, string $libelle): self
    {
        return new self($cle, $libelle, self::TEXTE);
    }

    public static function nombre(string $cle, string $libelle): self
    {
        return new self($cle, $libelle, self::NOMBRE);
    }

    /** Montant en plus petite unité — les centimes sont la source, jamais le franc. */
    public static function montant(string $cle, string $libelle, bool $totalise = true): self
    {
        return new self($cle, $libelle, self::MONTANT, $totalise);
    }

    public static function date(string $cle, string $libelle): self
    {
        return new self($cle, $libelle, self::DATE);
    }

    public static function dateHeure(string $cle, string $libelle): self
    {
        return new self($cle, $libelle, self::DATE_HEURE);
    }

    /** Les nombres se lisent alignés à droite, le texte à gauche. */
    public function alignementDroite(): bool
    {
        return in_array($this->type, [self::NOMBRE, self::MONTANT], true);
    }

    /**
     * Valeur affichable.
     *
     * Le montant reste en unité monétaire entière : c'est au rendu de décider
     * s'il l'écrit « 73 200 » ou le laisse brut pour un tableur.
     */
    public function formater(mixed $valeur, string $devise = 'FCFA'): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        return match ($this->type) {
            self::MONTANT    => number_format(((int) $valeur) / 100, 0, ',', ' ') . ' ' . $devise,
            self::NOMBRE     => is_numeric($valeur)
                ? rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',')
                : (string) $valeur,
            self::DATE       => $this->enDate($valeur)?->format('d/m/Y') ?? (string) $valeur,
            self::DATE_HEURE => $this->enDate($valeur)?->format('d/m/Y H:i') ?? (string) $valeur,
            default          => (string) $valeur,
        };
    }

    /** Valeur brute, pour un tableur : un nombre doit rester un nombre. */
    public function valeurBrute(mixed $valeur): mixed
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        return match ($this->type) {
            self::MONTANT => ((int) $valeur) / 100,
            self::NOMBRE  => is_numeric($valeur) ? (float) $valeur : (string) $valeur,
            self::DATE, self::DATE_HEURE => $this->formater($valeur),
            default       => (string) $valeur,
        };
    }

    private function enDate(mixed $valeur): ?\DateTimeInterface
    {
        if ($valeur instanceof \DateTimeInterface) {
            return $valeur;
        }

        try {
            return new \DateTimeImmutable((string) $valeur);
        } catch (\Throwable) {
            // Une valeur qui n'est pas une date s'affiche telle quelle plutôt
            // que de faire échouer tout le document.
            return null;
        }
    }
}
