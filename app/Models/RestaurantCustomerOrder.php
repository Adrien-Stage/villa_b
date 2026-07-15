<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantCustomerOrder extends Model
{
    use HasFactory;

    // Parcours d'une commande, de la salle à la table :
    //   pending    → reçue (portail), affectée à un serveur, pas encore transmise
    //   confirmed  → bon transmis à la cuisine (déclenche la sortie du stock)
    //   preparing  → la cuisine prépare
    //   ready      → plat prêt, la cuisine signale le serveur
    //   served     → le serveur a apporté le plat à la table
    //   canceled   → annulée
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY     = 'ready';
    const STATUS_SERVED    = 'served';
    const STATUS_CANCELED  = 'canceled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_SERVED,
        self::STATUS_CANCELED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Reçue',
        self::STATUS_CONFIRMED => 'En cuisine',
        self::STATUS_PREPARING => 'En préparation',
        self::STATUS_READY     => 'Prête à servir',
        self::STATUS_SERVED    => 'Servie',
        self::STATUS_CANCELED  => 'Annulée',
    ];

    // Statuts « en cuisine » : la cuisine a le bon en main, le stock est engagé.
    public const KITCHEN_STATUSES = [
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_SERVED,
    ];

    // Statuts « actifs » : la commande occupe encore un serveur.
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
    ];

    protected $fillable = [
        'source',
        'created_by',
        'assigned_server_id',
        'table_number',
        'booking_id',
        'folio_item_id',
        'customer_name',
        'customer_phone',
        'status',
        'payment_status',
        'payment_method',
        'total_amount',
        'amount_paid',
        'notes',
        'placed_at',
        'assigned_at',
        'sent_to_kitchen_at',
        'ready_at',
        'served_at',
        'paid_at',
        'paid_by',
        'stock_deducted_at',
        'food_cost',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'amount_paid' => 'integer',
        'food_cost' => 'integer',
        'placed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'sent_to_kitchen_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'paid_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
    ];

    public function assignedServer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_server_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_server_id', $userId);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function wasSentToKitchen(): bool
    {
        return in_array($this->status, self::KITCHEN_STATUSES, true);
    }

    /**
     * Les ingrédients de cette commande ont-ils déjà été sortis du garde-manger ?
     */
    public function stockWasDeducted(): bool
    {
        return $this->stock_deducted_at !== null;
    }

    /**
     * Marge brute de la commande, en centimes FCFA. Null tant que le coût matière
     * n'a pas été figé (commande non envoyée en cuisine).
     */
    public function margin(): ?int
    {
        if ($this->food_cost === null) {
            return null;
        }

        return (int) $this->total_amount - (int) $this->food_cost;
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestaurantCustomerOrderItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
