<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un règlement de banquet — acompte ou solde —, encaissé à la caisse du restaurant. */
class RestaurantBanquetPayment extends Model
{
    public const ACOMPTE = 'acompte';

    public const SOLDE = 'solde';

    protected $fillable = [
        'restaurant_banquet_id', 'kind', 'amount', 'payment_method', 'cash_register_session_id', 'paid_by', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];

    public function banquet(): BelongsTo
    {
        return $this->belongsTo(RestaurantBanquet::class, 'restaurant_banquet_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
