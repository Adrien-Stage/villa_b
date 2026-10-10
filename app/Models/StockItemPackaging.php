<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un conditionnement d'un article de l'économat : le paquet de 10 pièces, le
 * carton de 200. Il dit combien d'unités de l'article il contient (factor) et
 * combien d'unités restent encore fermées au magasin (closed_count).
 */
class StockItemPackaging extends Model
{
    protected $fillable = ['stock_item_id', 'name', 'factor', 'closed_count'];

    protected $casts = [
        'factor'       => 'decimal:3',
        'closed_count' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
