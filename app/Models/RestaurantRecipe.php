<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RestaurantRecipe : la fiche technique.
 *
 * Deux natures :
 *  - dish : la nomenclature d'un plat du menu. Sa vente sort les ingrédients du
 *    garde-manger.
 *  - prep : une préparation de base fabriquée en batch (sauce ndolé, fond,
 *    marinade). Elle alimente un article de garde-manger, que les fiches de plats
 *    consomment ensuite comme n'importe quel ingrédient.
 */
class RestaurantRecipe extends Model
{
    use HasFactory;

    const TYPE_DISH = 'dish';
    const TYPE_PREP = 'prep';

    public const TYPES = [
        self::TYPE_DISH => 'Plat',
        self::TYPE_PREP => 'Préparation de base',
    ];

    protected $fillable = [
        'name',
        'type',
        'restaurant_menu_item_id',
        'produces_pantry_item_id',
        'yield_quantity',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'yield_quantity' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RestaurantRecipeLine::class, 'restaurant_recipe_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuItem::class, 'restaurant_menu_item_id');
    }

    public function producedItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'produces_pantry_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDishes(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DISH);
    }

    public function scopePreparations(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_PREP);
    }

    public function isPreparation(): bool
    {
        return $this->type === self::TYPE_PREP;
    }

    /**
     * Rendement, protégé contre la division par zéro.
     */
    public function yield(): float
    {
        $yield = (float) $this->yield_quantity;

        return $yield > 0 ? $yield : 1.0;
    }

    /**
     * Coût matière de la totalité du rendement, en centimes FCFA.
     * Les préparations sont valorisées à leur coût moyen pondéré : leur propre
     * fiche a déjà été chiffrée au moment de leur production.
     */
    public function totalCost(): float
    {
        return $this->lines->sum(fn (RestaurantRecipeLine $line) => $line->cost());
    }

    /**
     * Coût matière d'une portion (plat) ou d'une unité produite (préparation).
     */
    public function unitCost(): float
    {
        return $this->totalCost() / $this->yield();
    }

    /**
     * Food cost : part du coût matière dans le prix de vente, en pourcentage.
     * Null si la fiche n'est pas rattachée à un plat vendu.
     */
    public function foodCostPercent(): ?float
    {
        $price = (int) ($this->menuItem?->price ?? 0);

        if ($price <= 0) {
            return null;
        }

        return $this->unitCost() / $price * 100;
    }

    /**
     * Marge brute d'une portion, en centimes FCFA.
     */
    public function margin(): ?float
    {
        $price = (int) ($this->menuItem?->price ?? 0);

        if ($price <= 0) {
            return null;
        }

        return $price - $this->unitCost();
    }
}
