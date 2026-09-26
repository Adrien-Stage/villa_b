<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CancellationPolicy : Politique d'annulation d'un séjour hôtelier
 *
 * Selon les standards hôteliers, définit :
 * - Le type de pénalité (première nuit, pourcentage, montant fixe, non-remboursable, gratuit)
 * - La valeur de la pénalité le cas échéant
 * - Le délai d'annulation gratuite (jours avant arrivée et heure limite)
 * - L'application par défaut ou spécifique
 */
class CancellationPolicy extends Model
{
    use HasFactory;

    public const PENALTY_FREE           = 'free';
    public const PENALTY_FIRST_NIGHT    = 'first_night';
    public const PENALTY_PERCENTAGE     = 'percentage';
    public const PENALTY_FIXED_AMOUNT   = 'fixed_amount';
    public const PENALTY_NON_REFUNDABLE = 'non_refundable';

    public const PENALTY_TYPES = [
        self::PENALTY_FREE           => 'Sans frais (100% remboursé)',
        self::PENALTY_FIRST_NIGHT    => 'Première nuitée facturée',
        self::PENALTY_PERCENTAGE     => 'Pourcentage du séjour',
        self::PENALTY_FIXED_AMOUNT   => 'Montant forfaitaire fixe',
        self::PENALTY_NON_REFUNDABLE => 'Non remboursable (100% de frais)',
    ];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'penalty_type',
        'penalty_value',
        'free_cancel_days_before',
        'free_cancel_time',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'penalty_value'           => 'integer',
        'free_cancel_days_before' => 'integer',
        'is_default'              => 'boolean',
        'is_active'               => 'boolean',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Retourne la politique par défaut active de l'établissement
     */
    public static function getDefault(): ?self
    {
        return self::query()->active()->default()->first()
            ?? self::query()->active()->first();
    }

    public function penaltyTypeLabel(): string
    {
        return self::PENALTY_TYPES[$this->penalty_type] ?? $this->penalty_type;
    }

    /**
     * Calcule la date et heure limite exacte pour annuler sans pénalité.
     */
    public function calculateFreeCancelUntil(CarbonInterface $checkInDate): Carbon
    {
        $timeParts = explode(':', $this->free_cancel_time ?: '18:00');
        $hour   = (int) ($timeParts[0] ?? 18);
        $minute = (int) ($timeParts[1] ?? 0);

        return Carbon::parse($checkInDate)
            ->copy()
            ->subDays(max(0, (int) $this->free_cancel_days_before))
            ->setTime($hour, $minute, 0);
    }

    /**
     * Description humaine de la politique
     */
    public function summaryText(): string
    {
        if ($this->penalty_type === self::PENALTY_NON_REFUNDABLE) {
            return "Non remboursable : 100% du séjour est facturé dès la réservation.";
        }

        if ($this->penalty_type === self::PENALTY_FREE) {
            return "Annulation sans frais à tout moment avant l'arrivée.";
        }

        $timeStr = $this->free_cancel_time ?: '18:00';
        $days = (int) $this->free_cancel_days_before;

        $delaiStr = $days === 0
            ? "le jour de l'arrivée avant {$timeStr}"
            : "jusqu'à {$days} jour(s) avant l'arrivée à {$timeStr}";

        $penaliteStr = match ($this->penalty_type) {
            self::PENALTY_FIRST_NIGHT  => "la première nuitée est due",
            self::PENALTY_PERCENTAGE   => "{$this->penalty_value}% du montant du séjour est dû",
            self::PENALTY_FIXED_AMOUNT => number_format(($this->penalty_value ?? 0) / 100, 0, ',', ' ') . " FCFA de pénalité sont dus",
            default                    => "des frais d'annulation s'appliquent",
        };

        return "Annulation gratuite {$delaiStr}. Au-delà, {$penaliteStr}.";
    }

    /**
     * Crée un snapshot JSON à figer dans la réservation
     */
    public function toSnapshot(): array
    {
        return [
            'id'                      => $this->id,
            'code'                    => $this->code,
            'name'                    => $this->name,
            'description'             => $this->description,
            'penalty_type'            => $this->penalty_type,
            'penalty_value'           => $this->penalty_value,
            'free_cancel_days_before' => $this->free_cancel_days_before,
            'free_cancel_time'        => $this->free_cancel_time,
            'summary'                 => $this->summaryText(),
            'captured_at'             => now()->toIso8601String(),
        ];
    }
}
