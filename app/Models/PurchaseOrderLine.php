<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id', 'stock_item_id',
        'quantity_ordered', 'quantity_received', 'unit_price',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price'        => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /** Reste à livrer sur cette ligne. */
    public function outstanding(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }

    public function lineTotal(): int
    {
        return (int) round((float) $this->quantity_ordered * $this->unit_price);
    }
}
