<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantMenuCategoryResource;
use App\Models\RestaurantMenuCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * API publique (site vitrine) : menu du restaurant.
 * Route déjà protégée par le middleware `module:restaurant` (routes/api.php) —
 * indisponible si ce module est désactivé pour l'établissement.
 */
class PublicRestaurantMenuController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = RestaurantMenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['items' => function ($query) {
                $query->where('is_active', true)->orderBy('sort_order');
            }])
            ->get()
            ->filter(fn (RestaurantMenuCategory $category) => $category->items->isNotEmpty())
            ->values();

        return RestaurantMenuCategoryResource::collection($categories);
    }
}
