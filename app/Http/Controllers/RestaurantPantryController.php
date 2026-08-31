<?php

namespace App\Http\Controllers;

use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Services\RestaurantStockService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class RestaurantPantryController extends Controller
{
    private const UNITS = ['pcs', 'kg', 'g', 'l', 'ml'];
    private const MOVE_TYPES = ['in', 'out', 'adjust'];

    // Motifs saisissables à la main. Les motifs système (vente, production,
    // inventaire) sont générés par RestaurantStockService et n'ont rien à faire ici.
    private const MOVE_REASONS = ['purchase', 'kitchen', 'waste', 'correction', 'other'];

    public function __construct(private readonly RestaurantStockService $stock)
    {
    }

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

        $items = $itemsQuery->paginate(15)->withQueryString();

        $recentMovements = RestaurantPantryMovement::query()
            ->with(['item', 'recordedBy'])
            ->latest('occurred_at')
            ->take(20)
            ->get();

        $canManage = Auth::user()->hasAnyRole(['restaurant_chief']);

        // Valeur du stock : ce que le garde-manger immobilise réellement en argent.
        $stockValue = RestaurantPantryItem::query()
            ->active()
            ->get()
            ->sum(fn (RestaurantPantryItem $item) => $item->stockValue());

        $stats = [
            'total_items' => RestaurantPantryItem::query()->count(),
            'low_stock' => RestaurantPantryItem::query()->lowStock()->count(),
            'negative_stock' => RestaurantPantryItem::query()->where('current_stock', '<', 0)->count(),
            'stock_value' => $stockValue,
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
            'reasonLabels' => RestaurantPantryMovement::REASON_LABELS,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('restaurant_pantry_categories', 'name')->where(fn ($q) => $q),
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
                Rule::exists('restaurant_pantry_categories', 'id')->where(fn ($q) => $q),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_pantry_items', 'name')->where(fn ($q) => $q),
            ],
            'unit' => ['required', Rule::in(self::UNITS)],
            'is_prepared' => ['nullable', 'boolean'],
            // On achète en sacs de 50 kg mais on cuisine en grammes : l'unité d'achat
            // et son facteur de conversion évitent l'erreur de saisie la plus courante.
            'purchase_unit' => ['nullable', 'string', 'max:40'],
            'purchase_conversion' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Saisi en FCFA -> stockage en centimes (optionnel)
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $costPrice = isset($validated['cost_price']) ? ((int) $validated['cost_price'] * 100) : null;

        RestaurantPantryItem::create([
            'restaurant_pantry_category_id' => $validated['restaurant_pantry_category_id'] ?? null,
            'name' => trim($validated['name']),
            'unit' => $validated['unit'],
            'is_prepared' => $request->boolean('is_prepared'),
            'purchase_unit' => $validated['purchase_unit'] ?? null,
            'purchase_conversion' => $validated['purchase_conversion'] ?? 1,
            'min_stock' => (string) ($validated['min_stock'] ?? 0),
            'current_stock' => '0',
            'cost_price' => $costPrice,
            // Amorce le coût moyen : il sera affiné à la première réception valorisée.
            'average_cost' => $costPrice ?? 0,
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
                Rule::exists('restaurant_pantry_categories', 'id')->where(fn ($q) => $q),
            ],
            'name' => [
                'required',
                'string',
                'max:140',
                Rule::unique('restaurant_pantry_items', 'name')
                    ->ignore($item->id)
                    ->where(fn ($q) => $q),
            ],
            'unit' => ['required', Rule::in(self::UNITS)],
            'is_prepared' => ['nullable', 'boolean'],
            // On achète en sacs de 50 kg mais on cuisine en grammes : l'unité d'achat
            // et son facteur de conversion évitent l'erreur de saisie la plus courante.
            'purchase_unit' => ['nullable', 'string', 'max:40'],
            'purchase_conversion' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Saisi en FCFA -> stockage en centimes (optionnel)
            'cost_price' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update([
            'restaurant_pantry_category_id' => $validated['restaurant_pantry_category_id'] ?? null,
            'name' => trim($validated['name']),
            'unit' => $validated['unit'],
            'is_prepared' => $request->boolean('is_prepared'),
            'purchase_unit' => $validated['purchase_unit'] ?? null,
            'purchase_conversion' => $validated['purchase_conversion'] ?? 1,
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

        // Tout passe par le moteur : c'est lui qui tient le coût moyen pondéré et la
        // piste d'audit des mouvements. Pour un ajustement, la quantité saisie est le
        // stock absolu constaté, pas un delta.
        $this->stock->recordMovement(
            item: $item,
            type: $validated['type'],
            quantity: (float) $validated['quantity'],
            reason: $validated['reason'],
            notes: $validated['notes'] ?? null,
            occurredAt: $validated['occurred_at'] ? Carbon::parse($validated['occurred_at']) : null,
        );

        return redirect()->route('restaurant.pantry.index')->with('success', 'Stock mis a jour.');
    }

    /**
     * Réception de marchandise : on saisit ce qu'on a reçu en unités d'achat
     * (3 sacs de 50 kg) et ce qu'on a payé. Le moteur convertit en unités de stock
     * et recalcule le coût moyen pondéré — donc le coût matière de tous les plats
     * qui utilisent cet ingrédient.
     */
    public function receive(Request $request, RestaurantPantryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'purchase_quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            // Prix total payé, saisi en FCFA -> stocké en centimes
            'total_price' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        try {
            $this->stock->receive(
                item: $item,
                purchaseQuantity: (float) $validated['purchase_quantity'],
                totalPrice: isset($validated['total_price']) ? ((int) $validated['total_price'] * 100) : null,
                notes: $validated['notes'] ?? null,
                occurredAt: $validated['occurred_at'] ? Carbon::parse($validated['occurred_at']) : null,
            );
        } catch (RuntimeException $e) {
            return redirect()->route('restaurant.pantry.index')->withErrors(['movement' => $e->getMessage()]);
        }

        $item->refresh();

        return redirect()->route('restaurant.pantry.index')->with('success', sprintf(
            'Réception enregistrée. %s : stock %s %s, coût moyen %s FCFA / %s.',
            $item->name,
            rtrim(rtrim(number_format((float) $item->current_stock, 3, ',', ' '), '0'), ','),
            $item->unit,
            number_format((float) $item->average_cost / 100, 2, ',', ' '),
            $item->unit,
        ));
    }
}
