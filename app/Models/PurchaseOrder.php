<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de commande adressé à un fournisseur.
 *
 * Cycle : brouillon → envoyé (par email) → réceptionné, éventuellement en
 * plusieurs fois si le fournisseur livre partiellement.
 */
class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT              = 'draft';
    public const STATUS_SENT               = 'sent';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED           = 'received';
    public const STATUS_CANCELLED          = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT              => 'Brouillon',
        self::STATUS_SENT               => 'Envoyé',
        self::STATUS_PARTIALLY_RECEIVED => 'Partiellement reçu',
        self::STATUS_RECEIVED           => 'Réceptionné',
        self::STATUS_CANCELLED          => 'Annulé',
    ];

    protected $fillable = [
        'number', 'supplier_id', 'status', 'expected_at', 'sent_at', 'received_at',
        'sent_to_email', 'send_error', 'total_amount', 'notes',
        'created_by', 'received_by', 'tenant_id',
    ];

    protected $casts = [
        'expected_at'  => 'date',
        'sent_at'      => 'datetime',
        'received_at'  => 'datetime',
        'total_amount' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $order) {
            if (empty($order->number)) {
                $order->number = self::generateNumber();
            }
            // Le défaut de la migration n'est pas reflété sur l'instance en
            // mémoire : on le pose ici pour que isEditable()/canBeSent()
            // fonctionnent sans refresh après création.
            if (empty($order->status)) {
                $order->status = self::STATUS_DRAFT;
            }
        });
    }

    public static function generateNumber(): string
    {
        $year = now()->year;
        $last = self::whereYear('created_at', $year)->orderBy('id', 'desc')->first();
        $seq  = $last ? (int) substr($last->number, -4) + 1 : 1;

        return sprintf('BC-%d-%04d', $year, $seq);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_PARTIALLY_RECEIVED]);
    }

    // ── Règles de cycle ──────────────────────────────────────────────────────

    /** Un bon ne se modifie plus une fois parti chez le fournisseur. */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSent(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->lines()->exists();
    }

    /** On ne réceptionne que ce qui a été commandé et pas encore soldé. */
    public function canBeReceived(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_PARTIALLY_RECEIVED], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Recalcule le total à partir des lignes. */
    public function recalculateTotal(): void
    {
        $total = $this->lines()->get()
            ->sum(fn (PurchaseOrderLine $l) => (int) round((float) $l->quantity_ordered * $l->unit_price));

        $this->update(['total_amount' => (int) $total]);
    }

    /**
     * Statut déduit de l'avancement des réceptions : soldé si toutes les
     * lignes sont servies, partiel dès qu'une quantité est arrivée.
     */
    public function refreshReceptionStatus(): void
    {
        $lines = $this->lines()->get();

        $fullyReceived = $lines->every(
            fn (PurchaseOrderLine $l) => (float) $l->quantity_received >= (float) $l->quantity_ordered
        );
        $anyReceived = $lines->contains(fn (PurchaseOrderLine $l) => (float) $l->quantity_received > 0);

        $this->update([
            'status'      => $fullyReceived ? self::STATUS_RECEIVED
                            : ($anyReceived ? self::STATUS_PARTIALLY_RECEIVED : $this->status),
            'received_at' => $fullyReceived ? now() : $this->received_at,
        ]);
    }
}
