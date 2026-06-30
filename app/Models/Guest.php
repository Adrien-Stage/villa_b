<?php
// app/Models/Guest.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Guest : Personne physique occupant une chambre
 * 
 * DISTINCTION CRITIQUE :
 * - Customer = qui paie / a réservé (le compte)
 * - Guest = qui dort dans la chambre (peut être différent)
 * 
 * Exemples :
 * - Mme Dubois (Customer) paie pour ses 2 enfants (Guests)
 * - L'entreprise ABC (Customer) paie pour ses employés (Guests)
 * - Un client walk-in sans fiche : Guest créé sans Customer lié
 */
class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',           // La réservation concernée
        'customer_id',          // Optionnel : si lié à une fiche client
        
        // Identité
        'first_name',
        'last_name',
        'date_of_birth',
        'nationality',
        
        // Documents (obligatoires pour la police du tourisme)
        'id_document_type',
        'id_document_number',
        'id_document_expiry',
        
        // Contact
        'phone',
        'email',
        
        // Check-in
        'checked_in_at',
        'checked_in_by',
        
        // Photo (pour la carte d'accès)
        'photo_path',
        
        // Relation avec le client payeur
        'is_primary_guest',     // True = c'est lui qui a signé le registre
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'id_document_expiry' => 'date',
        'checked_in_at' => 'datetime',
        'is_primary_guest' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}