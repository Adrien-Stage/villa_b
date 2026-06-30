<?php
// app/Models/Room.php

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

/**
 * Room : Chambre physique de l'hôtel
 * 
 * Une Room appartient à un RoomType (catégorie).
 * Exemple : Chambre 101 est de type "Standard"
 * 
 * CDC Section 4.3 : Numéro, étage, vue, statut
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'number',
        'floor',
        'view_type',
        'status',
        'notes',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'status' => RoomStatus::class, // Cast vers l'enum PHP
        'is_active' => 'boolean',
    ];

    /**
     * Relation : La chambre est d'un type défini
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Relation : Historique des statuts (pour audit)
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(RoomStatusHistory::class);
    }

    /**
     * Relation : Réservations actives et futures
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function housekeepingAssignments(): HasMany
    {
        return $this->hasMany(HousekeepingAssignment::class);
    }

    public function activeHousekeepingAssignment(): HasOne
    {
        return $this->hasOne(HousekeepingAssignment::class)
            ->whereIn('status', ['pending', 'in_progress'])
            ->latestOfMany();
    }

    /**
     * Scope : Chambres disponibles pour une période
     * Utilisé dans le wizard de réservation (section 4.4.1)
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        return $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
            $q->where(function ($sq) use ($checkIn, $checkOut) {
                $sq->whereBetween('check_in', [$checkIn, $checkOut])
                    ->orWhereBetween('check_out', [$checkIn, $checkOut])
                    ->orWhere(function ($ssq) use ($checkIn, $checkOut) {
                        $ssq->where('check_in', '<=', $checkIn)
                            ->where('check_out', '>=', $checkOut);
                    });
            })->whereNotIn('status', ['cancelled', 'no_show']);
        })->where('status', RoomStatus::AVAILABLE)
            ->where('is_active', true);
    }

    /**
     * Met à jour le statut avec historique (pattern Observer)
     * 
     * @param RoomStatus $newStatus Nouveau statut
     * @param string|null $reason Raison du changement
     * @param int|null $userId ID de l'utilisateur (null = auth()->id() si disponible)
     */
    public function updateStatus(RoomStatus $newStatus, ?string $reason = null, ?int $userId = null): void
    {
        // Vérifie si changement réel
        if ($this->status === $newStatus) {
            return;
        }

        $oldStatus = $this->status;

        // Mise à jour du statut
        $this->update(['status' => $newStatus]);

        // Résolution défensive de l'userId
        // Si null passé ET auth disponible → on récupère l'ID
        // Si auth non disponible (CLI) → on met null (système)
        $resolvedUserId = $userId;

        if ($resolvedUserId === null && Auth::check()) {
            $resolvedUserId = Auth::id();
        }

        // Création de l'historique
        $this->statusHistory()->create([
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $resolvedUserId,  // Peut être null en CLI
            'changed_at' => now(),
        ]);
    }
}
