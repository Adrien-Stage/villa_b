<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'une demande d'achat interne.
 */
class PurchaseRequestLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'stock_item_id',
        'quantity_requested',
        'estimated_unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity_requested'   => 'decimal:3',
        'estimated_unit_price' => 'integer', // centimes FCFA
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function estimatedTotal(): int
    {
        return (int) round((float) $this->quantity_requested * $this->estimated_unit_price);
    }
}
