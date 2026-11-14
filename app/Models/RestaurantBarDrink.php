<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantBarDrink extends Model
{
    public const GLASS_ML = 50;

    public const FORMATS = [
        'bottle' => 'Bouteille entière',
        'glass' => 'Verre de 5 cl',
        'cocktail' => 'Cocktail alcoolisé',
        'mocktail' => 'Mocktail sans alcool',
    ];

    protected $fillable = ['restaurant_menu_item_id', 'format', 'ingredients', 'instructions'];

    protected $casts = ['ingredients' => 'array'];
}
