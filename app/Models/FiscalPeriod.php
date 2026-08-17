<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Période mensuelle d'un exercice.
 *
 * Le verrouillage matérialise l'Article 22 de l'Acte Uniforme : passée la
 * date limite, une période n'accepte plus aucune écriture et toute correction
 * passe par une contre-passation datée d'une période encore ouverte.
 */
class FiscalPeriod extends Model
{
    /** Délai maximal de verrouillage après la fin de la période (Art. 22). */
    public const LOCK_DEADLINE_MONTHS = 1;

    protected $fillable = [
        'fiscal_year_id',
        'starts_on',
        'ends_on',
        'locked_at',
        'locked_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
        'locked_at' => 'datetime',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->fiscalYear?->isClosed();
    }

    /** Date au-delà de laquelle la période aurait dû être verrouillée. */
    public function lockDeadline(): Carbon
    {
        return $this->ends_on->copy()->addMonthsNoOverflow(self::LOCK_DEADLINE_MONTHS);
    }

    /** La période est ouverte alors que son délai de verrouillage est dépassé. */
    public function isOverdue(): bool
    {
        return !$this->isLocked() && now()->greaterThan($this->lockDeadline());
    }

    /** Période couvrant une date donnée. */
    public static function forDate(CarbonInterface $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    public function label(): string
    {
        return ucfirst($this->starts_on->translatedFormat('F Y'));
    }
}
