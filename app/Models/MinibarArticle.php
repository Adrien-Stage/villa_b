<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Article du mini-bar : un article de l'économat (chocolat, chips, boisson…)
 * vendu au client au prix fixé ici, TTC, en centimes.
 */
class MinibarArticle extends Model
{
    protected $fillable = ['stock_item_id', 'name', 'price', 'is_active', 'sort_order'];

    protected $casts = [
        'price'      => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
