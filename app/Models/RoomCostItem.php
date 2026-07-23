<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de la fiche technique d'un type de chambre : un poste de coût
 * (électricité, eau, savonnette, blanchisserie…) avec sa base de calcul.
 *
 * Montants en centimes FCFA.
 */
class RoomCostItem extends Model
{
    use HasFactory;

    // Catégories — servent au regroupement et aux sous-totaux de la fiche.
    public const CATEGORIES = [
        'energy'       => 'Électricité',
        'water'        => 'Eau',
        'consumable'   => 'Consommables',
        'linen'        => 'Blanchisserie & linge',
        'housekeeping' => 'Main-d’œuvre ménage',
        'maintenance'  => 'Entretien',
        'amenity'      => 'Accueil / agréments',
        'other'        => 'Autre',
    ];

    // Base de calcul.
    public const BASIS_PER_NIGHT       = 'per_night';
    public const BASIS_PER_GUEST_NIGHT = 'per_guest_night';
    public const BASIS_PER_STAY        = 'per_stay';

    public const BASES = [
        self::BASIS_PER_NIGHT       => 'Par nuitée',
        self::BASIS_PER_GUEST_NIGHT => 'Par personne et nuitée',
        self::BASIS_PER_STAY        => 'Par séjour',
    ];

    protected $fillable = [
        'room_type_id', 'category', 'label', 'basis',
        'quantity', 'unit_cost', 'stock_item_id',
        'sort_order', 'is_active', 'notes', 'tenant_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_cost'  => 'integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Coût ─────────────────────────────────────────────────────────────────

    /**
     * Prix unitaire effectif : le coût moyen pondéré de l'article d'économat
     * lié s'il existe (les consommables suivent ainsi les prix d'achat réels),
     * sinon le prix saisi sur la ligne.
     */
    public function effectiveUnitCost(): int
    {
        if ($this->stock_item_id && $this->stockItem) {
            return (int) $this->stockItem->average_cost;
        }

        return (int) $this->unit_cost;
    }

    /**
     * Coût de la ligne ramené à une nuitée occupée, en centimes.
     *
     * @param  int    $occupants  Personnes de référence (pour la base personne-nuitée).
     * @param  float  $avgNights  Durée moyenne de séjour (pour amortir la base séjour).
     */
    public function costPerNight(int $occupants, float $avgNights): float
    {
        $base = (float) $this->quantity * $this->effectiveUnitCost();

        return match ($this->basis) {
            self::BASIS_PER_GUEST_NIGHT => $base * max(1, $occupants),
            // Un coût « par séjour » (ex. lavage complet du linge au départ) est
            // réparti sur la durée moyenne de séjour pour être comparé à l'ADR,
            // qui est un montant par nuitée.
            self::BASIS_PER_STAY        => $avgNights > 0 ? $base / $avgNights : $base,
            default                     => $base,
        };
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Autre';
    }

    public function basisLabel(): string
    {
        return self::BASES[$this->basis] ?? $this->basis;
    }
}
