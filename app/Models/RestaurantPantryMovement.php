<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantPantryMovement extends Model
{
    use HasFactory;

    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_ADJUST = 'adjust';

    // Motifs saisis à la main
    const REASON_PURCHASE = 'purchase';
    const REASON_KITCHEN = 'kitchen';
    const REASON_WASTE = 'waste';
    const REASON_CORRECTION = 'correction';
    const REASON_OTHER = 'other';

    // Motifs générés par le système
    const REASON_SALE = 'sale';               // sortie déclenchée par la vente d'un plat
    const REASON_SALE_RETURN = 'sale_return'; // restitution après annulation d'une commande
    const REASON_PRODUCTION = 'production';   // fabrication d'une préparation en batch
    const REASON_COUNT = 'count';             // ajustement issu d'un inventaire physique

    public const REASON_LABELS = [
        self::REASON_PURCHASE => 'Achat',
        self::REASON_KITCHEN => 'Cuisine',
        self::REASON_WASTE => 'Perte',
        self::REASON_CORRECTION => 'Correction',
        self::REASON_SALE => 'Vente',
        self::REASON_SALE_RETURN => 'Annulation de vente',
        self::REASON_PRODUCTION => 'Production',
        self::REASON_COUNT => 'Inventaire',
        self::REASON_OTHER => 'Autre',
    ];

    protected $fillable = [
        'restaurant_pantry_item_id',
        'type',
        'quantity',
        'unit_cost',
        'total_cost',
        'stock_after',
        'restaurant_customer_order_id',
        'restaurant_recipe_id',
        'reason',
        'notes',
        'recorded_by',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'integer',
        'stock_after' => 'decimal:3',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantCustomerOrder::class, 'restaurant_customer_order_id');
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(RestaurantRecipe::class, 'restaurant_recipe_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? 'Autre';
    }

    /**
     * Mouvement généré par le système : il ne se saisit pas à la main.
     */
    public function isAutomatic(): bool
    {
        return in_array($this->reason, [
            self::REASON_SALE,
            self::REASON_SALE_RETURN,
            self::REASON_PRODUCTION,
            self::REASON_COUNT,
        ], true);
    }
}
