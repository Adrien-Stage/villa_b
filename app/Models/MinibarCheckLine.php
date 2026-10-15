<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinibarCheckLine extends Model
{
    protected $fillable = [
        'minibar_check_id', 'minibar_article_id', 'label', 'quantity_before', 'consumed',
        'unit_price', 'total_price', 'unit_cost', 'folio_item_id',
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'consumed'        => 'integer',
        'unit_price'      => 'integer',
        'total_price'     => 'integer',
        'unit_cost'       => 'integer',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(MinibarCheck::class, 'minibar_check_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(MinibarArticle::class, 'minibar_article_id');
    }
}
