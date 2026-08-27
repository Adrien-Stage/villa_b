<?php

namespace App\Http\Controllers;

use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantMenuController extends Controller
{
    private const ITEM_TYPES = ['food', 'drink', 'other'];

    public function index(Request $request): View
    {
        $user = Auth::user();

        // Le serveur en salle ne vient pas administrer la carte : il vient y
        // prendre une commande. Même route, écran adapté au geste du métier.
        if ($user->hasAnyRole(['restaurant_staff']) && !$user->hasAnyRole(['restaurant_chief', 'manager'])) {
            return $this->priseDeCommande();
        }

        $categories = RestaurantMenuCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('items')
            ->get();

        $itemsQuery = RestaurantMenuItem::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $itemsQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $itemsQuery->where('restaurant_menu_category_id', (int) $request->input('category'));
        }

        if ($request->filled('type')) {
            $itemsQuery->where('type', (string) $request->input('type'));
        }

        if ($request->filled('meal') && array_key_exists($request->input('meal'), RestaurantMenuItem::MEAL_SERVICES)) {
            $itemsQuery->whereJsonContains('meal_services', (string) $request->input('meal'));
        }

        if ($request->filled('status')) {
            $itemsQuery->where('is_active', (string) $request->input('status') === 'active');
        }

        $items = $itemsQuery->paginate(15)->withQueryString();

        $canManage = $user->hasAnyRole(['restaurant_chief']);

        return view('restaurant.menus.index', [
            'categories' => $categories,
            'items' => $items,
            'canManage' => $canManage,
            'itemTypes' => self::ITEM_TYPES,
            'mealServices' => RestaurantMenuItem::MEAL_SERVICES,
        ]);
    }

    /**
     * Écran de prise de commande en salle : la carte du jour en vignettes,
     * un panier, et l'envoi en cuisine dans le même geste.
     *
     * Seuls les articles actifs sortent, et les catégories vides sont
     * écartées : sur une tablette, un filtre qui ne renvoie rien est une
     * fausse piste au milieu du service.
     */
    private function priseDeCommande(): View
    {
        $items = RestaurantMenuItem::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = RestaurantMenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($categorie) => $items->contains('restaurant_menu_category_id', $categorie->id))
            ->values();

        return view('restaurant.menus.service', [
            'items' => $items,
            'categories' => $categories,
            'mealServices' => RestaurantMenuItem::MEAL_SERVICES,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_menu_categories', 'name')->where(fn ($q) => $q),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RestaurantMenuCategory::create([
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Categorie creee avec succes.');
    }

    public function updateCategory(Request $request, RestaurantMenuCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_menu_categories', 'name')
                    ->ignore($category->id)
                    ->where(fn ($q) => $q),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Categorie modifiee avec succes.');
    }

    public function destroyCategory(RestaurantMenuCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return redirect()
                ->route('restaurant.menus.index')
                ->withErrors(['category' => 'Impossible de supprimer: cette categorie contient encore des articles.']);
        }

        $category->delete();

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Categorie supprimee.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_menu_category_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_menu_categories', 'id')->where(fn ($q) => $q),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_menu_items', 'name')->where(fn ($q) => $q),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Saisi en FCFA -> stockage en centimes
            'price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'type' => ['required', Rule::in(self::ITEM_TYPES)],
            'meal_services' => ['nullable', 'array'],
            'meal_services.*' => [Rule::in(array_keys(RestaurantMenuItem::MEAL_SERVICES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Aucun service coché = article proposé toute la journée
        $mealServices = array_values(array_intersect(
            array_keys(RestaurantMenuItem::MEAL_SERVICES),
            $validated['meal_services'] ?? []
        ));
        if ($mealServices === []) {
            $mealServices = array_keys(RestaurantMenuItem::MEAL_SERVICES);
        }

        $item = RestaurantMenuItem::create([
            'restaurant_menu_category_id' => $validated['restaurant_menu_category_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => (int) $validated['price'] * 100,
            'type' => (string) $validated['type'],
            'meal_services' => $mealServices,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('restaurant_menu', 'public');
            $item->update(['image_path' => $path]);
        }

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Article cree avec succes.');
    }

    public function updateItem(Request $request, RestaurantMenuItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_menu_category_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_menu_categories', 'id')->where(fn ($q) => $q),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_menu_items', 'name')
                    ->ignore($item->id)
                    ->where(fn ($q) => $q),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Saisi en FCFA -> stockage en centimes
            'price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'type' => ['required', Rule::in(self::ITEM_TYPES)],
            'meal_services' => ['nullable', 'array'],
            'meal_services.*' => [Rule::in(array_keys(RestaurantMenuItem::MEAL_SERVICES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        // Aucun service coché = article proposé toute la journée
        $mealServices = array_values(array_intersect(
            array_keys(RestaurantMenuItem::MEAL_SERVICES),
            $validated['meal_services'] ?? []
        ));
        if ($mealServices === []) {
            $mealServices = array_keys(RestaurantMenuItem::MEAL_SERVICES);
        }

        $item->update([
            'restaurant_menu_category_id' => $validated['restaurant_menu_category_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => (int) $validated['price'] * 100,
            'type' => (string) $validated['type'],
            'meal_services' => $mealServices,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $path = $request->file('image')->store('restaurant_menu', 'public');
            $item->update(['image_path' => $path]);
        } elseif ($request->input('remove_image') == '1') {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $item->update(['image_path' => null]);
        }

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Article modifie avec succes.');
    }

    public function destroyItem(RestaurantMenuItem $item): RedirectResponse
    {
        $used = RestaurantOrderItem::query()
            ->withoutGlobalScopes()
            
            ->where('menu_item_id', $item->id)
            ->exists();

        if ($used) {
            return redirect()
                ->route('restaurant.menus.index')
                ->withErrors(['item' => 'Cet article est deja utilise dans des commandes. Desactive-le au lieu de le supprimer.']);
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Article supprime.');
    }
}
