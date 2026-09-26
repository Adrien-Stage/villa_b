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
        'partner_organization_id', // Convention appliquée à CE séjour
        'room_package_id',         // Formule d'hébergement retenue
        'group_booking_id',     // Null si individuel
        'booking_number',       // Numéro unique affiché (VB-2025-0001)
        'status',

        // Gratuité : fait comptable à part entière, pas une mention libre
        'is_complimentary',
        'complimentary_reason',
        'complimentary_value',  // Manque à gagner : valeur du séjour au tarif applicable

        // Dates
        'check_in',
        'check_in_time',
        'check_out',
        'actual_check_in',      // Heure réelle d'arrivée
        'actual_check_out',     // Heure réelle de départ
        'checkin_code',         // Code OTP pour la sécurité du check-in
        'code_recipient',       // « customer » ou « booker » : à qui le code est adressé
        'checkin_attempts',     // Nombre de tentatives échouées

        // Personnes
        'adults_count',
        'children_count',
        'children_ages',
        'has_extra_bed',
        'extra_bed_count',
        'extra_bed_amount',
        'prepaid_breakfast_children',
        'prepaid_breakfast_amount',

        // Politique d'annulation
        'cancellation_policy_id',
        'cancellation_policy_snapshot',
        'free_cancel_until',

        // Tarification
        'total_nights',
        'price_per_night',      // Prix appliqué (peut différer du tarif base)
        'total_room_amount',    // total_nights * price_per_night
        'extras_amount',        // Restaurant, minibar...
        'package_amount',       // Formule figée au moment de la réservation
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
        'created_by',
        'checked_in_by',
        'checked_out_by',
        'approved_by',          // Qui a validé la gratuité
        'approved_at',
        'tenant_id',
    ];

    protected $casts = [
        'status' => BookingStatus::class,
        'is_complimentary' => 'boolean',
        'children_ages' => 'array',
        'has_extra_bed' => 'boolean',
        'extra_bed_count' => 'integer',
        'extra_bed_amount' => 'integer',
        'prepaid_breakfast_children' => 'boolean',
        'prepaid_breakfast_amount' => 'integer',
        'complimentary_value' => 'integer',
        'approved_at' => 'datetime',
        'check_in' => 'date',
        'check_out' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'price_per_night' => 'integer',
        'total_room_amount' => 'integer',
        'extras_amount' => 'integer',
        'package_amount' => 'integer',
        'tax_amount' => 'integer',
        'cancellation_policy_snapshot' => 'array',
        'free_cancel_until' => 'datetime',
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

            if (empty($booking->cancellation_policy_id) && class_exists(CancellationPolicy::class)) {
                $defaultPolicy = CancellationPolicy::getDefault();
                if ($defaultPolicy) {
                    $booking->cancellation_policy_id = $defaultPolicy->id;
                    if (empty($booking->cancellation_policy_snapshot)) {
                        $booking->cancellation_policy_snapshot = $defaultPolicy->toSnapshot();
                    }
                    if (empty($booking->free_cancel_until) && $booking->check_in) {
                        $booking->free_cancel_until = $defaultPolicy->calculateFreeCancelUntil($booking->check_in);
                    }
                }
            }
        });
    }

    /**
     * Génère un numéro unique : VB-2026-000001
     */
    public static function generateBookingNumber(): string
    {
        $prefix = 'VB';
        $year = now()->year;
        $pattern = sprintf('%s-%d-%%', $prefix, $year);

        $lastBooking = self::withoutGlobalScopes()
            ->where('booking_number', 'like', $pattern)
            ->orderBy('booking_number', 'desc')
            ->first();

        $sequence = 1;
        if ($lastBooking && preg_match('/-(\d+)$/', $lastBooking->booking_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        do {
            $number = sprintf('%s-%d-%06d', $prefix, $year, $sequence);
            $sequence++;
        } while (self::withoutGlobalScopes()->where('booking_number', $number)->exists());

        return $number;
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

    /**
     * Convention partenaire appliquée à ce séjour. Peut différer de celle du
     * client : un membre peut séjourner à titre privé, ou pour le compte d'une
     * autre organisation.
     */
    public function partnerOrganization(): BelongsTo
    {
        return $this->belongsTo(PartnerOrganization::class);
    }

    /** Formule d'hébergement retenue pour ce séjour, s'il y en a une. */
    public function roomPackage(): BelongsTo
    {
        return $this->belongsTo(RoomPackage::class);
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

    public function breakfastEntitlements(): HasMany
    {
        return $this->hasMany(BreakfastEntitlement::class);
    }

    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(BookingCancellation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceLabel(): string
    {
        $sourceLabels = [
            'direct'         => 'Direct',
            'phone'          => 'Téléphone',
            'email'          => 'Email',
            'walk_in'        => 'Walk-in',
            'ota_bookingcom' => 'Booking.com',
            'website'        => 'Site web',
            'group'          => 'Groupe',
        ];

        return $sourceLabels[$this->source] ?? ucfirst(str_replace('_', ' ', $this->source ?? 'direct'));
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
            + $this->package_amount
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
