<?php
// app/Models/Payment.php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment : Encaissement sur une réservation
 * 
 * CDC Section 4.8 : Modes de paiement
 * - Stripe (carte internationale)
 * - Orange Money / MTN MoMo (sandbox MVP)
 * - Espèces
 * 
 * ARCHITECTURE :
 * - Un paiement = un flux d'argent (entrant ou remboursement)
 * - Référence externe pour traçabilité bancaire/operateur
 */
class Payment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'booking_id',
        'customer_id',          // Pour les paiements hors réservation (ex: client walk-in restaurant)
        'cash_register_session_id',
        
        // Montant
        'amount',               // En centimes FCFA (positif = encaissement, négatif = remboursement)
        'currency',             // 'XAF' par défaut
        
        // Méthode
        'method',               // 'stripe', 'orange_money', 'mtn_momo', 'cash', 'bank_transfer', 'check'
        'status',               // 'pending', 'completed', 'failed', 'refunded', 'disputed'
        
        // Références externes
        'reference',            // Notre numéro interne (PAY-2025-0001)
        'external_reference',   // ID chez Stripe, OM, MTN...
        'external_receipt_url', // Justificatif PDF chez l'opérateur
        
        // Métadonnées
        'paid_at',
        'refunded_at',
        'refund_reason',
        
        // Traçabilité
        'processed_by',         // User qui a enregistré (null si automatique Stripe)
        'notes',
        
        // Données brutes de l'opérateur (pour debug)
        'gateway_response',     // JSON complet de la réponse API
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    /**
     * Helper : Montant formaté
     */
    public function formattedAmount(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        return $sign . number_format(abs($this->amount) / 100, 0, ',', ' ') . ' FCFA';
    }
}