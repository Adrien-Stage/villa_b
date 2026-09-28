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

    public function stockItem(): BelongsTo
    {
        return $this->item();
    }

    /** Le stock couvre-t-il la quantité demandée sur cette ligne ? */
    public function isServiceable(): bool
    {
        return $this->item && (float) $this->item->current_stock >= (float) $this->quantity_requested;
    }

    /** Coût unitaire moyen pondéré au centime près. */
    public function unitCost(): int
    {
        return (int) ($this->item?->average_cost ?? 0);
    }

    /** Montant total demandé (centimes FCFA). */
    public function totalRequestedCost(): int
    {
        return (int) round((float) $this->quantity_requested * $this->unitCost());
    }

    /** Montant total servi (centimes FCFA). */
    public function totalIssuedCost(): int
    {
        return (int) round((float) $this->quantity_issued * $this->unitCost());
    }
}
