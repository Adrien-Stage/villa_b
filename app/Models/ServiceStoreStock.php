<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stock d'un article dans un dépôt de service. Montants en centimes FCFA. */
class ServiceStoreStock extends Model
{
    protected $fillable = ['service_store_id', 'stock_item_id', 'current_stock', 'average_cost'];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'average_cost'  => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function stockValue(): int
    {
        return (int) round((float) $this->current_stock * $this->average_cost);
    }
}
