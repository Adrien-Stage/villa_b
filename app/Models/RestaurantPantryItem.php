<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantPantryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_pantry_category_id',
        'name',
        'unit',
        'is_prepared',
        'purchase_unit',
        'purchase_conversion',
        'purchase_price',
        'current_stock',
        'min_stock',
        'cost_price',
        'average_cost',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'min_stock' => 'decimal:3',
        'purchase_conversion' => 'decimal:3',
        'purchase_price' => 'integer',
        'cost_price' => 'integer',
        'average_cost' => 'decimal:4',
        'is_prepared' => 'boolean',
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

    /**
     * La fiche technique qui fabrique cet article — uniquement pour une préparation.
     */
    public function recipe(): HasOne
    {
        return $this->hasOne(RestaurantRecipe::class, 'produces_pantry_item_id');
    }

    /**
     * Les lignes de fiche technique qui consomment cet article.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RestaurantRecipeLine::class, 'restaurant_pantry_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock');
    }

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->min_stock;
    }

    /**
     * Nombre d'unités de stock contenues dans une unité d'achat : un sac de 50 kg
     * d'arachide, pour un article suivi en grammes, vaut 50 000.
     */
    public function conversion(): float
    {
        $conversion = (float) $this->purchase_conversion;

        return $conversion > 0 ? $conversion : 1.0;
    }

    /**
     * Valeur du stock actuel, en centimes FCFA.
     */
    public function stockValue(): float
    {
        return (float) $this->current_stock * (float) $this->average_cost;
    }
}
