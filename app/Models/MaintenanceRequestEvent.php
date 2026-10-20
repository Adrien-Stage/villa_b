<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une étape d'une demande d'intervention : qui a fait quoi, quand. Le
 * responsable technique et le demandeur y lisent toute l'histoire.
 */
class MaintenanceRequestEvent extends Model
{
    public const UPDATED_AT = null;

    public const LIBELLES = [
        'created'     => 'Demande signalée',
        'assigned'    => 'Affectée',
        'taken'       => 'Prise en charge',
        'started'     => 'Intervention commencée',
        'parts'       => 'Pièces employées',
        'submitted'   => 'Fiche d\'intervention soumise',
        'approved'    => 'Fiche visée et clôturée',
        'rework'      => 'Reprise demandée',
        'completed'   => 'Intervention terminée',
        'reopened'    => 'Rouverte',
        'cancelled'   => 'Annulée',
        'requalified' => 'Requalifiée',
    ];

    protected $fillable = ['maintenance_request_id', 'user_id', 'event', 'notes', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function libelle(): string
    {
        return self::LIBELLES[$this->event] ?? $this->event;
    }
}
