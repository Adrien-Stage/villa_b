<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantPantryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_pantry_category_id',
        'name',
        'unit',
        'current_stock',
        'min_stock',
        'cost_price',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'cost_price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryCategory::class, 'restaurant_pantry_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RestaurantPantryMovement::class, 'restaurant_pantry_item_id');
    }
}

