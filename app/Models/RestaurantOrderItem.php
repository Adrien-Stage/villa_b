<?php
// app/Models/RestaurantOrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RestaurantOrderItem : Ligne de consommation restaurant
 * 
 * Une consommation = un plat/boisson sur une note ouverte
 */
class RestaurantOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_note_id',   // La note ouverte liée
        'menu_item_id',         // Référence au menu (si défini)
        
        // Détails
        'item_name',            // Nom affiché (snapshot, car le menu peut changer)
        'quantity',
        'unit_price',
        'total_price',
        
        // Préparation
        'status',               // 'pending', 'preparing', 'ready', 'served'
        'special_requests',     // "sans oignon", "bien cuit"
        
        'served_at',
        'served_by',            // User ID
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'served_at' => 'datetime',
    ];

    public function restaurantNote(): BelongsTo
    {
        return $this->belongsTo(RestaurantNote::class);
    }
}