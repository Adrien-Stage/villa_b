<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantMenuCategory extends Model
{
    use HasFactory, AppartientAUnRestaurant;

    protected $fillable = [
        'point_of_sale_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantMenuItem::class, 'restaurant_menu_category_id');
    }
}

