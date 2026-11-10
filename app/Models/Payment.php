<?php
// app/Models/Payment.php

namespace App\Models;

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
    use HasFactory;

    public const METHOD_PARTNER_WALLET = 'partner_wallet';
    public const PURPOSE_PARTNER_DEPOSIT = 'partner_deposit';

    public const ORIGIN_ACOMPTE_RESERVATION = 'acompte_reservation';
    public const ORIGIN_CHECK_IN = 'check_in';
    public const ORIGIN_SEJOUR_NUITEES = 'sejour_nuitees';
    public const ORIGIN_CHECK_OUT = 'check_out';
    public const ORIGIN_FACTURE_COMPLEMENTAIRE = 'facture_complementaire';
    public const ORIGIN_GROUPE = 'groupe';
    public const ORIGIN_POS_RECEPTION = 'pos_reception';
    public const ORIGIN_REMBOURSEMENT_ANNULATION = 'remboursement_annulation';
    public const ORIGIN_DEPOT_PARTENAIRE = 'depot_partenaire';

    public const ORIGINS = [
        self::ORIGIN_ACOMPTE_RESERVATION => 'Acompte réservation',
        self::ORIGIN_CHECK_IN => 'Check-in',
        self::ORIGIN_SEJOUR_NUITEES => 'Séjour / Nuitées',
        self::ORIGIN_CHECK_OUT => 'Check-out / Solde départ',
        self::ORIGIN_FACTURE_COMPLEMENTAIRE => 'Facture complémentaire',
        self::ORIGIN_GROUPE => 'Dossier groupe',
        self::ORIGIN_POS_RECEPTION => 'POS Réception',
        self::ORIGIN_REMBOURSEMENT_ANNULATION => 'Remboursement annulation',
        self::ORIGIN_DEPOT_PARTENAIRE => 'Approvisionnement dépôt partenaire',
    ];

    protected $fillable = [
        'booking_id',
        'partner_organization_id',
        'wallet_entry_id',
        'purpose',
        'payment_origin',       // Origine explicite pour les registres (acompte, check-in, séjour, check-out, facture...)
        'invoice_id',           // Facture soldée, quand l'encaissement en vise une (facture complémentaire)
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

    public function partnerOrganization(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganization::class);
    }

    public function walletEntry(): BelongsTo
    {
        return $this->belongsTo(WalletEntry::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Entrées/sorties d'argent ; l'utilisation d'un dépôt n'encaisse rien. */
    public function scopeCashFlows($query)
    {
        return $query->where(fn ($q) => $q->where('method', '!=', self::METHOD_PARTNER_WALLET)->orWhereNull('method'));
    }

    /** Règlements de prestations, hors avances encore disponibles du partenaire. */
    public function scopeServiceSettlements($query)
    {
        return $query->where('purpose', '!=', self::PURPOSE_PARTNER_DEPOSIT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function methodLabel(): string
    {
        $methods = [
            self::METHOD_PARTNER_WALLET => 'Compte dépôt partenaire',
            'orange_money'   => 'Orange Money',
            'mtn_momo'       => 'MTN MoMo',
            'cash'           => 'Espèces',
            'stripe'         => 'Carte bancaire (Stripe)',
            'bank_transfer'  => 'Virement bancaire',
            'check'          => 'Chèque',
        ];

        return $methods[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method ?? 'inconnu'));
    }

    public function originLabel(bool $hasReceptionSale = false, ?Booking $bookingContext = null): string
    {
        if ($this->purpose === self::PURPOSE_PARTNER_DEPOSIT) {
            return self::ORIGINS[self::ORIGIN_DEPOT_PARTENAIRE];
        }

        if ($this->payment_origin !== null && isset(self::ORIGINS[$this->payment_origin])) {
            return self::ORIGINS[$this->payment_origin];
        }

        if ((int) $this->amount < 0 || str_contains(mb_strtolower((string) $this->refund_reason), 'annulation')) {
            return self::ORIGINS[self::ORIGIN_REMBOURSEMENT_ANNULATION];
        }

        if ($hasReceptionSale || str_starts_with((string) $this->notes, 'POS Réception')) {
            return self::ORIGINS[self::ORIGIN_POS_RECEPTION];
        }

        $notesLower = mb_strtolower((string) $this->notes);

        if ($this->invoice_id !== null || str_contains($notesLower, 'facture complémentaire')) {
            return self::ORIGINS[self::ORIGIN_FACTURE_COMPLEMENTAIRE];
        }

        if (str_contains($notesLower, 'groupe')) {
            return self::ORIGINS[self::ORIGIN_GROUPE];
        }

        if (str_contains($notesLower, 'acompte')) {
            return self::ORIGINS[self::ORIGIN_ACOMPTE_RESERVATION];
        }

        if (str_contains($notesLower, 'check-out') || str_contains($notesLower, 'check out') || str_contains($notesLower, 'départ')) {
            return self::ORIGINS[self::ORIGIN_CHECK_OUT];
        }

        if (str_contains($notesLower, 'check-in') || str_contains($notesLower, 'check in') || str_contains($notesLower, 'arrivée')) {
            return self::ORIGINS[self::ORIGIN_CHECK_IN];
        }

        $booking = $bookingContext ?? ($this->relationLoaded('booking') ? $this->booking : null);
        if ($booking && $this->paid_at) {
            if ($booking->actual_check_out && abs($booking->actual_check_out->timestamp - $this->paid_at->timestamp) <= 1800) {
                return self::ORIGINS[self::ORIGIN_CHECK_OUT];
            }
            if ($booking->actual_check_in && abs($booking->actual_check_in->timestamp - $this->paid_at->timestamp) <= 1800) {
                return self::ORIGINS[self::ORIGIN_CHECK_IN];
            }
        }

        if ($this->booking_id !== null) {
            return self::ORIGINS[self::ORIGIN_SEJOUR_NUITEES];
        }

        return 'Encaissement réception';
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
