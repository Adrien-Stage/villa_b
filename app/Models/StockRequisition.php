<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande d'un département à l'économat.
 *
 * Cycle : en attente → validée (ou refusée) → livrée. Le déstockage n'a lieu
 * qu'à la livraison : valider n'engage que l'accord de l'économe, pas encore
 * la sortie physique des articles.
 */
class StockRequisition extends Model
{
    use HasFactory;

    public const DEPARTMENTS = [
        'hebergement'  => 'Hébergement',
        'housekeeping' => 'Housekeeping',
        'restaurant'   => 'Restaurant / Cuisine',
        'boutique'     => 'Boutique',
        'autre'        => 'Autre',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_APPROVED  => 'Validée',
        self::STATUS_REJECTED  => 'Refusée',
        self::STATUS_DELIVERED => 'Livrée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    /** Rôles habilités à demander, par département. */
    public const DEPARTMENT_ROLES = [
        'hebergement'  => ['reception', 'manager'],
        'housekeeping' => ['housekeeping_leader', 'housekeeping', 'manager'],
        'restaurant'   => ['restaurant_chief', 'manager'],
        'boutique'     => ['shop_manager', 'manager'],
        'autre'        => ['manager'],
    ];

    protected $fillable = [
        'number', 'department', 'status', 'purpose', 'review_notes',
        'requested_by', 'reviewed_by', 'reviewed_at', 'delivered_at', 'tenant_id',
    ];

    protected $casts = [
        'reviewed_at'  => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $requisition) {
            if (empty($requisition->number)) {
                $requisition->number = self::generateNumber();
            }
            if (empty($requisition->status)) {
                $requisition->status = self::STATUS_PENDING;
            }
        });
    }

    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $seq  = $last ? (int) substr($last->number, -4) + 1 : 1;

        return sprintf('DM-%d-%04d', $year, $seq);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(StockRequisitionLine::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // ── Règles de cycle ──────────────────────────────────────────────────────

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** La livraison n'est possible qu'une fois la demande validée. */
    public function canBeDelivered(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function departmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? $this->department;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Le stock permet-il de servir intégralement la demande ? */
    public function isFullyServiceable(): bool
    {
        return $this->lines()->with('item')->get()->every(
            fn (StockRequisitionLine $l) => $l->item
                && (float) $l->item->current_stock >= (float) $l->quantity_requested
        );
    }
}
