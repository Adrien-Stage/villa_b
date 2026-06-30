<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantPantryMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_pantry_item_id',
        'type',
        'quantity',
        'reason',
        'notes',
        'recorded_by',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

