<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une entrée au buffet : des adultes, des enfants, un encaissement. */
class RestaurantBuffetEntry extends Model
{
    protected $fillable = [
        'restaurant_buffet_service_id', 'point_of_sale_id', 'adults', 'children', 'amount',
        'payment_method', 'booking_id', 'folio_item_id', 'cash_register_session_id', 'recorded_by', 'notes',
    ];

    protected $casts = [
        'adults' => 'integer',
        'children' => 'integer',
        'amount' => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(RestaurantBuffetService::class, 'restaurant_buffet_service_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
