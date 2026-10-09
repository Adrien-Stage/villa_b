<?php

namespace App\Editions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;

/**
 * Un filtre d'édition : une période, un jour, une semaine ou un choix.
 *
 * Le filtre sait se lire depuis la requête et se valider : une valeur hors
 * des options proposées est ramenée à « tous », une date illisible à sa
 * valeur par défaut. Une édition ne reçoit donc que des valeurs sûres.
 */
class Filtre
{
    public const PERIODE = 'periode';
    public const JOUR = 'jour';
    public const SEMAINE = 'semaine';
    public const CHOIX = 'choix';

    /** Valeur des choix qui ne filtre pas. */
    public const TOUS = '';

    /** @param  (Closure(User): array<string, string>)|null  $options */
    private function __construct(
        public readonly string $cle,
        public readonly string $libelle,
        public readonly string $type,
        private readonly mixed $defaut = null,
        private readonly ?Closure $options = null,
        public readonly string $libelleTous = 'Tous',
    ) {}

    /** Du … au … ; « mois » par défaut couvre le mois en cours, « jour » la journée. */
    public static function periode(string $defaut = 'jour'): self
    {
        return new self('periode', 'Période', self::PERIODE, $defaut);
    }

    public static function jour(string $libelle = 'Date'): self
    {
        return new self('jour', $libelle, self::JOUR);
    }

    public static function semaine(): self
    {
        return new self('semaine', 'Semaine', self::SEMAINE);
    }

    /** @param  Closure(User): array<string, string>  $options  valeur => libellé */
    public static function choix(string $cle, string $libelle, Closure $options, string $libelleTous = 'Tous'): self
    {
        return new self($cle, $libelle, self::CHOIX, null, $options, $libelleTous);
    }

    /** @return array<string, string> */
    public function options(User $user): array
    {
        return $this->options ? ($this->options)($user) : [];
    }

    /**
     * Valeur lue et validée.
     *
     * @param  array<string, mixed>  $saisie
     * @return mixed  période : [du, au] ; jour et semaine : CarbonImmutable ; choix : string
     */
    public function lire(array $saisie, User $user): mixed
    {
        return match ($this->type) {
            self::PERIODE => $this->lirePeriode($saisie),
            self::JOUR => $this->date($saisie['jour'] ?? null) ?? CarbonImmutable::today(),
            self::SEMAINE => ($this->date($saisie['semaine'] ?? null) ?? CarbonImmutable::today())->startOfWeek(),
            default => array_key_exists((string) ($saisie[$this->cle] ?? ''), $this->options($user))
                ? (string) $saisie[$this->cle]
                : self::TOUS,
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function lirePeriode(array $saisie): array
    {
        [$du, $au] = $this->defaut === 'mois'
            ? [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()]
            : [CarbonImmutable::today(), CarbonImmutable::today()];

        $du = $this->date($saisie['du'] ?? null) ?? $du;
        $au = $this->date($saisie['au'] ?? null) ?? $au;

        // Une période à l'envers se lit à l'endroit plutôt que de ne rien rendre.
        return $du->greaterThan($au) ? [$au, $du] : [$du, $au];
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valeur)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
