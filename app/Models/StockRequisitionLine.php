<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequisitionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_requisition_id', 'stock_item_id',
        'quantity_requested', 'quantity_issued',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_issued'    => 'decimal:3',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    /** Le stock couvre-t-il la quantité demandée sur cette ligne ? */
    public function isServiceable(): bool
    {
        return $this->item && (float) $this->item->current_stock >= (float) $this->quantity_requested;
    }
}
