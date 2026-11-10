<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un règlement de banquet — acompte ou solde —, encaissé au restaurant ou à la réception. */
class RestaurantBanquetPayment extends Model
{
    public const ACOMPTE = 'acompte';

    public const SOLDE = 'solde';

    protected $fillable = [
        'restaurant_banquet_id', 'reference', 'kind', 'amount', 'payment_method', 'cash_register_session_id', 'paid_by', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function banquet(): BelongsTo
    {
        return $this->belongsTo(RestaurantBanquet::class, 'restaurant_banquet_id');
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function libelleKind(): string
    {
        return $this->kind === self::SOLDE ? 'Solde' : 'Acompte';
    }

    public function receiptReference(): string
    {
        return $this->reference ?: sprintf('BQT-PAY-%05d', $this->id);
    }

    public function methodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Espèces',
            'mobile_money' => 'Mobile money',
            'card' => 'Carte bancaire',
            'transfer' => 'Virement bancaire',
            'other' => 'Autre',
            default => ucfirst(str_replace('_', ' ', (string) $this->payment_method)),
        };
    }
}
