<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CancellationPolicy;
use App\Models\CashRegisterSession;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CancellationPolicyService
{
    /**
     * Retourne la politique par défaut configurée dans l'établissement
     */
    public function getDefaultPolicy(): CancellationPolicy
    {
        return CancellationPolicy::getDefault()
            ?? CancellationPolicy::firstOrCreate(
                ['code' => 'FLEX_48H'],
                [
                    'name'                    => 'Flexible (jusqu\'à 48h avant l\'arrivée)',
                    'description'             => 'Annulation gratuite jusqu\'à 2 jours avant l\'arrivée à 18h00. Au-delà, la première nuitée est due.',
                    'penalty_type'            => CancellationPolicy::PENALTY_FIRST_NIGHT,
                    'penalty_value'           => null,
                    'free_cancel_days_before' => 2,
                    'free_cancel_time'        => '18:00',
                    'is_default'              => true,
                    'is_active'               => true,
                ]
            );
    }

    /**
     * Détermine la politique applicable pour une réservation ou un pack
     */
    public function resolvePolicyForBooking(?int $policyId = null, ?int $packageId = null): CancellationPolicy
    {
        if ($policyId) {
            $policy = CancellationPolicy::query()->active()->find($policyId);
            if ($policy) {
                return $policy;
            }
        }

        return $this->getDefaultPolicy();
    }

    /**
     * Calcule la date et heure limite exacte pour annuler sans pénalité
     */
    public function calculateFreeCancelUntil(CancellationPolicy $policy, CarbonInterface $checkInDate): Carbon
    {
        return $policy->calculateFreeCancelUntil($checkInDate);
    }

    /**
     * Calcule la pénalité, la retenue et le remboursement au moment de l'annulation
     *
     * @return array{
     *     is_free_cancellation: bool,
     *     free_cancel_until: ?Carbon,
     *     cancelled_at: Carbon,
     *     policy_code: string,
     *     policy_name: string,
     *     penalty_type: string,
     *     theoretical_penalty: int,
     *     penalty_amount: int,
     *     penalty_waived: bool,
     *     deposit_paid: int,
     *     deposit_retained: int,
     *     refund_amount: int,
     *     remaining_due: int
     * }
     */
    public function calculateCancellation(
        Booking $booking,
        ?CarbonInterface $cancelledAt = null,
        bool $waivePenalty = false
    ): array {
        $now = $cancelledAt ? Carbon::parse($cancelledAt) : now();

        // 1. Récupération des conditions depuis le snapshot ou la relation
        $snapshot = $booking->cancellation_policy_snapshot;
        $policy = $booking->cancellationPolicy ?? $this->getDefaultPolicy();

        $policyCode  = $snapshot['code'] ?? $policy->code;
        $policyName  = $snapshot['name'] ?? $policy->name;
        $penaltyType = $snapshot['penalty_type'] ?? $policy->penalty_type;
        $penaltyVal  = $snapshot['penalty_value'] ?? $policy->penalty_value;

        // 2. Date limite sans frais
        $freeCancelUntil = $booking->free_cancel_until;
        if (!$freeCancelUntil && $booking->check_in) {
            $freeCancelUntil = $policy->calculateFreeCancelUntil($booking->check_in);
        }

        // 3. Calcul de la pénalité théorique
        $theoreticalPenalty = 0;

        if (!$booking->is_complimentary) {
            if ($penaltyType === CancellationPolicy::PENALTY_NON_REFUNDABLE) {
                $theoreticalPenalty = (int) $booking->total_amount;
            } elseif ($penaltyType === CancellationPolicy::PENALTY_FREE) {
                $theoreticalPenalty = 0;
            } else {
                // Vérifie si la date d'annulation est au-delà du délai sans frais
                $isLateCancellation = $freeCancelUntil ? $now->gt($freeCancelUntil) : true;

                if ($isLateCancellation) {
                    $theoreticalPenalty = match ($penaltyType) {
                        CancellationPolicy::PENALTY_FIRST_NIGHT  => (int) ($booking->price_per_night ?: ($booking->total_nights > 0 ? (int) round($booking->total_room_amount / $booking->total_nights) : $booking->total_amount)),
                        CancellationPolicy::PENALTY_PERCENTAGE   => (int) round($booking->total_amount * min(100, (int) $penaltyVal) / 100),
                        CancellationPolicy::PENALTY_FIXED_AMOUNT => min((int) $booking->total_amount, (int) $penaltyVal),
                        default                                  => (int) ($booking->price_per_night ?: $booking->total_amount),
                    };
                }
            }
        }

        // 4. Application d'une dérogation commerciale éventuelle
        $penaltyAmount = $waivePenalty ? 0 : $theoreticalPenalty;
        $isFree = ($penaltyAmount === 0);

        // 5. Ventilation financière par rapport aux paiements déjà perçus
        $depositPaid     = (int) $booking->paid_amount;
        $depositRetained = min($depositPaid, $penaltyAmount);
        $refundAmount    = max(0, $depositPaid - $penaltyAmount);
        $remainingDue    = max(0, $penaltyAmount - $depositPaid);

        return [
            'is_free_cancellation' => $isFree,
            'free_cancel_until'    => $freeCancelUntil,
            'cancelled_at'         => $now,
            'policy_code'          => $policyCode,
            'policy_name'          => $policyName,
            'penalty_type'         => $penaltyType,
            'theoretical_penalty'  => $theoreticalPenalty,
            'penalty_amount'       => $penaltyAmount,
            'penalty_waived'       => $waivePenalty,
            'deposit_paid'         => $depositPaid,
            'deposit_retained'     => $depositRetained,
            'refund_amount'        => $refundAmount,
            'remaining_due'        => $remainingDue,
        ];
    }

    /**
     * Traite l'annulation d'une réservation de manière atomique et conforme aux standards hôteliers
     */
    public function processCancellation(
        Booking $booking,
        array $data,
        ?User $user = null
    ): BookingCancellation {
        $user = $user ?? Auth::user();
        $waivePenalty = !empty($data['waive_penalty']) && ($user && $user->hasRole('manager'));

        return DB::transaction(function () use ($booking, $data, $user, $waivePenalty) {
            $now = now();
            $calc = $this->calculateCancellation($booking, $now, $waivePenalty);

            $refundMethod = $data['refund_method'] ?? 'none';
            $refundStatus = 'none';
            $refundPaymentId = null;

            // Traitement du remboursement si un montant doit être restitué
            if ($calc['refund_amount'] > 0) {
                if ($refundMethod === 'cash') {
                    // Vérifier si une caisse de réception est ouverte pour le remboursement espèces
                    $activeSession = CashRegisterSession::where('user_id', $user?->id)
                        ->where('module', 'reception')
                        ->whereNull('closed_at')
                        ->first();

                    if (!$activeSession) {
                        throw new \InvalidArgumentException('Veuillez ouvrir votre caisse de réception pour effectuer un remboursement en espèces.');
                    }

                    // Génération référence de paiement négatif
                    $year = now()->year;
                    $paymentsCount = Payment::withoutGlobalScopes()
                        ->where('reference', 'like', "PAY-{$year}-%")
                        ->count();
                    $paymentRef = sprintf('PAY-%d-%06d', $year, $paymentsCount + 1);

                    $payment = Payment::create([
                        'booking_id'               => $booking->id,
                        'customer_id'              => $booking->customer_id,
                        'amount'                   => -$calc['refund_amount'], // Négatif pour sortie/remboursement
                        'currency'                 => 'XAF',
                        'method'                   => 'cash',
                        'status'                   => 'completed',
                        'reference'                => $paymentRef,
                        'paid_at'                  => $now,
                        'refunded_at'              => $now,
                        'refund_reason'            => "Remboursement suite annulation {$booking->booking_number}",
                        'processed_by'             => $user?->id,
                        'notes'                    => $data['reason_description'] ?? 'Remboursement direct en caisse',
                        'cash_register_session_id' => $activeSession->id,
                    ]);

                    $refundPaymentId = $payment->id;
                    $refundStatus    = 'completed';
                } elseif (in_array($refundMethod, ['orange_money', 'mtn_momo', 'bank_transfer'])) {
                    $refundStatus = 'pending'; // À exécuter via les canaux bancaires/momo
                } elseif ($refundMethod === 'credit_note') {
                    $refundStatus = 'credit_issued';
                }
            }

            // Création de l'enregistrement d'annulation
            $cancellation = BookingCancellation::create([
                'booking_id'          => $booking->id,
                'cancelled_at'        => $now,
                'cancelled_by'        => $user?->id,
                'reason_code'         => $data['reason_code'] ?? 'guest_request',
                'reason_description'  => $data['reason_description'] ?? null,
                'penalty_amount'      => $calc['penalty_amount'],
                'penalty_waived'      => $calc['penalty_waived'],
                'waived_by'           => $calc['penalty_waived'] ? $user?->id : null,
                'waive_reason'        => $calc['penalty_waived'] ? ($data['waive_reason'] ?? 'Geste commercial') : null,
                'deposit_paid'        => $calc['deposit_paid'],
                'deposit_retained'    => $calc['deposit_retained'],
                'refund_amount'       => $calc['refund_amount'],
                'refund_method'       => $refundMethod,
                'refund_status'       => $refundStatus,
                'payment_id'          => $refundPaymentId,
            ]);

            // Inscription au Folio si une pénalité est retenue
            if ($calc['deposit_retained'] > 0) {
                FolioItem::create([
                    'booking_id'       => $booking->id,
                    'customer_id'      => $booking->customer_id,
                    'type'             => FolioItem::TYPE_PENALTY,
                    'description'      => "Frais d'annulation retenus ({$cancellation->cancellation_number})",
                    'quantity'         => 1,
                    'unit_price'       => $calc['deposit_retained'],
                    'total_price'      => $calc['deposit_retained'],
                    'is_complimentary' => false,
                    'earns_points'     => false,
                    'recorded_by'      => $user?->id,
                    'occurred_at'      => $now,
                    'notes'            => "Politique {$calc['policy_code']} appliquée",
                ]);
            }

            // Bascule du statut de la réservation
            $booking->update([
                'status' => BookingStatus::CANCELLED,
            ]);

            // Enregistrement d'audit
            AuditLog::record(
                $user?->id,
                'sensitive_action',
                "Annulation {$cancellation->cancellation_number} de la réservation #{$booking->booking_number} ({$booking->customer?->full_name}) - Pénalité: " . number_format($calc['penalty_amount'] / 100, 0, ',', ' ') . " FCFA, Remboursement: " . number_format($calc['refund_amount'] / 100, 0, ',', ' ') . " FCFA",
                'bookings',
                [
                    'booking_id'          => $booking->id,
                    'booking_number'      => $booking->booking_number,
                    'cancellation_number' => $cancellation->cancellation_number,
                    'penalty_amount'      => $calc['penalty_amount'],
                    'refund_amount'       => $calc['refund_amount'],
                    'reason_code'         => $cancellation->reason_code,
                ]
            );

            return $cancellation;
        });
    }
}
