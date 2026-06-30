<?php
// app/Models/Invoice.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice : Facture finale générée au check-out
 * 
 * CDC Section 4.8.2 : Facturation PDF brandée
 * 
 * PRINCIPE :
 * - Immuable une fois émise (correction = avoir + nouvelle facture)
 * - Numérotation séquentielle légale
 * - Contient les lignes de facturation détaillées
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'customer_id',
        
        // Numérotation légale
        'invoice_number',       // F-2025-000001 (séquentiel annuel)
        'invoice_date',
        
        // Montants
        'subtotal',             // Total avant taxes
        'tax_amount',           // TVA et taxes locales
        'total_amount',         // TTC
        
        // Paiement
        'paid_amount',
        'balance_due',
        'status',               // 'draft', 'sent', 'paid', 'overdue', 'cancelled'
        
        // Documents
        'pdf_path',             // Stockage MinIO
        'sent_at',
        'sent_to_email',
        
        // Notes légales
        'legal_notes',          // Mentions obligatoires Cameroun
        'internal_notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'balance_due' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Lignes de facture détaillées
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}