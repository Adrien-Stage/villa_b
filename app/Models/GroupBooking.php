<?php
// app/Models/GroupBooking.php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GroupBooking : Dossier groupe liant plusieurs chambres
 * 
 * CDC Section 4.4.2 : Réservation de groupe ajoutée au MVP
 * 
 * Exemple : Une famille de 8 personnes prend 4 chambres, un séminaire prend 10 chambres
 * 
 * ARCHITECTURE :
 * - GroupBooking = l'enveloppe/dossier
 * - Booking (avec group_booking_id) = les chambres individuelles
 * 
 * AVANTAGES :
 * - Acompte global unique pour tout le groupe
 * - Rooming-list (répartition des gens dans les chambres)
 * - Facturation groupée possible
 */
class GroupBooking extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booker_id',            // Utilisateur (staff) ayant enregistré la réservation
        'group_code',           // Numéro de dossier groupe (ex: GRP-2025-0001)
        'contact_customer_id',  // Client principal qui paie/organise
        'group_name',           // Nom du groupe : "Famille Nkomo", "Séminaire UNESCO"
        'event_type',           // 'family', 'corporate', 'wedding', 'tour_group'
        
        // Dates globales (peuvent différer des dates individuelles si arrivées décalées)
        'start_date',
        'end_date',
        
        // Paiement global
        'total_deposit_required',
        'total_deposit_paid',
        
        // Statut du dossier
        'status',               // 'pending', 'confirmed', 'in_house', 'completed', 'cancelled'
        
        // Documents
        'rooming_list_sent',    // Boolean
        'rooming_list_sent_at',
        
        'notes',                // Notes générales
        'internal_notes',       // Notes staff
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rooming_list_sent' => 'boolean',
        'rooming_list_sent_at' => 'datetime',
    ];

    /**
     * Le client contact principal
     */
    public function contactCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'contact_customer_id');
    }

    /**
     * Le client qui a effectué/payé la réservation (si différent du contact).
     */
    public function booker(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'booker_id');
    }

    /**
     * Les réservations individuelles de chambres
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Tous les clients du groupe (via les bookings)
     */
    public function allCustomers()
    {
        return $this->bookings()->with('customer')->get()->pluck('customer');
    }

    /**
     * Génère le rooming-list (répartition)
     * CDC : Document listant qui est dans quelle chambre
     */
    public function generateRoomingList(): array
    {
        $list = [];
        
        foreach ($this->bookings as $booking) {
            $guests = $booking->guests; // Les occupants
            $list[] = [
                'room_number' => $booking->room->number,
                'room_type' => $booking->room->roomType->name,
                'guests' => $guests->map(fn($g) => $g->full_name),
            ];
        }
        
        return $list;
    }

    /**
     * Calcule le solde consommé réel pour tout le groupe
     */
    public function getConsumedBalance(): int
    {
        return $this->bookings->sum(function ($booking) {
            return $booking->getConsumedBalance();
        });
    }
}