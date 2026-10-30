<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne d'un bon de transfert : l'article, la quantité et son coût à la sortie du magasin central. */
class StockTransferLine extends Model
{
    use \App\Models\Concerns\SaisiEnConditionnement;

    protected $fillable = ['stock_transfer_id', 'stock_item_id', 'quantity', 'packaging_name', 'unit_cost', 'total_cost'];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_cost'  => 'integer',
        'total_cost' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
