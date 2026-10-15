<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Journal du housekeeping : chaque geste fait sur une chambre, par qui et à
 * quelle heure. Le responsable des étages et la gouvernante y lisent qui a
 * nettoyé, contrôlé ou rechargé quelle chambre.
 *
 * Immuable : on n'y corrige rien, on y ajoute.
 */
class HousekeepingLog extends Model
{
    public const ASSIGNED           = 'assigned';
    public const STARTED            = 'started';
    public const CLEANED            = 'cleaned';
    public const STAYOVER_STARTED   = 'stayover_started';
    public const STAYOVER_DONE      = 'stayover_done';
    public const INSPECTED          = 'inspected';
    public const REJECTED           = 'rejected';
    public const RELEASED           = 'released';
    public const ISSUE              = 'issue';
    public const MINIBAR_CHECKED    = 'minibar_checked';
    public const MINIBAR_RESTOCKED  = 'minibar_restocked';
    public const MINIBAR_REQUESTED  = 'minibar_requested';

    public const ACTIONS = [
        self::ASSIGNED          => 'Affectée à une équipe',
        self::STARTED           => 'Nettoyage commencé',
        self::CLEANED           => 'Nettoyée, à contrôler',
        self::STAYOVER_STARTED  => 'Recouche commencée',
        self::STAYOVER_DONE     => 'Recouche terminée',
        self::INSPECTED         => 'Contrôlée',
        self::REJECTED          => 'Refusée au contrôle',
        self::RELEASED          => 'Remise en vente',
        self::ISSUE             => 'Problème signalé',
        self::MINIBAR_CHECKED   => 'Relevé du mini-bar',
        self::MINIBAR_RESTOCKED => 'Mini-bar réapprovisionné',
        self::MINIBAR_REQUESTED => 'Contrôle du mini-bar demandé',
    ];

    protected $fillable = [
        'room_id', 'housekeeping_assignment_id', 'housekeeping_team_id', 'booking_id',
        'user_id', 'action', 'details', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** Consigne un geste fait maintenant par la personne connectée. */
    public static function noter(
        Room $room,
        string $action,
        ?string $details = null,
        ?HousekeepingAssignment $assignment = null,
        ?int $bookingId = null,
    ): self {
        return self::create([
            'room_id'                    => $room->id,
            'housekeeping_assignment_id' => $assignment?->id,
            'housekeeping_team_id'       => $assignment?->housekeeping_team_id,
            'booking_id'                 => $bookingId,
            'user_id'                    => Auth::id(),
            'action'                     => $action,
            'details'                    => $details !== null ? mb_substr($details, 0, 500) : null,
            'occurred_at'                => now(),
        ]);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTeam::class, 'housekeeping_team_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
