<?php
// app/Models/LoyaltyTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LoyaltyTransaction : Journal immuable des mouvements de points
 * 
 * PRINCIPE COMPTABLE :
 * - On ne modifie jamais une transaction (pas de update)
 * - En cas d'erreur, on crée une transaction inverse (reversal)
 * - Solde final = somme de toutes les transactions
 * 
 * CDC Section 4.2.3 : Programme de fidélité
 */
class LoyaltyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'booking_id',           // Optionnel : contexte du séjour
        'points',               // Positif = gagné, Négatif = dépensé
        'type',                 // 'earned', 'redeemed', 'bonus', 'expired', 'reversal'
        'reason',               // Description lisible
        'balance_after',        // Solde après cette transaction (snapshot)
        'expires_at',           // Pour les points à expiration
        'reversed_from_id',     // Si c'est un reversal, lien vers l'originale
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Empêche la modification (immutabilité)
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function ($transaction) {
            throw new \LogicException('Les transactions de fidélité sont immuables');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}