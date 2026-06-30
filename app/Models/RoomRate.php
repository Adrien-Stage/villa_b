<?php
// app/Models/RoomRate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RoomRate : Tarif spécifique pour un type de chambre
 * 
 * CDC Section 4.6 : Tarification complexe
 * 
 * PRIORITÉ DES TARIFS (du plus prioritaire au moins) :
 * 1. Groupe (GroupBooking spécifique)
 * 2. Événementiel culturel (FESPAM, FESTIVAM...)
 * 3. Saisonnier (haute/basse saison)
 * 4. Hebdomadaire (week-end vs semaine)
 * 5. Long séjour (>7 nuits)
 * 6. Dernière minute (<24h)
 * 7. Tarif de base (RoomType.base_price)
 * 
 * Ce modèle stocke les règles 2-6. Les règles 1 et 7 sont ailleurs.
 */
class RoomRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        
        // Identification
        'name',                 // "Haute Saison 2025", "Tarif Week-end"
        'rate_type',            // 'seasonal', 'weekly', 'event', 'long_stay', 'last_minute'
        
        // Période d'application
        'start_date',
        'end_date',
        'days_of_week',         // JSON [1,2,3,4,5] pour lundi-vendredi
        
        // Prix
        'price_adjustment_type', // 'fixed', 'percentage', 'amount_off'
        'price_adjustment',     // Valeur (ex: -10 pour -10%, ou 50000 pour prix fixe)
        'min_nights',           // Pour long_stay : minimum de nuits
        'max_nights',           // Maximum pour ce tarif
        'min_advance_days',     // Pour last_minute : réservation < X jours avant
        'max_advance_days',
        
        // Conditions
        'event_name',           // Si rate_type = 'event' : "FESPAM 2025"
        'is_active',
        'priority',             // Ordre d'application si conflit (plus haut = gagne)
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Vérifie si ce tarif est applicable pour une date donnée
     */
    public function isApplicable(\Carbon\Carbon $date, int $nights, int $advanceDays): bool
    {
        // Vérification période
        if ($date->lt($this->start_date) || $date->gt($this->end_date)) {
            return false;
        }

        // Vérification jours de semaine
        if ($this->days_of_week && !in_array($date->dayOfWeek, $this->days_of_week)) {
            return false;
        }

        // Vérification nuits min/max
        if ($this->min_nights && $nights < $this->min_nights) return false;
        if ($this->max_nights && $nights > $this->max_nights) return false;

        // Vérification avance
        if ($this->min_advance_days && $advanceDays < $this->min_advance_days) return false;
        if ($this->max_advance_days && $advanceDays > $this->max_advance_days) return false;

        return true;
    }

    /**
     * Calcule le prix final selon le type d'ajustement
     */
    public function calculatePrice(int $basePrice): int
    {
        return match($this->price_adjustment_type) {
            'fixed' => $this->price_adjustment,
            'percentage' => (int) round($basePrice * (1 + $this->price_adjustment / 100)),
            'amount_off' => max(0, $basePrice - $this->price_adjustment),
            default => $basePrice,
        };
    }
}