<?php
// app/Models/InvoiceItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * InvoiceItem : Ligne détaillée d'une facture
 * 
 * Exemples :
 * - 3 nuits Chambre Standard @ 45 000 FCFA
 * - Petit-déjeuner x 6 @ 5 000 FCFA
 * - Taxe de séjour
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',           // Prix unitaire en centimes
        'total_price',          // quantity * unit_price
        'tax_rate',             // % de TVA appliqué
        'tax_amount',
        'category',             // 'room', 'restaurant', 'extra', 'tax', 'discount'
        'source_type',          // Polymorphisme optionnel : 'App\Models\Booking', 'App\Models\RestaurantOrder'
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'integer',
        'total_price' => 'integer',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}