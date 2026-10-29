<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomImage extends Model
{
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

    protected $fillable = [
        'room_id',
        'path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Ce que la corbeille affiche pour cette fiche. */
    public function libelleCorbeille(): string
    {
        return 'Photo de la chambre ' . ($this->room?->number ?? '#' . $this->room_id);
    }
}
