<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantBarBottle extends Model
{
    protected $fillable = [
        'service_store_id', 'stock_item_id', 'capacity_ml', 'remaining_ml',
        'opening_cost', 'opened_by', 'opening_key',
    ];

    protected $casts = [
        'capacity_ml' => 'integer',
        'remaining_ml' => 'integer',
        'opening_cost' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** Valeur restante en centimes FCFA, arrondie à partir du coût de la bouteille. */
    public function valueAt(int $volume): int
    {
        return (int) round($this->opening_cost * $volume / $this->capacity_ml);
    }
}
