<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * RoomPackage : formule vendue avec la chambre (demi-pension, pension
 * complète, séjour affaires avec blanchisserie…).
 *
 * Regroupe des repas et des prestations facturés forfaitairement, en principe
 * moins cher que la somme des éléments pris séparément.
 *
 * Tous les montants sont en centimes FCFA.
 */
class RoomPackage extends Model
{
    use HasFactory;

    public const MODE_PER_PERSON_NIGHT = 'per_person_night';
    public const MODE_PER_ROOM_NIGHT   = 'per_room_night';
    public const MODE_PER_STAY         = 'per_stay';

    public const PRICING_MODES = [
        self::MODE_PER_PERSON_NIGHT => 'Par personne et par nuitée',
        self::MODE_PER_ROOM_NIGHT   => 'Par chambre et par nuitée',
        self::MODE_PER_STAY         => 'Forfait pour le séjour',
    ];

    public const DISCOUNT_NONE    = 'none';
    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_AMOUNT  = 'amount';

    protected $fillable = [
        'name', 'code', 'description',
        'meals', 'service_item_ids',
        'pricing_mode', 'price',
        'room_discount_type', 'room_discount_value',
        'room_type_ids', 'sort_order', 'is_active', 'tenant_id',
    ];

    protected $casts = [
        'meals'               => 'array',
        'service_item_ids'    => 'array',
        'room_type_ids'       => 'array',
        'price'               => 'integer',
        'room_discount_value' => 'integer',
        'sort_order'          => 'integer',
        'is_active'           => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Portée ───────────────────────────────────────────────────────────────

    /**
     * Le pack est-il proposable pour ce type de chambre ? Une liste vide vaut
     * « tous les types », pour ne pas avoir à tout re-cocher à la création.
     */
    public function appliesToRoomType(?int $roomTypeId): bool
    {
        $ids = $this->room_type_ids ?: [];

        return empty($ids) || in_array($roomTypeId, array_map('intval', $ids), true);
    }

    // ── Tarification ─────────────────────────────────────────────────────────

    /**
     * Montant facturé pour un séjour donné, selon le mode de tarification.
     *
     * @param  int  $nights    Nombre de nuitées.
     * @param  int  $occupants Nombre de personnes (adultes + enfants).
     * @return int             Montant en centimes.
     */
    public function amountFor(int $nights, int $occupants = 1): int
    {
        $nights    = max(0, $nights);
        $occupants = max(1, $occupants);

        return match ($this->pricing_mode) {
            self::MODE_PER_PERSON_NIGHT => $this->price * $nights * $occupants,
            self::MODE_PER_ROOM_NIGHT   => $this->price * $nights,
            self::MODE_PER_STAY         => $nights > 0 ? $this->price : 0,
            default                     => 0,
        };
    }

    /**
     * Remise consentie sur l'hébergement lorsque ce pack est retenu.
     *
     * @param  int  $grossRoomAmount  Hébergement brut, en centimes.
     * @param  int  $nights           Nuitées (remise « au montant » = par nuitée).
     */
    public function roomDiscountFor(int $grossRoomAmount, int $nights = 1): int
    {
        if ($grossRoomAmount <= 0) {
            return 0;
        }

        $discount = match ($this->room_discount_type) {
            self::DISCOUNT_PERCENT => (int) round($grossRoomAmount * min(100, $this->room_discount_value) / 100),
            self::DISCOUNT_AMOUNT  => $this->room_discount_value * max(1, $nights),
            default                => 0,
        };

        return max(0, min($discount, $grossRoomAmount));
    }

    // ── Composition ──────────────────────────────────────────────────────────

    /** Prestations du catalogue incluses dans la formule. */
    public function serviceItems(): Collection
    {
        $ids = $this->service_item_ids ?: [];

        return empty($ids)
            ? collect()
            : ServiceItem::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Valeur des éléments inclus s'ils étaient achetés séparément, pour une
     * personne et une nuitée. Sert à mesurer l'intérêt de la formule.
     *
     * Les repas n'ont pas de prix fixe (la carte varie) : on retient le plat
     * le moins cher servi à ce repas, ce qui donne une estimation prudente,
     * jamais surévaluée.
     */
    public function aLaCarteValue(): int
    {
        $total = 0;

        foreach (($this->meals ?: []) as $meal) {
            $cheapest = RestaurantMenuItem::query()
                ->active()
                ->get()
                ->filter(fn ($item) => $item->isServedAt($meal))
                ->min('price');

            $total += (int) ($cheapest ?? 0);
        }

        $total += (int) $this->serviceItems()->sum('price');

        return $total;
    }

    // ── Affichage ────────────────────────────────────────────────────────────

    public function pricingModeLabel(): string
    {
        return self::PRICING_MODES[$this->pricing_mode] ?? '—';
    }

    /** Libellés des repas inclus, ex. « Petit déjeuner · Dîner ». */
    public function mealsLabel(): string
    {
        $meals = $this->meals ?: [];

        if (empty($meals)) {
            return '';
        }

        $labels = array_map(
            fn (string $meal) => RestaurantMenuItem::MEAL_SERVICES[$meal] ?? $meal,
            array_values(array_intersect(array_keys(RestaurantMenuItem::MEAL_SERVICES), $meals))
        );

        return implode(' · ', $labels);
    }

    public function roomDiscountLabel(): string
    {
        return match ($this->room_discount_type) {
            self::DISCOUNT_PERCENT => $this->room_discount_value . ' % sur l\'hébergement',
            self::DISCOUNT_AMOUNT  => number_format($this->room_discount_value / 100, 0, ',', ' ') . ' FCFA par nuitée',
            default                => '',
        };
    }

    /**
     * Résumé de ce que contient la formule, pour l'affichage en liste.
     *
     * @return array<int, string>
     */
    public function contentLabels(): array
    {
        $labels = [];

        if ($meals = $this->mealsLabel()) {
            $labels[] = $meals;
        }

        foreach ($this->serviceItems() as $service) {
            $labels[] = $service->name;
        }

        if ($discount = $this->roomDiscountLabel()) {
            $labels[] = $discount;
        }

        return $labels;
    }
}
