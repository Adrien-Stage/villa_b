<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    use HasFactory;

    protected $table = 'shop_orders';

    protected $fillable = [
        'order_number',
        'booking_id',
        'folio_item_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'total_items',
        'subtotal',
        'tax_amount',
        'total_amount',
        'payment_status',
        'payment_method',
        'paid_at',
        'created_by',
        'cash_register_session_id',
        'notes',
    ];

    protected $casts = [
        'total_items' => 'integer',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
