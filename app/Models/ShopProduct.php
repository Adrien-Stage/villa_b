<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopProduct extends Model
{
    use HasFactory;

    protected $table = 'shop_products';

    protected $fillable = [
        'shop_category_id',
        'name',
        'description',
        'image_path',
        'sku',
        'price',
        'stock_quantity',
        'reorder_level',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'stock_quantity' => 'integer',
        'reorder_level' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class);
    }
}
