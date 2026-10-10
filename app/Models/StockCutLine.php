<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une portion d'un bon de découpe : ce qui entre au garde-manger, et sa part de valeur. */
class StockCutLine extends Model
{
    protected $fillable = [
        'stock_cut_id', 'restaurant_pantry_item_id', 'label', 'quantity', 'pantry_quantity', 'value', 'unit_cost',
    ];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'pantry_quantity' => 'decimal:3',
        'value'           => 'integer',
        'unit_cost'       => 'decimal:4',
    ];

    public function cut(): BelongsTo
    {
        return $this->belongsTo(StockCut::class, 'stock_cut_id');
    }

    public function pantryItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }
}
