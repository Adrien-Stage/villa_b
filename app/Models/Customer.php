<?php
// app/Models/Customer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer : Client de l'hôtel
 * 
 * Distinction importante : 
 * - Customer = la fiche client (CRM)
 * - Guest = occupant d'une chambre (peut être différent du Customer qui paie)
 * 
 * Un Customer peut avoir plusieurs Bookings (historique)
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'nationality',
        'id_document_type',     // 'passport', 'id_card', 'driver_license'
        'id_document_number',
        'date_of_birth',
        'address',
        'city',
        'country',
        
        // Préférences (section 4.2.1)
        'preferences',          // JSON : {"room_type": "suite", "floor": "high", ...}
        'allergies',            // ["peanuts", "gluten"]
        'special_requests',     // Notes sur les habitudes
        
        // Fidélité
        'loyalty_points',
        'loyalty_level',        // 'bronze', 'silver', 'gold', 'platinum'
        'total_nights_stayed',
        'total_spent',          // En centimes FCFA
        
        // Photo
        'photo_path',
        
        // Flags
        'is_vip',
        'is_blacklisted',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'preferences' => 'array',
        'allergies' => 'array',
        'date_of_birth' => 'date',
        'loyalty_points' => 'integer',
        'total_nights_stayed' => 'integer',
        'total_spent' => 'integer',
        'is_vip' => 'boolean',
        'is_blacklisted' => 'boolean',
    ];

    // Relations

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Historique des mouvements de points fidélité
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    // Accessors

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Met à jour le niveau de fidélité selon les règles métier
     * Section 4.2.3 : Points = 1pt/1000 FCFA, niveaux par seuils
     */
    public function recalculateLoyaltyLevel(): void
    {
        $levels = [
            'platinum' => 50000,  // 50M FCFA dépensés
            'gold' => 20000,      // 20M FCFA
            'silver' => 5000,     // 5M FCFA
            'bronze' => 0,
        ];

        foreach ($levels as $level => $threshold) {
            if ($this->total_spent >= $threshold * 100) { // threshold en centimes
                $this->loyalty_level = $level;
                break;
            }
        }
        
        $this->save();
    }

    /**
     * Ajoute des points et crée la transaction
     */
    public function addLoyaltyPoints(int $points, string $reason, ?int $bookingId = null): void
    {
        $this->increment('loyalty_points', $points);
        
        $this->loyaltyTransactions()->create([
            'points' => $points,
            'type' => 'earned',
            'reason' => $reason,
            'booking_id' => $bookingId,
            'balance_after' => $this->loyalty_points,
        ]);
    }
}