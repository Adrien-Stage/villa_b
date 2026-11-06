<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventaire d'un dépôt de service. Voir create_service_store_counts_tables. */
class ServiceStoreCount extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT     => 'En cours de comptage',
        self::STATUS_SUBMITTED => 'Soumis au RAF pour validation',
        self::STATUS_CLOSED    => 'Validé & Régularisé',
        self::STATUS_REJECTED  => 'Rejeté par le RAF',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'reference', 'service_store_id', 'status', 'count_date', 'notes',
        'opened_by', 'submitted_by', 'submitted_at',
        'closed_by', 'closed_at', 'validated_by', 'validated_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'count_date'   => 'date',
        'submitted_at' => 'datetime',
        'closed_at'    => 'datetime',
        'validated_at' => 'datetime',
        'rejected_at'  => 'datetime',
        'cancelled_at' => 'datetime',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeModified(): bool
    {
        if (!$this->isDraft() && !$this->isRejected()) {
            return false;
        }

        if ($this->created_at && $this->created_at->diffInHours(now()) > 48) {
            return false;
        }

        return true;
    }

    public function canBeCancelledByRaf(): bool
    {
        if (!$this->isClosed()) {
            return false;
        }

        $dateValidation = $this->validated_at ?? $this->closed_at;
        if (!$dateValidation) {
            return false;
        }

        return $dateValidation->diffInHours(now()) <= 48;
    }

    public function cancellationDeadline(): ?\Carbon\Carbon
    {
        $dateValidation = $this->validated_at ?? $this->closed_at;
        return $dateValidation ? $dateValidation->copy()->addHours(48) : null;
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
