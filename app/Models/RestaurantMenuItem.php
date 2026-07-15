<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantMenuItem extends Model
{
    use HasFactory;

    const MEAL_BREAKFAST = 'breakfast';
    const MEAL_LUNCH     = 'lunch';
    const MEAL_DINNER    = 'dinner';

    /**
     * Services de repas → libellé affiché. L'ordre est celui de la journée.
     */
    public const MEAL_SERVICES = [
        self::MEAL_BREAKFAST => 'Petit déjeuner',
        self::MEAL_LUNCH     => 'Déjeuner',
        self::MEAL_DINNER    => 'Dîner',
    ];

    protected $fillable = [
        'restaurant_menu_category_id',
        'name',
        'description',
        'image_path',
        'price',
        'type',
        'meal_services',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'integer',
        'meal_services' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RestaurantMenuCategory::class, 'restaurant_menu_category_id');
    }

    /**
     * La fiche technique du plat : ce qui sort du garde-manger à chaque vente.
     */
    public function recipe(): HasOne
    {
        return $this->hasOne(RestaurantRecipe::class, 'restaurant_menu_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Services auxquels l'article est proposé. Un article sans service défini
     * est considéré disponible toute la journée.
     */
    public function mealServices(): array
    {
        $services = $this->meal_services;

        if (!is_array($services) || $services === []) {
            return array_keys(self::MEAL_SERVICES);
        }

        return array_values(array_intersect(array_keys(self::MEAL_SERVICES), $services));
    }

    public function isServedAt(string $service): bool
    {
        return in_array($service, $this->mealServices(), true);
    }

    /**
     * Libellés des services, ex : "Petit déjeuner · Dîner".
     */
    public function mealServicesLabel(): string
    {
        $services = $this->mealServices();

        if (count($services) === count(self::MEAL_SERVICES)) {
            return 'Toute la journée';
        }

        return implode(' · ', array_map(
            fn (string $service) => self::MEAL_SERVICES[$service],
            $services
        ));
    }
}

