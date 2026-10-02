<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventaire d'un dépôt de service. Voir create_service_store_counts_tables. */
class ServiceStoreCount extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'En cours de comptage',
        self::STATUS_CLOSED    => 'Clôturé',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'reference', 'service_store_id', 'status', 'count_date', 'notes',
        'opened_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'count_date' => 'date',
        'closed_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $count) {
            // Numérotation annuelle : ID-2026-0001 (inventaire de dépôt).
            $annee = now()->year;
            $dernier = self::query()->where('reference', 'like', "ID-{$annee}-%")->count();
            $count->reference ??= sprintf('ID-%d-%04d', $annee, $dernier + 1);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ServiceStoreCountLine::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Valeur consommée : ce qui manque au comptage, au coût du dépôt. */
    public function consumptionValue(): int
    {
        return (int) $this->lines->sum(fn (ServiceStoreCountLine $l) => max(0, -$l->varianceValue()));
    }
}
