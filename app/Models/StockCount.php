<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Feuille d'inventaire physique du magasin central (Économat).
 *
 * Cycle : draft (comptage en cours) → closed (validé et écarts régularisés)
 * ou cancelled.
 */
class StockCount extends Model
{
    use HasFactory;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'En cours de comptage',
        self::STATUS_CLOSED    => 'Clôturé & Régularisé',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'reference',
        'stock_category_id',
        'status',
        'count_date',
        'notes',
        'total_theoretical_value',
        'total_counted_value',
        'variance_value',
        'loss_value',
        'surplus_value',
        'opened_by',
        'closed_by',
        'closed_at',
        'tenant_id',
    ];

    protected $casts = [
        'count_date'              => 'date',
        'total_theoretical_value' => 'integer',
        'total_counted_value'     => 'integer',
        'variance_value'          => 'integer',
        'loss_value'              => 'integer',
        'surplus_value'           => 'integer',
        'closed_at'               => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $count) {
            if (empty($count->reference)) {
                $count->reference = self::generateReference();
            }
            if (empty($count->count_date)) {
                $count->count_date = now()->toDateString();
            }
            if (empty($count->status)) {
                $count->status = self::STATUS_DRAFT;
            }
        });
    }

    public static function generateReference(): string
    {
        $prefix = 'INV';
        $year = now()->year;
        $pattern = sprintf('%s-%d-%%', $prefix, $year);

        $last = self::withoutGlobalScopes()
            ->where('reference', 'like', $pattern)
            ->orderBy('reference', 'desc')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->reference, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        do {
            $reference = sprintf('%s-%d-%04d', $prefix, $year, $seq);
            $seq++;
        } while (self::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class, 'stock_count_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Taux de progression du comptage (lignes renseignées / total).
     */
    public function progressPercentage(): int
    {
        $total = $this->lines()->count();
        if ($total === 0) {
            return 100;
        }

        $counted = $this->lines()->whereNotNull('counted_quantity')->count();

        return (int) round(($counted / $total) * 100);
    }
}
