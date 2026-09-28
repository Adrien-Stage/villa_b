<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de réception contradictoire (Goods Receipt).
 *
 * Établi lors de la livraison physique par le fournisseur.
 * Enregistre les quantités commandées, livrées, acceptées (qui entrent en stock)
 * et rejetées (avaries, non-conformités, casse).
 */
class GoodsReceipt extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_RECEIVED  => 'Réceptionné & Conforme',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    protected $fillable = [
        'number',
        'purchase_order_id',
        'supplier_id',
        'delivery_note_number',
        'received_at',
        'status',
        'total_amount',
        'notes',
        'received_by',
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
            if (empty($receipt->received_at)) {
                $receipt->received_at = now();
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

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
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
}
