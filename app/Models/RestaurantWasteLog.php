<?php

namespace App\Models;

use App\Models\Concerns\AppartientAUnRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * RestaurantWasteLog : Enregistrement structuré du gaspillage, des pertes et des repas non facturés en cuisine.
 *
 * Selon les normes de comptabilité matière (Oracle Materials Control / HACCP) :
 * Tout aliment sorti du stock sans vente directe doit faire l'objet d'un motif clair,
 * d'un responsable désigné et d'une valorisation exacte au coût moyen pondéré.
 */
class RestaurantWasteLog extends Model
{
    use HasFactory, AppartientAUnRestaurant;

    /** Une perte sort du stock du restaurant. */
    public const SERVICE_REQUIS = PointOfSale::SERVICE_STOCK;

    // Motifs normalisés de perte
    public const REASON_SPOILAGE = 'spoilage';
    public const REASON_BURNT = 'burnt';
    public const REASON_BREAKAGE = 'breakage';
    public const REASON_STAFF_MEAL = 'staff_meal';
    public const REASON_COMPLIMENTARY = 'complimentary';
    public const REASON_BUFFET_SURPLUS = 'buffet_surplus';
    public const REASON_INTERNAL_CONSUMPTION = 'internal_consumption';
    public const REASON_RETURN = 'return';
    public const REASON_OTHER = 'other';

    public const REASONS = [
        self::REASON_SPOILAGE,
        self::REASON_BURNT,
        self::REASON_BREAKAGE,
        self::REASON_STAFF_MEAL,
        self::REASON_COMPLIMENTARY,
        self::REASON_BUFFET_SURPLUS,
        self::REASON_INTERNAL_CONSUMPTION,
        self::REASON_RETURN,
        self::REASON_OTHER,
    ];

    public const REASON_LABELS = [
        self::REASON_SPOILAGE => 'Aliment périmé / Avarié',
        self::REASON_BURNT => 'Plat brûlé / Erreur cuisson',
        self::REASON_BREAKAGE => 'Casse / Produit renversé',
        self::REASON_STAFF_MEAL => 'Repas du personnel',
        self::REASON_COMPLIMENTARY => 'Portion offerte / Geste commercial',
        self::REASON_BUFFET_SURPLUS => 'Surplus de buffet non récupérable',
        self::REASON_INTERNAL_CONSUMPTION => 'Dégustation / Test interne',
        self::REASON_RETURN => 'Retour client / Non conforme',
        self::REASON_OTHER => 'Autre perte',
    ];

    // Départements / Ateliers
    public const DEPT_KITCHEN = 'cuisine';
    public const DEPT_PASTRY = 'patisserie';
    public const DEPT_BAR = 'bar';
    public const DEPT_RESTAURANT = 'restaurant';
    public const DEPT_COLD = 'garde_manger';

    public const DEPARTMENT_LABELS = [
        self::DEPT_KITCHEN => 'Cuisine chaude',
        self::DEPT_PASTRY => 'Pâtisserie / Boulangerie',
        self::DEPT_BAR => 'Bar / Boissons',
        self::DEPT_RESTAURANT => 'Salle restaurant',
        self::DEPT_COLD => 'Garde-manger / Cuisine froide',
    ];

    protected $fillable = [
        'point_of_sale_id',
        'reference',
        'restaurant_pantry_item_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'reason',
        'department',
        'responsible_person',
        'notes',
        'recorded_by',
        'tenant_id',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(RestaurantPantryItem::class, 'restaurant_pantry_item_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function movement(): HasOne
    {
        return $this->hasOne(RestaurantPantryMovement::class, 'restaurant_waste_log_id');
    }

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? ucfirst((string) $this->reason);
    }

    public function departmentLabel(): string
    {
        return self::DEPARTMENT_LABELS[$this->department] ?? ucfirst((string) $this->department);
    }

    /**
     * Coût unitaire en FCFA.
     */
    public function unitCostFcfa(): float
    {
        return (float) $this->unit_cost / 100;
    }

    /**
     * Coût total en FCFA.
     */
    public function totalCostFcfa(): float
    {
        return (float) $this->total_cost / 100;
    }

    /**
     * Total formaté en FCFA pour affichage propre.
     */
    public function formattedTotalCost(): string
    {
        return number_format($this->totalCostFcfa(), 0, ',', ' ') . ' FCFA';
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
    }
}
