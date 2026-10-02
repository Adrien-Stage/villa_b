<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal des mouvements d'un dépôt de service : source de vérité de son stock. */
class ServiceStoreMovement extends Model
{
    public const TYPE_IN         = 'in';
    public const TYPE_OUT        = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_REQUISITION = 'requisition';
    public const SOURCE_STOCK_COUNT = 'stock_count';

    public const TYPES = [
        self::TYPE_IN         => 'Entrée',
        self::TYPE_OUT        => 'Sortie',
        self::TYPE_ADJUSTMENT => 'Inventaire',
    ];

    protected $fillable = [
        'service_store_id', 'stock_item_id', 'type', 'quantity', 'stock_after', 'unit_cost',
        'stock_account', 'source_type', 'source_id', 'reason', 'user_id', 'occurred_at',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'stock_after' => 'decimal:3',
        'unit_cost'   => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
