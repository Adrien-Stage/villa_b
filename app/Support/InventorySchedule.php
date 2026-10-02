<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendrier des inventaires généraux, réglé dans Paramètres > Inventaire.
 *
 * Deux sortes de dates : des jours du mois, qui reviennent chaque mois
 * (le 1er, ou le dernier jour du mois), et des dates fixes de l'année
 * (31 décembre, 1er janvier), qui encadrent le changement d'exercice.
 *
 * Le calendrier ne déclenche rien de lui-même : il dit quand rappeler aux
 * services de compter. L'économe ouvre l'inventaire quand le comptage
 * commence — c'est l'ouverture qui gèle le magasin.
 */
class InventorySchedule
{
    /** Valeur des jours du mois qui désigne le dernier jour, quel qu'il soit. */
    public const LAST_DAY = 'last';

    /**
     * @param  list<int|string>  $monthDays   1..31 ou LAST_DAY
     * @param  list<string>      $fixedDates  « MM-JJ »
     */
    public function __construct(
        public readonly array $monthDays = [],
        public readonly array $fixedDates = [],
        public readonly bool $remindDayBefore = true,
    ) {
    }

    public static function current(): self
    {
        return self::fromSettings(Tenant::first()?->settings['inventaire'] ?? []);
    }

    /** @param  array<string, mixed>  $settings */
    public static function fromSettings(array $settings): self
    {
        return new self(
            monthDays: array_values((array) ($settings['month_days'] ?? [])),
            fixedDates: array_values((array) ($settings['fixed_dates'] ?? [])),
            remindDayBefore: (bool) ($settings['remind_day_before'] ?? true),
        );
    }

    public function isConfigured(): bool
    {
        return $this->monthDays !== [] || $this->fixedDates !== [];
    }

    public function isInventoryDay(CarbonInterface $date): bool
    {
        // Le 31 n'existe pas en avril : il ne se reporte pas, seul « dernier
        // jour du mois » suit la longueur du mois.
        foreach ($this->monthDays as $jour) {
            if ($jour === self::LAST_DAY ? $date->isLastOfMonth() : (int) $jour === $date->day) {
                return true;
            }
        }

        return in_array($date->format('m-d'), $this->fixedDates, true);
    }

    /** Prochain jour d'inventaire à partir de $from inclus, sur un an. */
    public function next(CarbonInterface $from): ?CarbonImmutable
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $jour = CarbonImmutable::parse($from)->startOfDay();

        for ($i = 0; $i <= 366; $i++) {
            if ($this->isInventoryDay($jour)) {
                return $jour;
            }

            $jour = $jour->addDay();
        }

        return null;
    }

    /** Libellé lisible du calendrier, pour l'écran des inventaires. */
    public function describe(): string
    {
        $parties = [];

        foreach ($this->monthDays as $jour) {
            $parties[] = $jour === self::LAST_DAY ? 'le dernier jour de chaque mois' : ((int) $jour === 1 ? 'le 1er de chaque mois' : "le {$jour} de chaque mois");
        }

        foreach ($this->fixedDates as $date) {
            // Année bissextile : le 29 février reste un 29 février.
            $jour = CarbonImmutable::createFromFormat('!Y-m-d', "2000-{$date}")->locale('fr');
            $parties[] = 'le ' . ($jour->day === 1 ? '1er' : $jour->day) . ' ' . $jour->isoFormat('MMMM');
        }

        return $parties === [] ? 'Aucun inventaire général planifié' : ucfirst(implode(', ', $parties));
    }
}
