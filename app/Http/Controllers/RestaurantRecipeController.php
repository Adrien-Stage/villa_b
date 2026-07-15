<?php

namespace App\Http\Controllers;

use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Services\RestaurantStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Les fiches techniques : la nomenclature de chaque plat, et les préparations de
 * base fabriquées en batch. C'est ce qui relie le menu au garde-manger.
 */
class RestaurantRecipeController extends Controller
{
    public function __construct(private readonly RestaurantStockService $stock)
    {
    }

    public function index(Request $request): View
    {
        $recipes = RestaurantRecipe::query()
            ->with(['lines.item', 'menuItem', 'producedItem'])
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $dishes = $recipes->where('type', RestaurantRecipe::TYPE_DISH);
        $preparations = $recipes->where('type', RestaurantRecipe::TYPE_PREP);

        // Plats du menu qui n'ont pas encore de fiche : leur vente ne décrémente rien.
        $unfichedItems = RestaurantMenuItem::query()
            ->active()
            ->whereDoesntHave('recipe')
            ->orderBy('name')
            ->get();

        $pantryItems = RestaurantPantryItem::query()
            ->active()
            ->with('category')
            ->orderBy('name')
            ->get();

        // Un plat sans fiche est sélectionnable ; un plat déjà fiché ne l'est plus.
        $availableMenuItems = $unfichedItems;

        // Une préparation produit un article de garde-manger « fabriqué » qui n'a pas
        // encore sa propre fiche.
        $availablePreparedItems = RestaurantPantryItem::query()
            ->active()
            ->where('is_prepared', true)
            ->whereDoesntHave('recipe')
            ->orderBy('name')
            ->get();

        $canManage = Auth::user()->hasRole('restaurant_chief');

        return view('restaurant.recipes.index', [
            'dishes' => $dishes,
            'preparations' => $preparations,
            'unfichedItems' => $unfichedItems,
            'pantryItems' => $pantryItems,
            'availableMenuItems' => $availableMenuItems,
            'availablePreparedItems' => $availablePreparedItems,
            'canManage' => $canManage,
            'stockService' => $this->stock,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $recipe = DB::transaction(function () use ($validated, $request) {
            $recipe = RestaurantRecipe::create($this->attributes($validated, $request));
            $this->syncLines($recipe, $validated['lines'] ?? []);

            return $recipe;
        });

        return redirect()
            ->route('restaurant.recipes.index')
            ->with('success', "Fiche technique « {$recipe->name} » créée.");
    }

    public function update(Request $request, RestaurantRecipe $recipe): RedirectResponse
    {
        $validated = $this->validated($request, $recipe);

        DB::transaction(function () use ($recipe, $validated, $request) {
            $recipe->update($this->attributes($validated, $request));
            $this->syncLines($recipe, $validated['lines'] ?? []);
        });

        return redirect()
            ->route('restaurant.recipes.index')
            ->with('success', "Fiche technique « {$recipe->name} » mise à jour.");
    }

    public function destroy(RestaurantRecipe $recipe): RedirectResponse
    {
        $recipe->delete();

        return redirect()
            ->route('restaurant.recipes.index')
            ->with('success', 'Fiche technique supprimée.');
    }

    /**
     * Fabrique un batch d'une préparation de base : les ingrédients sortent, la
     * préparation entre en stock à son coût de revient réel.
     */
    public function produce(Request $request, RestaurantRecipe $recipe): RedirectResponse
    {
        $validated = $request->validate([
            'batches' => ['required', 'numeric', 'gt:0', 'max:1000'],
        ]);

        try {
            $item = $this->stock->produce($recipe, (float) $validated['batches']);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('restaurant.recipes.index')
                ->withErrors(['production' => $e->getMessage()]);
        }

        $produced = $recipe->yield() * (float) $validated['batches'];

        return redirect()
            ->route('restaurant.recipes.index')
            ->with('success', sprintf(
                'Production enregistrée : %s %s de %s. Stock : %s %s.',
                rtrim(rtrim(number_format($produced, 3, ',', ' '), '0'), ','),
                $item->unit,
                $recipe->name,
                rtrim(rtrim(number_format((float) $item->current_stock, 3, ',', ' '), '0'), ','),
                $item->unit,
            ));
    }

    private function validated(Request $request, ?RestaurantRecipe $recipe = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'type' => ['required', Rule::in(array_keys(RestaurantRecipe::TYPES))],
            'restaurant_menu_item_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_menu_items', 'id'),
                Rule::unique('restaurant_recipes', 'restaurant_menu_item_id')->ignore($recipe?->id),
            ],
            'produces_pantry_item_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_pantry_items', 'id'),
                Rule::unique('restaurant_recipes', 'produces_pantry_item_id')->ignore($recipe?->id),
            ],
            'yield_quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'lines' => ['nullable', 'array', 'max:60'],
            'lines.*.restaurant_pantry_item_id' => ['required', 'integer', Rule::exists('restaurant_pantry_items', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'lines.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ], [
            'restaurant_menu_item_id.unique' => 'Ce plat a déjà une fiche technique.',
            'produces_pantry_item_id.unique' => 'Cet article est déjà produit par une autre fiche.',
        ]);

        // Une fiche de plat doit viser un plat ; une préparation doit produire un article.
        if ($validated['type'] === RestaurantRecipe::TYPE_DISH && empty($validated['restaurant_menu_item_id'])) {
            throw ValidationException::withMessages([
                'restaurant_menu_item_id' => 'Choisis le plat du menu auquel cette fiche correspond.',
            ]);
        }

        if ($validated['type'] === RestaurantRecipe::TYPE_PREP && empty($validated['produces_pantry_item_id'])) {
            throw ValidationException::withMessages([
                'produces_pantry_item_id' => 'Choisis l\'article de garde-manger que cette préparation fabrique.',
            ]);
        }

        // Une préparation ne peut pas se consommer elle-même.
        if ($validated['type'] === RestaurantRecipe::TYPE_PREP) {
            foreach ($validated['lines'] ?? [] as $line) {
                if ((int) $line['restaurant_pantry_item_id'] === (int) $validated['produces_pantry_item_id']) {
                    throw ValidationException::withMessages([
                        'lines' => 'Une préparation ne peut pas figurer parmi ses propres ingrédients.',
                    ]);
                }
            }
        }

        return $validated;
    }

    private function attributes(array $validated, Request $request): array
    {
        $isDish = $validated['type'] === RestaurantRecipe::TYPE_DISH;

        return [
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'restaurant_menu_item_id' => $isDish ? $validated['restaurant_menu_item_id'] : null,
            'produces_pantry_item_id' => $isDish ? null : $validated['produces_pantry_item_id'],
            'yield_quantity' => $validated['yield_quantity'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    /**
     * Remplace les lignes de la fiche par celles du formulaire. Les doublons sont
     * fusionnés : un ingrédient saisi deux fois ne fait qu'une ligne.
     */
    private function syncLines(RestaurantRecipe $recipe, array $lines): void
    {
        $recipe->lines()->delete();

        $merged = [];

        foreach ($lines as $line) {
            $itemId = (int) $line['restaurant_pantry_item_id'];

            if (!isset($merged[$itemId])) {
                $merged[$itemId] = [
                    'restaurant_recipe_id' => $recipe->id,
                    'restaurant_pantry_item_id' => $itemId,
                    'quantity' => 0,
                    'waste_percent' => (float) ($line['waste_percent'] ?? 0),
                    'notes' => $line['notes'] ?? null,
                ];
            }

            $merged[$itemId]['quantity'] += (float) $line['quantity'];
        }

        foreach ($merged as $attributes) {
            RestaurantRecipeLine::create($attributes);
        }
    }
}
