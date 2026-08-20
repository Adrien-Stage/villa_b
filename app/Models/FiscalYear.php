<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Exercice comptable. Au Cameroun comme dans l'espace OHADA, il coïncide
 * avec l'année civile : du 1ᵉʳ janvier au 31 décembre.
 */
class FiscalYear extends Model
{
    protected $fillable = [
        'label',
        'starts_on',
        'ends_on',
        'closed_at',
        'opening_posted_at',
    ];

    protected $casts = [
        'starts_on'         => 'date',
        'ends_on'           => 'date',
        'closed_at'         => 'datetime',
        'opening_posted_at' => 'datetime',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /** Les à-nouveaux ont-ils déjà été repris ? Une reprise ne se fait qu'une fois. */
    public function hasOpeningBalance(): bool
    {
        return $this->opening_posted_at !== null;
    }

    public function contains(CarbonInterface $date): bool
    {
        return $date->betweenIncluded($this->starts_on, $this->ends_on);
    }

    /** Exercice couvrant une date donnée. */
    public static function forDate(CarbonInterface $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    /**
     * Crée un exercice et ses douze périodes mensuelles.
     * Idempotent : rend l'exercice existant si l'année est déjà ouverte.
     */
    public static function openYear(int $year): self
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->startOfDay();

        $fiscalYear = static::firstOrCreate(
            ['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString()],
            ['label' => "Exercice {$year}"]
        );

        for ($month = 1; $month <= 12; $month++) {
            $periodStart = Carbon::create($year, $month, 1)->startOfDay();

            FiscalPeriod::firstOrCreate(
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'starts_on'      => $periodStart->toDateString(),
                ],
                ['ends_on' => $periodStart->copy()->endOfMonth()->toDateString()]
            );
        }

        return $fiscalYear->fresh('periods');
    }
}
