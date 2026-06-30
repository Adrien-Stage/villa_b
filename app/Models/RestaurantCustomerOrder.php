<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantCustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'created_by',
        'table_number',
        'booking_id',
        'folio_item_id',
        'customer_name',
        'customer_phone',
        'status',
        'payment_status',
        'payment_method',
        'total_amount',
        'amount_paid',
        'notes',
        'placed_at',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'amount_paid' => 'integer',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantCustomerOrderItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
