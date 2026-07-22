<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie d'articles de l'économat (produits d'entretien, linge, épicerie,
 * consommables techniques…).
 */
class StockCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon', 'sort_order', 'tenant_id'];

    protected $casts = ['sort_order' => 'integer'];

    public function items(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
