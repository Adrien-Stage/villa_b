<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne d'un bon de sortie hors établissement : l'article, la quantité et son coût à la sortie. */
class ExternalIssueLine extends Model
{
    protected $fillable = ['external_issue_id', 'stock_item_id', 'quantity', 'unit_cost', 'total_cost', 'notes'];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_cost'  => 'integer',
        'total_cost' => 'integer',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(ExternalIssue::class, 'external_issue_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
