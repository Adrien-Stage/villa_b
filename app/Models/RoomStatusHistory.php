<?php
// app/Models/RoomStatusHistory.php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RoomStatusHistory : Journal d'audit des changements de statut des chambres
 * 
 * Pourquoi ce modèle ?
 * - Traçabilité complète (CDC section 6.3 : journalisation des actions)
 * - Debug : qui a mis cette chambre "hors service" ?
 * - Analytics : temps moyen de nettoyage, fréquence des maintenances
 * 
 * Pattern : Event Sourcing light - on garde l'historique immuable
 */
class RoomStatusHistory extends Model
{
    use HasFactory;

    /**
     * Désactive les timestamps automatiques car on a 'changed_at' explicite
     * Mais on garde created_at/updated_at pour la cohérence Eloquent
     */
    protected $table = 'room_status_histories';

    protected $fillable = [
        'room_id',           // ← CLÉ ÉTRANGÈRE EXPLICITE
        'from_status',       // Valeur enum avant
        'to_status',         // Valeur enum après
        'reason',            // Ex: "Check-out client Martin", "Fuite robinet"
        'changed_by',        // User ID qui a fait le changement
        'changed_at',        // Timestamp explicite de l'événement
    ];

    protected $casts = [
        'from_status' => RoomStatus::class,
        'to_status' => RoomStatus::class,
        'changed_at' => 'datetime',
    ];

    /**
     * Relation : Cet historiel concerne une chambre spécifique
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Relation : Qui a fait le changement
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}