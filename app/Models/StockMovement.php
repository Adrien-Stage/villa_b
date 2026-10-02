<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal des variations de stock : source de vérité du module. Chaque entrée,
 * sortie ou ajustement y laisse une trace avec son document d'origine, ce qui
 * permet d'auditer un écart d'inventaire.
 */
class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_IN         = 'in';
    public const TYPE_OUT        = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const SOURCE_PURCHASE_ORDER = 'purchase_order';
    public const SOURCE_GOODS_RECEIPT  = 'goods_receipt';
    public const SOURCE_REQUISITION    = 'requisition';
    public const SOURCE_MANUAL         = 'manual';
    public const SOURCE_STOCK_COUNT    = 'stock_count';
    public const SOURCE_OPENING        = 'opening';

    public const SOURCES = [
        self::SOURCE_PURCHASE_ORDER => 'Bon de commande fournisseur',
        self::SOURCE_GOODS_RECEIPT  => 'Bon de réception fournisseur',
        self::SOURCE_REQUISITION    => 'Demande interne',
        self::SOURCE_MANUAL         => 'Saisie manuelle',
        self::SOURCE_STOCK_COUNT    => 'Inventaire physique',
        self::SOURCE_OPENING        => 'Reprise du stock initial',
    ];

    public const TYPES = [
        self::TYPE_IN         => 'Entrée',
        self::TYPE_OUT        => 'Sortie',
        self::TYPE_ADJUSTMENT => 'Ajustement',
    ];

    protected $fillable = [
        'stock_item_id', 'type', 'quantity', 'stock_after', 'unit_cost', 'stock_account',
        'source_type', 'source_id', 'reason', 'user_id', 'occurred_at', 'tenant_id',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'stock_after' => 'decimal:3',
        'unit_cost'   => 'integer',
        'occurred_at' => 'datetime',
    ];

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
