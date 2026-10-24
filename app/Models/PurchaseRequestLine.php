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
        'quantity_approved',
        'estimated_unit_price',
        'notes',
    ];

    protected $casts = [
        'quantity_requested'   => 'decimal:3',
        'quantity_approved'    => 'decimal:3',
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

    public function stockItem(): BelongsTo
    {
        return $this->item();
    }

    /** La quantité qui sera commandée : celle de l'approbation, sinon celle demandée. */
    public function quantiteRetenue(): float
    {
        return (float) ($this->quantity_approved ?? $this->quantity_requested);
    }

    /** L'approbation a changé la quantité demandée. */
    public function estAjustee(): bool
    {
        return $this->quantity_approved !== null
            && abs((float) $this->quantity_approved - (float) $this->quantity_requested) > 0.0005;
    }

    public function estimatedTotal(): int
    {
        return (int) round($this->quantiteRetenue() * $this->estimated_unit_price);
    }
}
