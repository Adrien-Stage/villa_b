<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne d'inventaire de dépôt. Montants en centimes FCFA. */
class ServiceStoreCountLine extends Model
{
    protected $fillable = [
        'service_store_count_id', 'stock_item_id', 'theoretical_quantity', 'unit_cost',
        'counted_quantity', 'notes',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:3',
        'counted_quantity'     => 'decimal:3',
        'unit_cost'            => 'integer',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(ServiceStoreCount::class, 'service_store_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    /** Compté moins théorique : négatif quand le service a consommé. */
    public function varianceQuantity(): float
    {
        return $this->isCounted()
            ? round((float) $this->counted_quantity - (float) $this->theoretical_quantity, 3)
            : 0.0;
    }

    public function varianceValue(): int
    {
        return (int) round($this->varianceQuantity() * $this->unit_cost);
    }
}
