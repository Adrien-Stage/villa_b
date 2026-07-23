<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hypothèses de la fiche technique d'un type de chambre : occupants de
 * référence, durée moyenne de séjour, charge fixe allouée par nuitée.
 *
 * Une fiche par type de chambre. Les lignes de coût, elles, sont rattachées
 * directement au type (RoomCostItem) pour rester lisibles.
 */
class RoomCostSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id', 'reference_occupants', 'avg_length_of_stay',
        'fixed_cost_per_night', 'notes', 'tenant_id',
    ];

    protected $casts = [
        'reference_occupants'  => 'integer',
        'avg_length_of_stay'   => 'decimal:2',
        'fixed_cost_per_night' => 'integer',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
