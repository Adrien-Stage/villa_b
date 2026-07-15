<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de fiche technique : un ingrédient et sa quantité.
 */
class RestaurantRecipeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_recipe_id',
        'restaurant_pantry_item_id',
        'quantity',
        'waste_percent',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'waste_percent' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(RestaurantRecipe::class, 'restaurant_recipe_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }

    /**
     * Quantité réellement sortie du stock pour la totalité du rendement : la
     * quantité nette de la recette, majorée de la perte au parage.
     */
    public function grossQuantity(): float
    {
        $net = (float) $this->quantity;
        $waste = (float) $this->waste_percent;

        if ($waste <= 0 || $waste >= 100) {
            return $net;
        }

        return $net / (1 - $waste / 100);
    }

    /**
     * Coût de la ligne pour la totalité du rendement, en centimes FCFA.
     */
    public function cost(): float
    {
        return $this->grossQuantity() * (float) ($this->item?->average_cost ?? 0);
    }
}
