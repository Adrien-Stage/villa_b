<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prise de service d'un serveur en salle.
 *
 * Un serveur « en service » a une prise ouverte (closed_at null). Seuls les
 * serveurs en service reçoivent une part des commandes du portail lors de la
 * répartition automatique.
 */
class RestaurantShift extends Model
{
    use HasFactory, AppartientAUnRestaurant;

    protected $fillable = [
        'point_of_sale_id',
        'user_id',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
