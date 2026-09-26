<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BookingCancellation : Historique, motif et décompte financier d'une annulation
 */
class BookingCancellation extends Model
{
    use HasFactory;

    public const REASONS = [
        'guest_request'     => 'Demande du client',
        'schedule_change'   => 'Changement de dates / report de voyage',
        'medical'           => 'Raison médicale ou urgence familiale',
        'force_majeure'     => 'Force majeure / intempéries / vol annulé',
        'no_payment'        => 'Défaut de paiement ou d\'acompte',
        'duplicate'         => 'Réservation en double ou erreur de saisie',
        'hotel_overbooking' => 'Indisponibilité hôtel / surréservation',
        'other'             => 'Autre motif',
    ];

    public const REFUND_METHODS = [
        'cash'          => 'Espèces (Caisse de réception)',
        'orange_money'  => 'Orange Money',
        'mtn_momo'      => 'MTN MoMo',
        'bank_transfer' => 'Virement bancaire',
        'credit_note'   => 'Avoir / Crédit client',
        'none'          => 'Aucun remboursement',
    ];

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'cancellation_number',
        'cancelled_at',
        'cancelled_by',
        'reason_code',
        'reason_description',
        'penalty_amount',
        'penalty_waived',
        'waived_by',
        'waive_reason',
        'deposit_paid',
        'deposit_retained',
        'refund_amount',
        'refund_method',
        'refund_status',
        'payment_id',
    ];

    protected $casts = [
        'cancelled_at'     => 'datetime',
        'penalty_amount'   => 'integer',
        'penalty_waived'   => 'boolean',
        'deposit_paid'     => 'integer',
        'deposit_retained' => 'integer',
        'refund_amount'    => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($cancellation) {
            if (empty($cancellation->cancellation_number)) {
                $cancellation->cancellation_number = self::generateCancellationNumber();
            }
            if (empty($cancellation->cancelled_at)) {
                $cancellation->cancelled_at = now();
            }
        });
    }

    public static function generateCancellationNumber(): string
    {
        $prefix = 'CAN';
        $year = now()->year;
        $pattern = sprintf('%s-%d-%%', $prefix, $year);

        $last = self::withoutGlobalScopes()
            ->where('cancellation_number', 'like', $pattern)
            ->orderBy('cancellation_number', 'desc')
            ->first();

        $sequence = 1;
        if ($last && preg_match('/-(\d+)$/', $last->cancellation_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $number = sprintf('%s-%d-%06d', $prefix, $year, $sequence);
            $sequence++;
        } while (self::withoutGlobalScopes()->where('cancellation_number', $number)->exists());

        return $number;
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason_code] ?? $this->reason_code;
    }

    public function refundMethodLabel(): string
    {
        return self::REFUND_METHODS[$this->refund_method] ?? ($this->refund_method ?: 'Non spécifié');
    }
}
