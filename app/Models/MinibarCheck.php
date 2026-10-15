<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Relevé du mini-bar d'une chambre : ce que le client a consommé, compté par
 * l'équipe, et où la consommation est facturée.
 */
class MinibarCheck extends Model
{
    /** Pendant le séjour, à la recouche. */
    public const OCCASION_SEJOUR = 'sejour';

    /** Au départ, demandé par la réception avant de clore la note. */
    public const OCCASION_DEPART = 'depart';

    /** Après le départ : le client est parti sans attendre le relevé. */
    public const OCCASION_APRES_DEPART = 'apres_depart';

    public const OCCASIONS = [
        self::OCCASION_SEJOUR       => 'Pendant le séjour',
        self::OCCASION_DEPART       => 'Au départ',
        self::OCCASION_APRES_DEPART => 'Après le départ',
    ];

    /** Porté au folio du séjour en cours. */
    public const BILLING_FOLIO = 'folio';

    /** Client parti : la consommation reste à lui facturer. */
    public const BILLING_A_FACTURER = 'a_facturer';

    /** Rien de consommé. */
    public const BILLING_AUCUNE = 'aucune';

    protected $fillable = [
        'room_id', 'booking_id', 'housekeeping_assignment_id', 'occasion', 'billing',
        'total_amount', 'user_id', 'checked_at', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'checked_at'   => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(MinibarCheckLine::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occasionLabel(): string
    {
        return self::OCCASIONS[$this->occasion] ?? $this->occasion;
    }

    /** « 2 × Coca-Cola, 1 × Chips », ou « rien consommé ». */
    public function resume(): string
    {
        $consommes = $this->lines->filter(fn (MinibarCheckLine $l) => $l->consumed > 0);

        return $consommes->isEmpty()
            ? 'rien consommé'
            : $consommes->map(fn (MinibarCheckLine $l) => "{$l->consumed} × {$l->label}")->implode(', ');
    }
}
