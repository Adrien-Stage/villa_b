<?php

namespace App\Http\Controllers;

use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantMenuController extends Controller
{
    private const ITEM_TYPES = ['food', 'drink', 'other'];

    public function index(Request $request): View
    {
        $user = Auth::user();

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
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_menu_categories', 'name')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RestaurantMenuCategory::create([
            'tenant_id' => Auth::user()->tenant_id,
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
                    ->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
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
                Rule::exists('restaurant_menu_categories', 'id')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_menu_items', 'name')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Saisi en FCFA -> stockage en centimes
            'price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'type' => ['required', Rule::in(self::ITEM_TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RestaurantMenuItem::create([
            'tenant_id' => Auth::user()->tenant_id,
            'restaurant_menu_category_id' => $validated['restaurant_menu_category_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => (int) $validated['price'] * 100,
            'type' => (string) $validated['type'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

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
                Rule::exists('restaurant_menu_categories', 'id')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_menu_items', 'name')
                    ->ignore($item->id)
                    ->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Saisi en FCFA -> stockage en centimes
            'price' => ['required', 'integer', 'min:0', 'max:5000000'],
            'type' => ['required', Rule::in(self::ITEM_TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update([
            'restaurant_menu_category_id' => $validated['restaurant_menu_category_id'] ?? null,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'price' => (int) $validated['price'] * 100,
            'type' => (string) $validated['type'],
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Article modifie avec succes.');
    }

    public function destroyItem(RestaurantMenuItem $item): RedirectResponse
    {
        $used = RestaurantOrderItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('menu_item_id', $item->id)
            ->exists();

        if ($used) {
            return redirect()
                ->route('restaurant.menus.index')
                ->withErrors(['item' => 'Cet article est deja utilise dans des commandes. Desactive-le au lieu de le supprimer.']);
        }

        $item->delete();

        return redirect()
            ->route('restaurant.menus.index')
            ->with('success', 'Article supprime.');
    }
}
