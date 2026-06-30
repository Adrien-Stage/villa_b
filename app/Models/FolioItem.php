<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FolioItem : Une ligne dans le dossier ouvert d'un séjour
 *
 * Enregistre TOUTES les prestations d'un client pendant son séjour :
 * hébergement, restaurant, activités, spa, minibar, etc.
 * Payantes ou offertes — tout est tracé.
 */
class FolioItem extends Model
{
    use HasFactory;

    // Types de prestations disponibles
    const TYPE_ROOM       = 'room';
    const TYPE_RESTAURANT = 'restaurant';
    const TYPE_SHOP       = 'shop';
    const TYPE_ACTIVITY   = 'activity';
    const TYPE_SPA        = 'spa';
    const TYPE_MINIBAR    = 'minibar';
    const TYPE_LAUNDRY    = 'laundry';
    const TYPE_DISCOUNT   = 'discount';
    const TYPE_PAYMENT    = 'payment';
    const TYPE_OTHER      = 'other';

    protected $fillable = [
        'booking_id',
        'customer_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'is_complimentary',
        'earns_points',
        'recorded_by',
        'occurred_at',
        'notes',
    ];

    protected $casts = [
        'quantity'         => 'decimal:2',
        'unit_price'       => 'integer',
        'total_price'      => 'integer',
        'is_complimentary' => 'boolean',
        'earns_points'     => 'boolean',
        'occurred_at'      => 'datetime',
    ];

    // Relations

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Helpers

    /**
     * Montant formaté en FCFA
     */
    public function formattedPrice(): string
    {
        if ($this->is_complimentary) return 'Offert';
        return number_format($this->total_price / 100, 0, ',', ' ') . ' FCFA';
    }

    /**
     * Label lisible du type
     */
    public function typeLabel(): string
    {
        return match($this->type) {
            self::TYPE_ROOM       => 'Hébergement',
            self::TYPE_RESTAURANT => 'Restaurant',
            self::TYPE_SHOP       => 'Boutique',
            self::TYPE_ACTIVITY   => 'Activité',
            self::TYPE_SPA        => 'Spa',
            self::TYPE_MINIBAR    => 'Minibar',
            self::TYPE_LAUNDRY    => 'Blanchisserie',
            self::TYPE_DISCOUNT   => 'Remise',
            self::TYPE_PAYMENT    => 'Paiement',
            default               => 'Autre',
        };
    }
}