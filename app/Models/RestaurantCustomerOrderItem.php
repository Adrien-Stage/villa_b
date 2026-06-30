<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantCustomerOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_customer_order_id',
        'menu_item_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
        'special_requests',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'total_price' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantCustomerOrder::class, 'restaurant_customer_order_id');
    }
}

