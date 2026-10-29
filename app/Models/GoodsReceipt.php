<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de réception contradictoire (Goods Receipt).
 *
 * Ouvert « en attente » dès que le bon de commande part chez le fournisseur,
 * avec le reste attendu de chaque ligne ; rempli par l'économat à la
 * livraison physique. Enregistre alors les quantités livrées, acceptées (qui
 * entrent en stock) et rejetées (avaries, non-conformités, casse), et si la
 * livraison est partielle ou complète.
 */
class GoodsReceipt extends Model
{
    use HasFactory;

    /** Ouvert à l'envoi du bon de commande : la livraison est attendue. */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING   => 'En attente de livraison',
        self::STATUS_RECEIVED  => 'Réceptionné & Conforme',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    /** Partielle : le reste du bon de commande reste attendu. Complète : le bon est soldé. */
    public const DELIVERY_PARTIAL  = 'partielle';
    public const DELIVERY_COMPLETE = 'complete';

    public const DELIVERIES = [
        self::DELIVERY_COMPLETE => 'Livraison complète',
        self::DELIVERY_PARTIAL  => 'Livraison partielle',
    ];

    protected $fillable = [
        'number',
        'purchase_order_id',
        'supplier_id',
        'delivery_note_number',
        'received_at',
        'status',
        'delivery_type',
        'total_amount',
        'notes',
        'received_by',
        'receiver_signature',
        'tenant_id',
    ];

    protected $casts = [
        'received_at'  => 'datetime',
        'total_amount' => 'integer', // centimes FCFA
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $receipt) {
            if (empty($receipt->number)) {
                $receipt->number = self::generateNumber();
            }
            if (empty($receipt->status)) {
                $receipt->status = self::STATUS_RECEIVED;
            }
            // Un bon en attente n'est encore ni reçu ni signé.
            if ($receipt->status === self::STATUS_PENDING) {
                return;
            }
            if (empty($receipt->received_at)) {
                $receipt->received_at = now();
            }
            if (empty($receipt->receiver_signature) && \Illuminate\Support\Facades\Auth::check()) {
                $user = \Illuminate\Support\Facades\Auth::user();
                $receipt->receiver_signature = $user->signatureName();
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'BR';
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** Les bordereaux de livraison joints : plusieurs par livraison. */
    public function attachments(): HasMany
    {
        return $this->hasMany(GoodsReceiptAttachment::class)->latest('id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function estEnAttente(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function scopeEnAttente(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function deliveryLabel(): ?string
    {
        return self::DELIVERIES[$this->delivery_type] ?? null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusLabel();
    }

    public function hasRejections(): bool
    {
        return $this->lines->contains(fn (GoodsReceiptLine $l) => (float) $l->quantity_rejected > 0);
    }

    public function totalRejectedQuantity(): float
    {
        return (float) $this->lines->sum('quantity_rejected');
    }

    public function totalAcceptedQuantity(): float
    {
        return (float) $this->lines->sum('quantity_accepted');
    }

    public function recalculateTotal(): void
    {
        $total = $this->lines()->sum('total_cost');
        $this->update(['total_amount' => (int) $total]);
    }

    /**
     * Nom du signataire économe pour l'affichage de la signature manuscrite Qwigley.
     */
    public function receiverSignature(): ?string
    {
        return $this->receiver_signature
            ?: ($this->receivedBy ? $this->receivedBy->signatureName() : null);
    }
}
