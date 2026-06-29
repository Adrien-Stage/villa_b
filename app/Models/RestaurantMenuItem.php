<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantMenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_menu_category_id',
        'name',
        'description',
        'image_path',
        'price',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuCategory::class, 'restaurant_menu_category_id');
    }
}

