<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un article du mini-bar d'une chambre : ce que la chambre doit contenir
 * (dotation) et ce qu'elle contient, au coût moyen des unités présentes.
 */
class RoomMinibarLine extends Model
{
    protected $fillable = ['room_id', 'minibar_article_id', 'standard_quantity', 'quantity', 'unit_cost'];

    protected $casts = [
        'standard_quantity' => 'integer',
        'quantity'          => 'integer',
        'unit_cost'         => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(MinibarArticle::class, 'minibar_article_id');
    }

    /** Ce qu'il manque pour revenir à la dotation. */
    public function manquant(): int
    {
        return max(0, $this->standard_quantity - $this->quantity);
    }
}
