<?php
// app/Models/RestaurantNote.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RestaurantNote : Consommations restaurant mises "en attente" sur un séjour
 * 
 * CDC Section 4.10.1 : Mécanisme de note ouverte
 * 
 * WORKFLOW :
 * 1. Client au restaurant : "Mettez sur ma chambre 101"
 * 2. Création d'une RestaurantNote liée au Booking
 * 3. Consommations ajoutées à cette note
 * 4. Au check-out : fusion automatique dans la facture finale
 * 
 * C'est une relation polymorphe "Folio" : tout peut être mis sur le dossier
 */
class RestaurantNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',           // Le séjour concerné
        'customer_id',          // Client (si différent du titulaire du booking)
        
        // Statut
        'status',               // 'open', 'closed', 'disputed'
        'opened_at',
        'closed_at',
        
        // Montants
        'total_amount',         // Calculé des items
        'tip_amount',           // Pourboire (spécificité restaurant)
        
        // Référence
        'table_number',
        'server_name',          // Serveur qui a ouvert la note
        
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Les consommations de cette note
     */
    public function items()
    {
        return $this->hasMany(RestaurantOrderItem::class); // À créer plus tard
    }
}