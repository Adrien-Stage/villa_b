<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantStockCountLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_stock_count_id',
        'restaurant_pantry_item_id',
        'theoretical_quantity',
        'counted_quantity',
        'variance_quantity',
        'unit_cost',
        'variance_value',
        'notes',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:3',
        'counted_quantity' => 'decimal:3',
        'variance_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'variance_value' => 'integer',
    ];

    public function count(): BelongsTo
    {
        return $this->belongsTo(RestaurantStockCount::class, 'restaurant_stock_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }
}
