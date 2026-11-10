<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un banquet : un événement réservé dans l'un des restaurants — date, salle,
 * client, nombre de couverts, menu, acompte —, réalisé par sa cuisine et
 * réglé à sa caisse.
 */
class RestaurantBanquet extends Model
{
    use AppartientAUnRestaurant;

    public const DEVIS = 'devis';

    public const CONFIRME = 'confirme';

    public const REALISE = 'realise';

    public const SOLDE = 'solde';

    public const ANNULE = 'annule';

    public const SOURCE_SUR_PLACE = 'sur_place';

    public const SOURCE_TELEPHONE = 'telephone';

    public const SOURCE_EMAIL = 'email';

    public const SOURCE_RESTAURANT = 'restaurant';

    public const SOURCE_SITE_WEB = 'site_web';

    public const SOURCES = [
        self::SOURCE_SUR_PLACE => 'Sur place (Réception)',
        self::SOURCE_TELEPHONE => 'Téléphone',
        self::SOURCE_EMAIL => 'Email',
        self::SOURCE_RESTAURANT => 'Restaurant',
        self::SOURCE_SITE_WEB => 'Site web',
    ];

    public const STATUTS = [
        self::DEVIS => 'Devis',
        self::CONFIRME => 'Confirmé',
        self::REALISE => 'Réalisé',
        self::SOLDE => 'Soldé',
        self::ANNULE => 'Annulé',
    ];

    protected $fillable = [
        'point_of_sale_id', 'reference', 'title', 'customer_id', 'booking_id', 'client_name', 'client_phone', 'client_email',
        'event_date', 'start_time', 'end_time', 'space_id', 'covers', 'price_per_cover', 'extras_amount',
        'total_amount', 'deposit_required', 'menu', 'status', 'source', 'notes', 'created_by', 'confirmed_at', 'canceled_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'covers' => 'integer',
        'price_per_cover' => 'integer',
        'extras_amount' => 'integer',
        'total_amount' => 'integer',
        'deposit_required' => 'integer',
        'confirmed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(RestaurantBanquetPayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Montant dû : couverts au prix convenu, plus les suppléments. */
    public static function totalPour(int $couverts, int $prixParCouvert, int $supplements = 0): int
    {
        return $couverts * $prixParCouvert + $supplements;
    }

    public function encaisse(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function resteDu(): int
    {
        return max(0, $this->total_amount - $this->encaisse());
    }

    public function depositRemaining(): int
    {
        return max(0, (int) $this->deposit_required - $this->encaisse());
    }

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->status] ?? $this->status;
    }

    public function libelleSource(): string
    {
        return self::SOURCES[$this->source ?? self::SOURCE_RESTAURANT] ?? ucfirst((string) $this->source);
    }

    public function estModifiable(): bool
    {
        return in_array($this->status, [self::DEVIS, self::CONFIRME], true);
    }
}
