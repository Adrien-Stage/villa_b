<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal du stock des mini-bars : chaque entrée et chaque consommation. */
class MinibarMovement extends Model
{
    public const TYPE_RESTOCK     = 'restock';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_DEFECTIVE   = 'defective';

    public const SOURCE_RESTOCK   = 'restock';
    public const SOURCE_CHECK     = 'check';
    public const SOURCE_DEFECTIVE = 'defective';

    protected $fillable = [
        'room_id', 'minibar_article_id', 'stock_item_id', 'type', 'quantity', 'quantity_after',
        'unit_cost', 'stock_account', 'source_type', 'source_id', 'booking_id', 'user_id', 'notes', 'occurred_at',
    ];

    protected $casts = [
        'quantity'       => 'integer',
        'quantity_after' => 'integer',
        'unit_cost'      => 'integer',
        'occurred_at'    => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(MinibarArticle::class, 'minibar_article_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
