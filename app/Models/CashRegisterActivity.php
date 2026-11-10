<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Opération effectuée par un tiers (ex. chef de réception) dans une session
 * de caisse ouverte par un autre collaborateur. Garantit la traçabilité
 * complète vis-à-vis du titulaire du tiroir et du contrôle comptable.
 */
class CashRegisterActivity extends Model
{
    public const ACTION_BOOKING_CREATE = 'booking_create';
    public const ACTION_BOOKING_CONFIRM = 'booking_confirm';
    public const ACTION_BOOKING_UPDATE = 'booking_update';
    public const ACTION_BOOKING_CHECKIN = 'booking_checkin';
    public const ACTION_BOOKING_CHECKOUT = 'booking_checkout';
    public const ACTION_BOOKING_CANCEL = 'booking_cancel';
    public const ACTION_FOLIO_ADD = 'folio_add';
    public const ACTION_FOLIO_REMOVE = 'folio_remove';
    public const ACTION_PAYMENT = 'payment';
    public const ACTION_INVOICE_PAYMENT = 'invoice_payment';
    public const ACTION_GROUP_CHECKIN = 'group_checkin';
    public const ACTION_GROUP_CHECKOUT = 'group_checkout';
    public const ACTION_GROUP_FOLIO_ADD = 'group_folio_add';
    public const ACTION_GROUP_PAYMENT = 'group_payment';
    public const ACTION_GROUP_ROOM_ADD = 'group_room_add';
    public const ACTION_GROUP_ROOM_REMOVE = 'group_room_remove';
    public const ACTION_POS_SALE = 'pos_sale';
    public const ACTION_DISBURSEMENT = 'disbursement';
    public const ACTION_BANQUET_CREATE = 'banquet_create';
    public const ACTION_BANQUET_UPDATE = 'banquet_update';
    public const ACTION_BANQUET_STATUS = 'banquet_status';
    public const ACTION_BANQUET_PAYMENT = 'banquet_payment';

    protected $fillable = [
        'tenant_id',
        'cash_register_session_id',
        'user_id',
        'action',
        'description',
        'amount',
        'payment_method',
        'subject_type',
        'subject_id',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Impact réel sur les espèces physiques du tiroir (en centimes).
     * Les paiements digitaux / virements / mises sur chambre valent 0 en espèces.
     */
    public function cashImpactCentimes(): int
    {
        if ($this->amount === null || $this->amount === 0) {
            return 0;
        }

        if ($this->action === self::ACTION_DISBURSEMENT) {
            return -abs((int) $this->amount);
        }

        if ($this->action === self::ACTION_BOOKING_CANCEL) {
            return $this->payment_method === 'cash' ? -abs((int) $this->amount) : 0;
        }

        if ($this->payment_method === 'cash') {
            return abs((int) $this->amount);
        }

        return 0;
    }

    public function isFinancialCashFlow(): bool
    {
        if ($this->amount === null || $this->amount === 0) {
            return false;
        }

        if ($this->action === self::ACTION_DISBURSEMENT) {
            return true;
        }

        if ($this->action === self::ACTION_FOLIO_ADD || $this->action === self::ACTION_FOLIO_REMOVE) {
            return false;
        }

        return $this->payment_method !== null && $this->payment_method !== 'room_charge';
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_BOOKING_CREATE => 'Nouvelle réservation',
            self::ACTION_BOOKING_CONFIRM => 'Confirmation web',
            self::ACTION_BOOKING_UPDATE => 'Modification réservation',
            self::ACTION_BOOKING_CHECKIN => 'Check-in',
            self::ACTION_BOOKING_CHECKOUT => 'Check-out',
            self::ACTION_BOOKING_CANCEL => 'Annulation',
            self::ACTION_FOLIO_ADD => 'Ajout prestation folio',
            self::ACTION_FOLIO_REMOVE => 'Suppression prestation folio',
            self::ACTION_PAYMENT => 'Encaissement séjour',
            self::ACTION_INVOICE_PAYMENT => 'Encaissement facture',
            self::ACTION_GROUP_CHECKIN => 'Check-in groupe',
            self::ACTION_GROUP_CHECKOUT => 'Check-out groupe',
            self::ACTION_GROUP_FOLIO_ADD => 'Prestation folio groupe',
            self::ACTION_GROUP_PAYMENT => 'Encaissement groupe',
            self::ACTION_GROUP_ROOM_ADD => 'Ajout chambre groupe',
            self::ACTION_GROUP_ROOM_REMOVE => 'Retrait chambre groupe',
            self::ACTION_POS_SALE => 'Vente POS Réception',
            self::ACTION_DISBURSEMENT => 'Sortie de caisse',
            self::ACTION_BANQUET_CREATE => 'Nouveau devis banquet',
            self::ACTION_BANQUET_UPDATE => 'Modification banquet',
            self::ACTION_BANQUET_STATUS => 'Statut banquet',
            self::ACTION_BANQUET_PAYMENT => 'Encaissement banquet',
            default => ucfirst(str_replace('_', ' ', (string) $this->action)),
        };
    }

    public static function methodLabel(?string $method): ?string
    {
        if ($method === null || $method === '') {
            return null;
        }

        return match ($method) {
            'cash' => 'Espèces',
            'orange_money' => 'Orange Money',
            'mtn_momo' => 'MTN MoMo',
            'mobile_money' => 'Mobile Money',
            'stripe', 'card' => 'Carte bancaire',
            'bank_transfer', 'transfer' => 'Virement bancaire',
            'room_charge' => 'Sur chambre',
            'credit_note' => 'Avoir',
            'check' => 'Chèque',
            'other' => 'Autre',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }
}
