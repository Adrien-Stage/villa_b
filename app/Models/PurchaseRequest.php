<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Demande d'achat interne (Purchase Request).
 *
 * Émise par un responsable de département (restaurant, cuisine, bar, housekeeping, économe...)
 * pour exprimer un besoin d'approvisionnement externe.
 *
 * Le circuit : le contrôleur de gestion l'approuve — ajustant au besoin les
 * quantités — ou la refuse ; le responsable des achats en tire le(s) bon(s)
 * de commande et les envoie aux fournisseurs. Quand plus aucun de ses bons
 * n'attend d'envoi, la demande est archivée.
 */
class PurchaseRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING   => 'En attente',
        self::STATUS_APPROVED  => 'Approuvée',
        self::STATUS_REJECTED  => 'Refusée',
        self::STATUS_CONVERTED => 'Convertie en bon(s)',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW    => 'Basse',
        self::PRIORITY_NORMAL => 'Normale',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    public const DEPARTMENTS = [
        'economat'     => 'Économat central',
        'cuisine'      => 'Cuisine principale',
        'restaurant'   => 'Restaurant',
        'bar'          => 'Bar / Lounge',
        'housekeeping' => 'Housekeeping / Hébergement',
        'maintenance'  => 'Maintenance technique',
        'direction'    => 'Direction générale',
        'autre'        => 'Autre service',
    ];

    protected $fillable = [
        'number',
        'department',
        'priority',
        'status',
        'purpose',
        'rejection_reason',
        'review_notes',
        'total_estimated_amount',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'archived_at',
        'tenant_id',
    ];

    protected $casts = [
        'reviewed_at'            => 'datetime',
        'archived_at'            => 'datetime',
        'total_estimated_amount' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $request) {
            if (empty($request->number)) {
                $request->number = self::generateNumber();
            }
            if (empty($request->status)) {
                $request->status = self::STATUS_PENDING;
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'DA';
        $year = now()->year;
        $pattern = sprintf('%s-%d-%%', $prefix, $year);

        $last = self::withoutGlobalScopes()
            ->where('number', 'like', $pattern)
            ->orderBy('number', 'desc')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->number, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        do {
            $number = sprintf('%s-%d-%04d', $prefix, $year, $seq);
            $seq++;
        } while (self::withoutGlobalScopes()->where('number', $number)->exists());

        return $number;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Les demandes encore en circuit : à approuver, à commander, ou dont un bon attend l'envoi. */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereNull('archived_at')
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_CONVERTED]);
    }

    /** Les demandes sorties du circuit : commandées, refusées ou annulées. */
    public function scopeArchivees(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('archived_at')
            ->orWhereIn('status', [self::STATUS_REJECTED, self::STATUS_CANCELLED]));
    }

    // ── Helpers de cycle ─────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isConverted(): bool
    {
        return $this->status === self::STATUS_CONVERTED;
    }

    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeConverted(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_CONVERTED && $this->archived_at !== null) {
            return 'Commandée';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function estArchivee(): bool
    {
        return $this->archived_at !== null
            || in_array($this->status, [self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
    }

    /** Une ligne au moins dont l'approbation a changé la quantité demandée. */
    public function aEteAjustee(): bool
    {
        return $this->lines->contains(fn (PurchaseRequestLine $l) => $l->estAjustee());
    }

    /**
     * Archive la demande convertie quand plus aucun de ses bons n'attend
     * d'envoi : ils sont partis chez les fournisseurs, ou ont été annulés.
     */
    public function archiverSiCommandee(): void
    {
        if ($this->status !== self::STATUS_CONVERTED || $this->archived_at !== null) {
            return;
        }

        if ($this->purchaseOrders()->where('status', PurchaseOrder::STATUS_DRAFT)->exists()) {
            return;
        }

        $this->update(['archived_at' => now()]);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function departmentLabel(): string
    {
        return self::DEPARTMENTS[$this->department] ?? ucfirst($this->department);
    }

    public function recalculateTotal(): void
    {
        $total = $this->lines()->get()
            ->sum(fn (PurchaseRequestLine $l) => $l->estimatedTotal());

        $this->update(['total_estimated_amount' => (int) $total]);
    }
}
