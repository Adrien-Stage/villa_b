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
        'reference',
        'department',
        'stock_category_id',
        'storage_zone_id',
        'status',
        'count_date',
        'notes',
        'total_theoretical_value',
        'total_counted_value',
        'variance_value',
        'loss_value',
        'surplus_value',
        'opened_by',
        'submitted_by',
        'submitted_at',
        'closed_by',
        'closed_at',
        'validated_by',
        'validated_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'tenant_id',
    ];

    protected $casts = [
        'count_date'              => 'date',
        'total_theoretical_value' => 'integer',
        'total_counted_value'     => 'integer',
        'variance_value'          => 'integer',
        'loss_value'              => 'integer',
        'surplus_value'           => 'integer',
        'submitted_at'            => 'datetime',
        'closed_at'               => 'datetime',
        'validated_at'            => 'datetime',
        'rejected_at'             => 'datetime',
        'cancelled_at'            => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $count) {
            if (empty($count->reference)) {
                $count->reference = self::generateReference();
            }
            if (empty($count->department)) {
                $count->department = 'economat';
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

    /** La zone de rangement comptée, quand l'inventaire s'y limite. */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(StorageZone::class, 'storage_zone_id');
    }

    /** Ce que l'inventaire compte : « Chambre froide 1 · Viandes », ou tout le magasin. */
    public function perimetre(string $tout = 'Tout le magasin'): string
    {
        return collect([$this->zone?->name, $this->category?->name])->filter()->implode(' · ') ?: $tout;
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

    /**
     * L'inventaire en cours de comptage ou soumis, s'il y en a un. Tant qu'il existe,
     * le magasin est gelé : aucun mouvement de stock ne doit fausser le
     * théorique relevé à l'ouverture.
     */
    public static function inProgress(): ?self
    {
        return self::query()->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SUBMITTED])->latest('id')->first();
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
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

    /**
     * Un inventaire peut être modifié s'il est encore en brouillon ou rejeté
     * ET s'il a été ouvert il y a moins de 48 heures.
     */
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

    /**
     * Seul le RAF peut annuler un inventaire validé, et strictement dans les 48h suivant la validation.
     */
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

    /** Échéance limite d'annulation (48h après validation) */
    public function cancellationDeadline(): ?\Carbon\Carbon
    {
        $dateValidation = $this->validated_at ?? $this->closed_at;
        return $dateValidation ? $dateValidation->copy()->addHours(48) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function departmentLabel(): string
    {
        if (empty($this->department) || $this->department === 'economat') {
            return 'Économat (Magasin central)';
        }

        return StockRequisition::DEPARTMENTS[$this->department] ?? ucfirst($this->department);
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
