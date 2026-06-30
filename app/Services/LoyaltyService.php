<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;

/**
 * LoyaltyService : Calcul et attribution des points fidélité
 *
 * Règles :
 * - 1 point par 1 000 FCFA dépensés
 * - Bonus selon le niveau actuel du client
 * - Bonus selon la durée du séjour
 * - Bonus week-end
 * - Mise à jour automatique du niveau après chaque séjour
 */
class LoyaltyService
{
    // Règle de base : 1 point par X centimes
    const FCFA_PER_POINT = 100000; // 1 000 FCFA en centimes

    // Multiplicateurs par niveau
    const MULTIPLIERS = [
        'bronze'   => 1.0,
        'silver'   => 1.25,
        'gold'     => 1.5,
        'platinum' => 2.0,
    ];

    // Seuils de dépenses cumulées pour chaque niveau (en centimes)
    const LEVEL_THRESHOLDS = [
        'platinum' => 5000000000, // 50 000 000 FCFA
        'gold'     => 2000000000, // 20 000 000 FCFA
        'silver'   =>  500000000, //  5 000 000 FCFA
        'bronze'   =>           0,
    ];

    /**
     * Calcule les points à attribuer pour un séjour
     */
    public function calculatePoints(Booking $booking): int
    {
        // On ne calcule que sur les montants éligibles du folio
        $eligibleAmount = $booking->folioItems()
            ->where('earns_points', true)
            ->where('is_complimentary', false)
            ->sum('total_price');

        if ($eligibleAmount <= 0) return 0;

        // Base : 1 point par 1 000 FCFA
        $basePoints = (int) floor($eligibleAmount / self::FCFA_PER_POINT);

        // Multiplicateur selon le niveau actuel du client
        $multiplier = self::MULTIPLIERS[$booking->customer->loyalty_level] ?? 1.0;
        $points = (int) floor($basePoints * $multiplier);

        // Bonus long séjour (>= 7 nuits) : +20%
        if ($booking->total_nights >= 7) {
            $points = (int) floor($points * 1.2);
        }

        // Bonus week-end : +10% si le séjour inclut un samedi
        if ($this->includesWeekend($booking)) {
            $points = (int) floor($points * 1.1);
        }

        return max(0, $points);
    }

    /**
     * Attribue les points au client après check-out
     * Crée la transaction et met à jour le niveau si nécessaire
     */
    public function awardPoints(Booking $booking): LoyaltyTransaction
    {
        $customer = $booking->customer;
        $points   = $this->calculatePoints($booking);

        // Mise à jour des métriques cumulées du client
        $customer->increment('total_nights_stayed', $booking->total_nights);
        $customer->increment('total_spent', $booking->total_amount);
        $customer->increment('loyalty_points', $points);

        // Recalcule le niveau selon le nouveau total dépensé
        $newLevel = $this->calculateLevel($customer->fresh()->total_spent);

        if ($newLevel !== $customer->loyalty_level) {
            $customer->update(['loyalty_level' => $newLevel]);
        }

        // Crée la transaction immuable
        $transaction = LoyaltyTransaction::create([
            'customer_id'  => $customer->id,
            'booking_id'   => $booking->id,
            'points'       => $points,
            'type'         => 'earned',
            'description'  => "Séjour {$booking->booking_number} — {$booking->total_nights} nuit(s)",
            'balance_after'=> $customer->fresh()->loyalty_points,
        ]);

        return $transaction;
    }

    /**
     * Utilise des points pour une remise sur une réservation
     * 100 points = 1 000 FCFA
     */
    public function redeemPoints(Customer $customer, int $points, Booking $booking): int
    {
        if ($customer->loyalty_points < $points) {
            throw new \LogicException("Points insuffisants : {$customer->loyalty_points} disponibles, {$points} demandés.");
        }

        // Calcul de la remise en centimes
        $discountAmount = (int) floor($points * (100000 / 100)); // 100 pts = 1 000 FCFA

        $customer->decrement('loyalty_points', $points);

        LoyaltyTransaction::create([
            'customer_id'  => $customer->id,
            'booking_id'   => $booking->id,
            'points'       => -$points, // Négatif = dépense
            'type'         => 'redeemed',
            'description'  => "Remise appliquée sur {$booking->booking_number}",
            'balance_after'=> $customer->fresh()->loyalty_points,
        ]);

        return $discountAmount;
    }

    /**
     * Détermine le niveau selon le total dépensé cumulé
     */
    public function calculateLevel(int $totalSpentCentimes): string
    {
        foreach (self::LEVEL_THRESHOLDS as $level => $threshold) {
            if ($totalSpentCentimes >= $threshold) {
                return $level;
            }
        }
        return 'bronze';
    }

    /**
     * Vérifie si le séjour inclut un samedi (jour 6)
     */
    private function includesWeekend(Booking $booking): bool
    {
        $current = $booking->check_in->copy();
        while ($current->lte($booking->check_out)) {
            if ($current->isSaturday()) return true;
            $current->addDay();
        }
        return false;
    }
}