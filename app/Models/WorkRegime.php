<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un régime horaire de l'établissement :
 * - Horaire fixe (sans système de quart, ex. Administration 08:00–17:00 Lun–Ven)
 * - Système de quarts / Jour-Nuit (ex. 08:00–15:00 & 15:00–22:00, ou 07:00–19:00 & 19:00–07:00)
 *
 * Chaque régime porte ses propres plages horaires (`work_shifts`) et s'applique
 * aux services rattachés (`departments`) ou, par exception, à un collaborateur (`users`).
 */
class WorkRegime extends Model
{
    use \App\Models\Concerns\VaALaCorbeille;

    public const MODE_FIXED = 'fixed';
    public const MODE_SHIFTS = 'shifts';

    /** @var array<string, string> */
    public const MODES = [
        self::MODE_FIXED  => 'Horaire fixe (sans quart)',
        self::MODE_SHIFTS => 'Système de quarts / Jour-Nuit',
    ];

    /** @var array<int, string> Jours ISO 1 (Lundi) à 7 (Dimanche). */
    public const JOURS_SEMAINE = [
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
        7 => 'Dimanche',
    ];

    /** @var array<int, string> Abréviations courtes des jours. */
    public const JOURS_COURTS = [
        1 => 'Lun',
        2 => 'Mar',
        3 => 'Mer',
        4 => 'Jeu',
        5 => 'Ven',
        6 => 'Sam',
        7 => 'Dim',
    ];

    protected $fillable = [
        'name',
        'mode',
        'working_days',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_default'   => 'boolean',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkShift::class, 'work_regime_id')
            ->orderBy('sort_order')
            ->orderBy('starts_at');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'work_regime_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'work_regime_id')
            ->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDansLOrdre(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderBy('sort_order')->orderBy('name');
    }

    public function estFixe(): bool
    {
        return $this->mode === self::MODE_FIXED;
    }

    public function estEnQuarts(): bool
    {
        return $this->mode === self::MODE_SHIFTS;
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    /**
     * Jours ouvrés ISO triés (1 = lundi .. 7 = dimanche).
     *
     * @return list<int>
     */
    public function joursTravailles(): array
    {
        if (! is_array($this->working_days)) {
            return [];
        }

        $jours = array_values(array_unique(array_filter(
            array_map('intval', $this->working_days),
            static fn (int $j): bool => $j >= 1 && $j <= 7
        )));
        sort($jours);

        return $jours;
    }

    /** Résumé lisible des jours ouvrés (« Lun – Ven », « Lun, Mer, Ven »). */
    public function workingDaysLabel(): string
    {
        $jours = $this->joursTravailles();

        if ($jours === []) {
            return 'Aucun jour configuré';
        }

        if ($jours === range($jours[0], end($jours)) && count($jours) >= 3) {
            return self::JOURS_COURTS[$jours[0]] . ' – ' . self::JOURS_COURTS[end($jours)];
        }

        return implode(', ', array_map(static fn (int $j): string => self::JOURS_COURTS[$j], $jours));
    }

    /** Régime de repli pour les services ou comptes sans régime explicitement choisi. */
    public static function defaut(): ?self
    {
        return self::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? self::query()
                ->where('is_active', true)
                ->dansLOrdre()
                ->first();
    }

    public function libelleCorbeille(): string
    {
        return $this->name;
    }
}
