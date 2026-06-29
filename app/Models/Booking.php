<?php
// app/Models/Booking.php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Booking : Réservation individuelle ou groupe
 * 
 * ARCHITECTURE :
 * - Une réservation = une chambre (individuelle) OU plusieurs chambres (groupe)
 * - Si group_booking_id est null → réservation individuelle
 * - Si group_booking_id est renseigné → fait partie d'un groupe
 * 
 * WORKFLOW STATUS :
 * pending → confirmed → checked_in → checked_out → completed
 *      ↓         ↓           ↓            ↓
 *  cancelled  no_show   early_dep    disputed
 * 
 * CDC Section 4.4 : Wizard 4 étapes, groupe, check-in/out
 */
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'customer_id',
        'booker_id',
        'group_booking_id',     // Null si individuel
        'booking_number',       // Numéro unique affiché (VB-2025-0001)
        'status',

        // Dates
        'check_in',
        'check_out',
        'actual_check_in',      // Heure réelle d'arrivée
        'actual_check_out',     // Heure réelle de départ
        'checkin_code',         // Code OTP pour la sécurité du check-in
        'checkin_attempts',     // Nombre de tentatives échouées

        // Personnes
        'adults_count',
        'children_count',

        // Tarification
        'total_nights',
        'price_per_night',      // Prix appliqué (peut différer du tarif base)
        'total_room_amount',    // total_nights * price_per_night
        'extras_amount',        // Restaurant, minibar...
        'tax_amount',
        'discount_amount',      // Points fidélité ou remise
        'total_amount',         // Montant final

        // Paiement
        'deposit_amount',       // Acompte versé
        'paid_amount',          // Total encaissé
        'balance_due',          // Reste à payer

        // Origine
        'source',               // 'direct', 'phone', 'email', 'ota_bookingcom'...
        'notes',                // Demandes spéciales
        'internal_notes',       // Notes staff (pas visible client)

        // Utilisateurs
        'created_by',           // Réceptionniste qui a créé
        'checked_in_by',        // Qui a fait le check-in
        'checked_out_by',       // Qui a fait le check-out
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'check_in' => 'date',
        'check_out' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'price_per_night' => 'integer',
        'total_room_amount' => 'integer',
        'extras_amount' => 'integer',
        'tax_amount' => 'integer',
        'discount_amount' => 'integer',
        'total_amount' => 'integer',
        'deposit_amount' => 'integer',
        'paid_amount' => 'integer',
        'balance_due' => 'integer',
    ];

    /**
     * Boot : Génère le numéro de réservation automatiquement
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($booking) {
            if (empty($booking->booking_number)) {
                $booking->booking_number = self::generateBookingNumber();
            }
        });
    }

    /**
     * Génère un numéro unique : VB-2025-000001
     */
    public static function generateBookingNumber(): string
    {
        $prefix = 'VB';
        $year = now()->year;
        $lastBooking = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastBooking ? (int)substr($lastBooking->booking_number, -6) + 1 : 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $sequence);
    }

    // RELATIONS

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Le client qui a effectué/payé la réservation (si différent du customer final).
     */
    public function booker(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'booker_id');
    }

    public function groupBooking(): BelongsTo
    {
        return $this->belongsTo(GroupBooking::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class); // Occupants de la chambre
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function restaurantNotes(): HasMany
    {
        return $this->hasMany(RestaurantNote::class); // Section 4.10.1
    }

    // SCOPES UTILES

    public function scopeArrivingToday($query)
    {
        return $query->where('check_in', today())
            ->whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::PENDING]);
    }

    public function scopeDepartingToday($query)
    {
        return $query->where('check_out', today())
            ->where('status', BookingStatus::CHECKED_IN);
    }

    public function scopeInHouse($query)
    {
        return $query->where('status', BookingStatus::CHECKED_IN);
    }

    // HELPERS MÉTIERS

    /**
     * Calcule les nuits et met à jour les montants
     */
    public function calculateTotals(): void
    {
        $this->total_nights = $this->check_in->diffInDays($this->check_out);
        $this->total_room_amount = $this->total_nights * $this->price_per_night;
        $this->total_amount = $this->total_room_amount
            + $this->extras_amount
            + $this->tax_amount
            - $this->discount_amount;
        $this->balance_due = $this->total_amount - $this->paid_amount;
        $this->save();
    }

    public function getConsumedBalance(): int
    {
        if ($this->status !== BookingStatus::CHECKED_IN || !$this->actual_check_in) {
            return $this->balance_due;
        }

        $checkInDate = $this->actual_check_in->copy()->startOfDay();
        $nowDate = $this->actual_check_out ?? now();
        
        $actualNights = $checkInDate->diffInDays($nowDate->copy()->startOfDay());
        if ($nowDate->format('H:i') >= '14:00' && $actualNights > 0) {
            $actualNights++;
        }
        $actualNights = max(1, $actualNights);

        $consumedRoomAmount = $actualNights * $this->price_per_night;
        
        $extrasAmount = $this->folioItems()
            ->whereNotIn('type', ['room', 'payment', 'discount'])
            ->where('is_complimentary', false)
            ->sum('total_price');
            
        $discountAmount = $this->folioItems()->where('type', 'discount')->sum('total_price');
        
        $subtotal = $consumedRoomAmount + $extrasAmount - $discountAmount;
        $taxAmount = 0;
        $consumedTotal = $subtotal;
        
        return max(0, $consumedTotal - $this->paid_amount);
    }

    /**
     * Vérifie si la réservation peut être modifiée
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            BookingStatus::PENDING,
            BookingStatus::CONFIRMED,
        ]);
    }

    /**
     * Relation entre Booking et FolioItem : une réservation a un folio (prestations)
     */
    public function folioItems(): HasMany
    {
        return $this->hasMany(FolioItem::class)->orderBy('occurred_at');
    }
}
