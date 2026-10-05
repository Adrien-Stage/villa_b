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
        // Une boisson part au bar, le reste en cuisine — sauf si le
        // restaurant n'a pas l'un des deux : le bar d'une piscine prépare
        // aussi ses en-cas, une cuisine sans bar sert aussi les boissons.
        static::creating(function (self $ligne): void {
            if (empty($ligne->station)) {
                $type = $ligne->menu_item_id
                    ? RestaurantMenuItem::query()->whereKey($ligne->menu_item_id)->value('type')
                    : null;
                $ligne->station = self::stationPour($type === 'drink' ? self::STATION_BAR : self::STATION_CUISINE, $ligne);
            }
        });
    }

    /** Le poste prévu, ou l'autre quand le restaurant n'a que celui-là. */
    private static function stationPour(string $prevue, self $ligne): string
    {
        $restaurantId = RestaurantCustomerOrder::query()->whereKey($ligne->restaurant_customer_order_id)->value('point_of_sale_id');
        $restaurant = $restaurantId ? PointOfSale::find($restaurantId) : null;
        $autre = $prevue === self::STATION_BAR ? self::STATION_CUISINE : self::STATION_BAR;
        $service = [self::STATION_CUISINE => PointOfSale::SERVICE_CUISINE, self::STATION_BAR => PointOfSale::SERVICE_BAR];

        return $restaurant && ! $restaurant->offre($service[$prevue]) && $restaurant->offre($service[$autre]) ? $autre : $prevue;
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
