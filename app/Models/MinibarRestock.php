<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Réapprovisionnement du mini-bar d'une chambre depuis le dépôt du mini-bar. */
class MinibarRestock extends Model
{
    /** Remise à niveau après le départ du client. */
    public const REASON_DEPART = 'depart';

    /** Mini-bar vidé par le client pendant son séjour. */
    public const REASON_VIDE = 'vide';

    /** Chambre libre : première mise en place ou complément. */
    public const REASON_MISE_EN_PLACE = 'mise_en_place';

    public const REASONS = [
        self::REASON_DEPART        => 'Après le départ',
        self::REASON_VIDE          => 'Mini-bar vide pendant le séjour',
        self::REASON_MISE_EN_PLACE => 'Mise en place',
    ];

    protected $fillable = ['room_id', 'service_store_id', 'booking_id', 'reason', 'total_cost', 'user_id', 'restocked_at'];

    protected $casts = [
        'total_cost'   => 'integer',
        'restocked_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(ServiceStore::class, 'service_store_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(MinibarMovement::class, 'source_id')
            ->where('source_type', MinibarMovement::SOURCE_RESTOCK);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
