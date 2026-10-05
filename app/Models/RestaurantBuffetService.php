<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un service de buffet au forfait : un restaurant ouvre son buffet pour un
 * repas d'une journée, à un prix d'entrée, et enregistre les entrées au fil
 * du service — sans note par table.
 *
 * L'autre forme du buffet, la formule au couvert, est un produit de la carte
 * commandé comme le reste.
 */
class RestaurantBuffetService extends Model
{
    use AppartientAUnRestaurant;

    /** Le buffet se sert en salle. */
    public const SERVICE_REQUIS = PointOfSale::SERVICE_SALLE;

    public const OUVERT = 'open';

    public const CLOS = 'closed';

    protected $fillable = [
        'point_of_sale_id', 'service_date', 'meal_service', 'adult_price', 'child_price',
        'status', 'opened_by', 'opened_at', 'closed_by', 'closed_at', 'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'adult_price' => 'integer',
        'child_price' => 'integer',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(RestaurantBuffetEntry::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function estOuvert(): bool
    {
        return $this->status === self::OUVERT;
    }

    public function libelleRepas(): string
    {
        return RestaurantMenuItem::MEAL_SERVICES[$this->meal_service] ?? $this->meal_service;
    }
}
