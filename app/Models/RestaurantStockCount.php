<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une feuille d'inventaire physique : on compte réellement le garde-manger et on
 * confronte le résultat au stock théorique tenu par les fiches techniques.
 */
class RestaurantStockCount extends Model
{
    use HasFactory, AppartientAUnRestaurant;

    /** On ne compte que le stock d'un restaurant qui en tient un. */
    public const SERVICE_REQUIS = PointOfSale::SERVICE_STOCK;

    const STATUS_DRAFT = 'draft';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'point_of_sale_id',
        'reference',
        'status',
        'notes',
        'variance_value',
        'opened_by',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'variance_value' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RestaurantStockCountLine::class, 'restaurant_stock_count_id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
