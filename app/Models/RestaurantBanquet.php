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

    public const STATUTS = [
        self::DEVIS => 'Devis',
        self::CONFIRME => 'Confirmé',
        self::REALISE => 'Réalisé',
        self::SOLDE => 'Soldé',
        self::ANNULE => 'Annulé',
    ];

    protected $fillable = [
        'point_of_sale_id', 'reference', 'title', 'customer_id', 'client_name', 'client_phone', 'client_email',
        'event_date', 'start_time', 'end_time', 'space_id', 'covers', 'price_per_cover', 'extras_amount',
        'total_amount', 'deposit_required', 'menu', 'status', 'notes', 'created_by', 'confirmed_at', 'canceled_at',
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

    public function libelleStatut(): string
    {
        return self::STATUTS[$this->status] ?? $this->status;
    }

    public function estModifiable(): bool
    {
        return in_array($this->status, [self::DEVIS, self::CONFIRME], true);
    }
}
