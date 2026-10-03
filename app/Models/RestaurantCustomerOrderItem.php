<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantCustomerOrderItem extends Model
{
    use HasFactory;

    /** Où la ligne se prépare : chaque restaurant a sa cuisine et son bar. */
    public const STATION_CUISINE = 'cuisine';

    public const STATION_BAR = 'bar';

    protected $fillable = [
        'restaurant_customer_order_id',
        'menu_item_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
        'special_requests',
        'station',
        'ready_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'ready_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Une boisson part au bar, le reste en cuisine.
        static::creating(function (self $ligne): void {
            if (empty($ligne->station)) {
                $type = $ligne->menu_item_id
                    ? RestaurantMenuItem::query()->whereKey($ligne->menu_item_id)->value('type')
                    : null;
                $ligne->station = $type === 'drink' ? self::STATION_BAR : self::STATION_CUISINE;
            }
        });
    }

    public function scopeAuBar(Builder $requete): Builder
    {
        return $requete->where('station', self::STATION_BAR);
    }

    public function scopeEnCuisine(Builder $requete): Builder
    {
        return $requete->where('station', self::STATION_CUISINE);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantCustomerOrder::class, 'restaurant_customer_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuItem::class, 'menu_item_id');
    }
}
