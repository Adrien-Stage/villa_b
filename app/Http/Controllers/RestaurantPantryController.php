<?php

namespace App\Http\Controllers;

use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantPantryController extends Controller
{
    private const UNITS = ['pcs', 'kg', 'g', 'l', 'ml'];
    private const MOVE_TYPES = ['in', 'out', 'adjust'];
    private const MOVE_REASONS = ['purchase', 'kitchen', 'waste', 'correction', 'other'];

    public function index(Request $request): View
    {
        $categories = RestaurantPantryCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->withCount('items')
            ->get();

        $itemsQuery = RestaurantPantryItem::query()
            ->with('category')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $itemsQuery->where('name', 'ilike', "%{$search}%");
        }

        if ($request->filled('category')) {
            $itemsQuery->where('restaurant_pantry_category_id', (int) $request->input('category'));
        }

        if ($request->filled('status')) {
            $itemsQuery->where('is_active', (string) $request->input('status') === 'active');
        }

        if ($request->filled('low')) {
            $itemsQuery->whereColumn('current_stock', '<=', 'min_stock');
        }

        $items = $itemsQuery->paginate(20)->withQueryString();

        $recentMovements = RestaurantPantryMovement::query()
            ->with(['item', 'recordedBy'])
            ->latest('occurred_at')
            ->take(20)
            ->get();

        $canManage = Auth::user()->hasAnyRole(['restaurant_chief']);

        $stats = [
            'total_items' => RestaurantPantryItem::query()->count(),
            'low_stock' => RestaurantPantryItem::query()->whereColumn('current_stock', '<=', 'min_stock')->count(),
        ];

        return view('restaurant.pantry.index', [
            'categories' => $categories,
            'items' => $items,
            'recentMovements' => $recentMovements,
            'stats' => $stats,
            'canManage' => $canManage,
            'units' => self::UNITS,
            'moveTypes' => self::MOVE_TYPES,
            'moveReasons' => self::MOVE_REASONS,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_pantry_categories', 'name')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RestaurantPantryCategory::create([
            'name' => trim($validated['name']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('restaurant.pantry.index')->with('success', 'Categorie creee.');
    }

    public function updateCategory(Request $request, RestaurantPantryCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_pantry_categories', 'name')
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

        return redirect()->route('restaurant.pantry.index')->with('success', 'Categorie modifiee.');
    }

    public function destroyCategory(RestaurantPantryCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return redirect()->route('restaurant.pantry.index')->withErrors([
                'category' => 'Impossible de supprimer: cette categorie contient des articles.',
            ]);
        }

        $category->delete();
        return redirect()->route('restaurant.pantry.index')->with('success', 'Categorie supprimee.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_pantry_category_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_pantry_categories', 'id')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_pantry_items', 'name')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'unit' => ['required', Rule::in(self::UNITS)],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Saisi en FCFA -> stockage en centimes (optionnel)
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        RestaurantPantryItem::create([
            'restaurant_pantry_category_id' => $validated['restaurant_pantry_category_id'] ?? null,
            'name' => trim($validated['name']),
            'unit' => $validated['unit'],
            'min_stock' => (string) ($validated['min_stock'] ?? 0),
            'current_stock' => '0',
            'cost_price' => isset($validated['cost_price']) ? ((int) $validated['cost_price'] * 100) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('restaurant.pantry.index')->with('success', 'Article cree.');
    }

    public function updateItem(Request $request, RestaurantPantryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_pantry_category_id' => [
                'nullable',
                'integer',
                Rule::exists('restaurant_pantry_categories', 'id')->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_pantry_items', 'name')
                    ->ignore($item->id)
                    ->where(fn ($q) => $q->where('tenant_id', Auth::user()->tenant_id)),
            ],
            'unit' => ['required', Rule::in(self::UNITS)],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Saisi en FCFA -> stockage en centimes (optionnel)
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update([
            'restaurant_pantry_category_id' => $validated['restaurant_pantry_category_id'] ?? null,
            'name' => trim($validated['name']),
            'unit' => $validated['unit'],
            'min_stock' => (string) ($validated['min_stock'] ?? 0),
            'cost_price' => isset($validated['cost_price']) ? ((int) $validated['cost_price'] * 100) : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('restaurant.pantry.index')->with('success', 'Article modifie.');
    }

    public function destroyItem(RestaurantPantryItem $item): RedirectResponse
    {
        if ($item->movements()->exists()) {
            return redirect()->route('restaurant.pantry.index')->withErrors([
                'item' => 'Cet article a deja des mouvements. Desactive-le au lieu de le supprimer.',
            ]);
        }

        $item->delete();
        return redirect()->route('restaurant.pantry.index')->with('success', 'Article supprime.');
    }

    public function storeMovement(Request $request, RestaurantPantryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(self::MOVE_TYPES)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', Rule::in(self::MOVE_REASONS)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $occurredAt = $validated['occurred_at'] ? Carbon::parse($validated['occurred_at']) : now();
        $qty = (float) $validated['quantity'];

        DB::transaction(function () use ($item, $validated, $qty, $occurredAt) {
            $type = $validated['type'];

            $delta = match ($type) {
                'in' => $qty,
                'out' => -$qty,
                default => 0,
            };

            if ($type === 'adjust') {
                // Adjust = set absolute stock (quantity is the new stock value)
                $newStock = $qty;
                RestaurantPantryMovement::create([
                    'restaurant_pantry_item_id' => $item->id,
                    'type' => 'adjust',
                    'quantity' => $newStock,
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'recorded_by' => Auth::id(),
                    'occurred_at' => $occurredAt,
                ]);

                $item->update(['current_stock' => (string) $newStock]);
                return;
            }

            $current = (float) $item->current_stock;
            $next = $current + $delta;
            if ($next < 0) {
                $next = 0;
            }

            RestaurantPantryMovement::create([
                'restaurant_pantry_item_id' => $item->id,
                'type' => $type,
                'quantity' => $qty,
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => Auth::id(),
                'occurred_at' => $occurredAt,
            ]);

            $item->update(['current_stock' => (string) $next]);
        });

        return redirect()->route('restaurant.pantry.index')->with('success', 'Stock mis a jour.');
    }
}
