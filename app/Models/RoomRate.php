<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tarif par critère d'un type de chambre (grille tarifaire, Hôtel › Tarifs).
 *
 * CDC Section 4.6 : Tarification complexe. Ordre de préséance, du plus fort au
 * plus faible :
 * 1. Groupe (GroupBooking spécifique)
 * 2. Événement (FESPAM, FESTIVAM…)
 * 3. Saison (haute / basse saison)
 * 4. Jours de la semaine (week-end vs semaine)
 * 5. Long séjour
 * 6. Dernière minute
 * 7. Prix de base (RoomType.base_price)
 *
 * Ce modèle porte les règles 2 à 6 ; TarifSejour choisit, nuit par nuit, celle
 * qui s'applique. Une seule règle par nuit : la priorité saisie d'abord, puis
 * l'ordre ci-dessus.
 *
 * Montants en centimes : prix fixe et remise en montant. Un pourcentage peut
 * être positif (majoration) ou négatif (remise).
 */
class RoomRate extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    use HasFactory;

    public const TYPE_EVENT = 'event';
    public const TYPE_SEASONAL = 'seasonal';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_LONG_STAY = 'long_stay';
    public const TYPE_LAST_MINUTE = 'last_minute';

    /** Les critères, dans l'ordre de préséance (le premier l'emporte à priorité égale). */
    public const TYPES = [
        self::TYPE_EVENT => 'Événement',
        self::TYPE_SEASONAL => 'Saison',
        self::TYPE_WEEKLY => 'Jours de la semaine',
        self::TYPE_LONG_STAY => 'Long séjour',
        self::TYPE_LAST_MINUTE => 'Dernière minute',
    ];

    public const AJUSTEMENT_FIXE = 'fixed';
    public const AJUSTEMENT_POURCENTAGE = 'percentage';
    public const AJUSTEMENT_REMISE = 'amount_off';

    public const AJUSTEMENTS = [
        self::AJUSTEMENT_FIXE => 'Prix fixe de la nuit',
        self::AJUSTEMENT_POURCENTAGE => 'Majoration ou remise en %',
        self::AJUSTEMENT_REMISE => 'Remise en montant',
    ];

    /** Jours de la semaine, au format de Carbon::dayOfWeek (0 = dimanche). */
    public const JOURS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 0 => 'Dim'];

    protected $fillable = [
        'room_type_id',
        'name',
        'rate_type',
        'start_date',
        'end_date',
        'days_of_week',
        'price_adjustment_type',
        'price_adjustment',
        'min_nights',
        'max_nights',
        'min_advance_days',
        'max_advance_days',
        'event_name',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'price_adjustment' => 'integer',
        'min_nights' => 'integer',
        'max_nights' => 'integer',
        'min_advance_days' => 'integer',
        'max_advance_days' => 'integer',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Rang du critère dans l'ordre de préséance : plus il est grand, plus il l'emporte. */
    public function rang(): int
    {
        $position = array_search($this->rate_type, array_keys(self::TYPES), true);

        return $position === false ? 0 : count(self::TYPES) - $position;
    }

    /**
     * Ce tarif vaut-il pour la nuit du $date, dans un séjour de $nights nuits
     * réservé $advanceDays jours avant l'arrivée ? Une période vide ne borne rien.
     */
    public function isApplicable(CarbonInterface $date, int $nights, int $advanceDays): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->start_date && $date->lt($this->start_date->copy()->startOfDay())) {
            return false;
        }
        if ($this->end_date && $date->gt($this->end_date->copy()->endOfDay())) {
            return false;
        }
        if ($this->days_of_week && ! in_array($date->dayOfWeek, array_map('intval', $this->days_of_week), true)) {
            return false;
        }
        if ($this->min_nights && $nights < $this->min_nights) {
            return false;
        }
        if ($this->max_nights && $nights > $this->max_nights) {
            return false;
        }
        if ($this->min_advance_days && $advanceDays < $this->min_advance_days) {
            return false;
        }
        // 0 jour : réservé le jour même. Un plafond nul compte donc.
        if ($this->max_advance_days !== null && $advanceDays > $this->max_advance_days) {
            return false;
        }

        return true;
    }

    /** Prix de la nuit, en centimes, à partir du prix de base. */
    public function calculatePrice(int $basePrice): int
    {
        return match ($this->price_adjustment_type) {
            self::AJUSTEMENT_FIXE => max(0, $this->price_adjustment),
            self::AJUSTEMENT_POURCENTAGE => max(0, (int) round($basePrice * (1 + $this->price_adjustment / 100))),
            self::AJUSTEMENT_REMISE => max(0, $basePrice - $this->price_adjustment),
            default => $basePrice,
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->rate_type] ?? $this->rate_type;
    }

    /** « +20 % », « −5 000 F », « 45 000 F la nuit ». */
    public function ajustementLabel(): string
    {
        $montant = fn (int $centimes) => number_format(abs($centimes) / 100, 0, ',', ' ') . ' F';

        return match ($this->price_adjustment_type) {
            self::AJUSTEMENT_FIXE => $montant($this->price_adjustment) . ' la nuit',
            self::AJUSTEMENT_POURCENTAGE => ($this->price_adjustment >= 0 ? '+' : '−') . abs($this->price_adjustment) . ' %',
            self::AJUSTEMENT_REMISE => '−' . $montant($this->price_adjustment),
            default => '—',
        };
    }

    /** Les conditions, en clair : période, jours, nuits, délai. */
    public function conditionsLabel(): string
    {
        $parties = [];
        if ($this->start_date || $this->end_date) {
            $parties[] = ($this->start_date ? 'du ' . $this->start_date->format('d/m/Y') : 'jusqu’')
                . ($this->end_date ? ($this->start_date ? ' au ' : 'au ') . $this->end_date->format('d/m/Y') : ' sans fin');
        } else {
            $parties[] = 'toute l’année';
        }
        if ($this->days_of_week) {
            $jours = array_map('intval', $this->days_of_week);
            $parties[] = implode(', ', array_values(array_filter(self::JOURS, fn ($libelle, $jour) => in_array($jour, $jours, true), ARRAY_FILTER_USE_BOTH)));
        }
        if ($this->min_nights || $this->max_nights) {
            $parties[] = match (true) {
                $this->min_nights && $this->max_nights => "{$this->min_nights} à {$this->max_nights} nuits",
                (bool) $this->min_nights => "dès {$this->min_nights} nuits",
                default => "jusqu’à {$this->max_nights} nuits",
            };
        }
        if ($this->min_advance_days || $this->max_advance_days !== null) {
            $parties[] = match (true) {
                $this->min_advance_days && $this->max_advance_days !== null => "réservé {$this->min_advance_days} à {$this->max_advance_days} jours avant",
                (bool) $this->min_advance_days => "réservé au moins {$this->min_advance_days} jours avant",
                default => "réservé {$this->max_advance_days} jour(s) avant ou moins",
            };
        }
        if ($this->event_name) {
            $parties[] = $this->event_name;
        }

        return implode(' · ', $parties);
    }

    /** Ce que la corbeille affiche pour cette fiche. */
    public function libelleCorbeille(): string
    {
        return ($this->name ?? 'Tarif') . ($this->roomType?->name ? ' — ' . $this->roomType->name : '');
    }
}
