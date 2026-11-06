<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une feuille d'inventaire physique : on compte réellement le garde-manger et on
 * confronte le résultat au stock théorique tenu par les fiches techniques.
 */
class RestaurantStockCount extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    use HasFactory, AppartientAUnRestaurant;

    /** On ne compte que le stock d'un restaurant qui en tient un. */
    public const SERVICE_REQUIS = PointOfSale::SERVICE_STOCK;

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
        'point_of_sale_id',
        'reference',
        'status',
        'notes',
        'variance_value',
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
    ];

    protected $casts = [
        'variance_value' => 'integer',
        'submitted_at'   => 'datetime',
        'closed_at'      => 'datetime',
        'validated_at'   => 'datetime',
        'rejected_at'    => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RestaurantStockCountLine::class, 'restaurant_stock_count_id');
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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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
}
