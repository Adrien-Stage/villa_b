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

    /**
     * Comptes de stock qu'une catégorie peut porter. Le compte décide aussi de
     * la variation en contrepartie (voir Account::variationFor).
     */
    public const STOCK_ACCOUNTS = [
        '311000' => 'Marchandises — boissons',
        '312000' => 'Marchandises — boutique',
        '321000' => 'Matières premières — cuisine',
        '331000' => 'Fournitures d’entretien et petit équipement',
        '332000' => 'Fournitures d’économat',
    ];

    protected $fillable = ['name', 'icon', 'stock_account', 'sort_order', 'tenant_id'];

    protected $casts = ['sort_order' => 'integer'];

    /** Compte effectif : sans choix explicite, fournitures d'économat. */
    public function effectiveStockAccount(): string
    {
        return $this->stock_account ?: Account::STOCK_STORE;
    }

    public function stockAccountLabel(): string
    {
        $compte = $this->effectiveStockAccount();

        return $compte . ' — ' . (self::STOCK_ACCOUNTS[$compte] ?? $compte);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
