<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantCustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'created_by',
        'table_number',
        'booking_id',
        'folio_item_id',
        'customer_name',
        'customer_phone',
        'status',
        'payment_status',
        'payment_method',
        'total_amount',
        'amount_paid',
        'notes',
        'placed_at',
        'paid_at',
        'paid_by',
        'stock_deducted_at',
        'food_cost',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'amount_paid' => 'integer',
        'food_cost' => 'integer',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
    ];

    /**
     * Les ingrédients de cette commande ont-ils déjà été sortis du garde-manger ?
     */
    public function stockWasDeducted(): bool
    {
        return $this->stock_deducted_at !== null;
    }

    /**
     * Marge brute de la commande, en centimes FCFA. Null tant que le coût matière
     * n'a pas été figé (commande non envoyée en cuisine).
     */
    public function margin(): ?int
    {
        if ($this->food_cost === null) {
            return null;
        }

        return (int) $this->total_amount - (int) $this->food_cost;
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantCustomerOrderItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
