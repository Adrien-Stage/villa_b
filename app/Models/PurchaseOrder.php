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
 * Cycle : brouillon → transmis (par email, ou remis par un autre moyen) →
 * réceptionné, éventuellement en plusieurs fois si le fournisseur livre
 * partiellement. Un bon de régularisation naît déjà réceptionné : il
 * documente une livraison arrivée sans commande préalable.
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

    public const TRANSMISSION_EMAIL = 'email';
    public const TRANSMISSION_REGULARISATION = 'regularisation';

    /** Les moyens de transmettre un bon sans email, au choix de l'économe. */
    public const TRANSMISSIONS_MANUELLES = [
        'main_propre' => 'Remis en main propre',
        'telephone'   => 'Dicté par téléphone',
        'whatsapp'    => 'Envoyé par WhatsApp ou SMS',
        'autre'       => 'Autre moyen',
    ];

    /** Pourquoi une marchandise est entrée sans bon de commande. */
    public const MOTIFS_REGULARISATION = [
        'achat_comptant'     => 'Achat au comptant (marché, boutique)',
        'livraison_imprevue' => 'Livraison arrivée sans bon de commande',
        'urgence'            => "Achat d'urgence",
    ];

    protected $fillable = [
        'number', 'supplier_id', 'purchase_request_id', 'status', 'expected_at', 'sent_at', 'received_at',
        'sent_to_email', 'transmission', 'send_error', 'total_amount', 'notes',
        'created_by', 'received_by', 'tenant_id',
        'issuer_signature',
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
            if (empty($order->issuer_signature)) {
                $user = auth()->user() ?? ($order->created_by ? User::find($order->created_by) : null);
                if ($user) {
                    $order->issuer_signature = method_exists($user, 'signatureName')
                        ? $user->signatureName()
                        : User::extractSignatureName($user->name);
                }
            }
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'BC';
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function invoicedAmount(): int
    {
        return (int) $this->invoices()->sum('amount_ttc');
    }

    /** Valeur acceptée des réceptions non annulées : ce qui peut être facturé. */
    public function receivedAmount(): int
    {
        return (int) $this->receipts()
            ->where('status', GoodsReceipt::STATUS_RECEIVED)
            ->sum('total_amount');
    }

    /** Reçu et pas encore facturé : ce qu'une facture peut porter sans écart. */
    public function uninvoicedReceivedAmount(): int
    {
        return max(0, $this->receivedAmount() - $this->invoicedAmount());
    }

    public function invoicingStatus(): string
    {
        $invoiced = $this->invoicedAmount();
        if ($invoiced <= 0) {
            return 'not_invoiced';
        }
        if ($invoiced >= $this->total_amount) {
            return 'fully_invoiced';
        }
        return 'partially_invoiced';
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

    /** Établi après coup pour une marchandise reçue sans commande. */
    public function isRegularisation(): bool
    {
        return $this->transmission === self::TRANSMISSION_REGULARISATION;
    }

    /** Comment le bon est parvenu au fournisseur, en clair. */
    public function transmissionLabel(): ?string
    {
        return match ($this->transmission) {
            null                              => null,
            self::TRANSMISSION_EMAIL          => 'Envoyé par email',
            self::TRANSMISSION_REGULARISATION => 'Régularisation d\'une réception directe',
            default                           => self::TRANSMISSIONS_MANUELLES[$this->transmission] ?? $this->transmission,
        };
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
     * lignes sont servies, partiel dès qu'une quantité est arrivée. Après
     * l'annulation de toutes ses réceptions, le bon redevient « envoyé » :
     * il attend encore sa livraison.
     */
    public function refreshReceptionStatus(): void
    {
        $lines = $this->lines()->get();

        $fullyReceived = $lines->every(
            fn (PurchaseOrderLine $l) => (float) $l->quantity_received >= (float) $l->quantity_ordered
        );
        $anyReceived = $lines->contains(fn (PurchaseOrderLine $l) => (float) $l->quantity_received > 0);

        $wasReceiving = in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_PARTIALLY_RECEIVED], true);

        $status = match (true) {
            $fullyReceived => self::STATUS_RECEIVED,
            $anyReceived   => self::STATUS_PARTIALLY_RECEIVED,
            $wasReceiving  => self::STATUS_SENT,
            default        => $this->status,
        };

        $this->update([
            'status'      => $status,
            // La date de réception complète ne vaut que pour un bon soldé.
            'received_at' => $fullyReceived ? ($this->received_at ?? now()) : null,
        ]);
    }

    /**
     * Nom extrait pour la signature automatique manuscrite de l'émetteur.
     */
    public function issuerSignature(): ?string
    {
        if (!empty($this->issuer_signature)) {
            return $this->issuer_signature;
        }

        if ($this->createdBy) {
            return method_exists($this->createdBy, 'signatureName')
                ? $this->createdBy->signatureName()
                : User::extractSignatureName($this->createdBy->name);
        }

        return null;
    }
}
