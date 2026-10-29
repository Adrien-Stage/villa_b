<?php
// app/Models/Room.php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Services\RoomAvailabilityService;
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
    // Effacé par un bouton « Supprimer », il passe par la corbeille.
    use \App\Models\Concerns\VaALaCorbeille;

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
        'minibar_due_booking_id',
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
            ->whereIn('status', ['pending', 'in_progress', 'blocked'])
            ->latestOfMany();
    }

    /**
     * Dernière affectation housekeeping, quel que soit son statut. Sert à
     * retrouver l'équipe responsable même après clôture (chambre nettoyée en
     * attente de contrôle), pour les permissions du chef d'équipe.
     */
    public function latestHousekeepingAssignment(): HasOne
    {
        return $this->hasOne(HousekeepingAssignment::class)->latestOfMany();
    }

    /** Le mini-bar de la chambre : dotation et contenu, article par article. */
    public function minibarLines(): HasMany
    {
        return $this->hasMany(RoomMinibarLine::class);
    }

    /** Séjour parti sans relevé de départ : son relevé du mini-bar reste dû. */
    public function minibarDueBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'minibar_due_booking_id');
    }

    /** La chambre a-t-elle un mini-bar (une dotation ou du contenu) ? */
    public function aUnMiniBar(): bool
    {
        $lignes = $this->relationLoaded('minibarLines') ? $this->minibarLines : $this->minibarLines()->get();

        return $lignes->contains(fn (RoomMinibarLine $l) => $l->standard_quantity > 0 || $l->quantity > 0);
    }

    /** Le mini-bar contient-il toute sa dotation ? */
    public function miniBarComplet(): bool
    {
        $lignes = $this->relationLoaded('minibarLines') ? $this->minibarLines : $this->minibarLines()->get();

        return $lignes->every(fn (RoomMinibarLine $l) => $l->quantity >= $l->standard_quantity);
    }

    /** Le client a-t-il vidé le mini-bar ? Rien dedans, alors qu'il a une dotation. */
    public function miniBarVide(): bool
    {
        $lignes = $this->relationLoaded('minibarLines') ? $this->minibarLines : $this->minibarLines()->get();

        return $lignes->sum('standard_quantity') > 0 && $lignes->sum('quantity') === 0;
    }

    /**
     * Scope : Chambres vendables, toutes dates confondues.
     *
     * Une chambre occupée aujourd'hui reste vendable pour le mois prochain :
     * seules maintenance et hors service, dont l'indisponibilité n'a pas
     * d'échéance connue, sont écartées. C'est la période demandée qui décide
     * ensuite, via RoomAvailabilityService.
     */
    public function scopeSellable($query)
    {
        return $query->where('is_active', true)
            ->whereNotIn('status', array_map(
                fn (RoomStatus $s) => $s->value,
                RoomAvailabilityService::UNSELLABLE_STATUSES
            ));
    }

    /**
     * Scope : Chambres libres sur une période.
     *
     * Intervalles semi-ouverts [arrivée, départ) : deux séjours bout à bout ne
     * se chevauchent pas. Le tampon de ménage de la rotation le jour même n'est
     * pas exprimable ici — il est appliqué par RoomAvailabilityService, qui
     * reste l'autorité sur l'acceptation d'une réservation.
     */
    public function scopeAvailableBetween($query, $checkIn, $checkOut)
    {
        $checkIn  = \Illuminate\Support\Carbon::parse($checkIn)->toDateString();
        $checkOut = \Illuminate\Support\Carbon::parse($checkOut)->toDateString();

        return $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
            $q->whereDate('check_in', '<', $checkOut)
                ->whereDate('check_out', '>', $checkIn)
                ->whereNotIn('status', ['cancelled', 'no_show']);
        })->sellable();
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

    /** Ce que la corbeille affiche pour cette fiche. */
    public function libelleCorbeille(): string
    {
        return 'Chambre ' . $this->number;
    }
}
