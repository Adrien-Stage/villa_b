<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RestaurantOrderController extends Controller
{
    private const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'served', 'canceled'];

    public function index(Request $request): View
    {
        $query = RestaurantCustomerOrder::query()
            ->withCount('items')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('table')) {
            $table = trim((string) $request->input('table'));
            $query->where('table_number', 'ilike', "%{$table}%");
        }

        $orders = $query->paginate(20)->withQueryString();

        $categories = RestaurantMenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $menuItems = RestaurantMenuItem::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $canManage = Auth::user()->hasAnyRole(['restaurant_chief', 'restaurant_staff']);

        return view('restaurant.orders.index', [
            'orders' => $orders,
            'statuses' => self::STATUSES,
            'canManage' => $canManage,
            'categories' => $categories,
            'menuItems' => $menuItems,
        ]);
    }

    public function show(RestaurantCustomerOrder $order): View
    {
        $order->load('items');

        return view('restaurant.orders.show', [
            'order' => $order,
            'statuses' => self::STATUSES,
            'canManage' => Auth::user()->hasAnyRole(['restaurant_chief', 'restaurant_staff']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'string', 'max:10'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items_json' => ['required', 'string', 'max:20000'],
        ]);

        $raw = json_decode($validated['items_json'], true);
        if (!is_array($raw)) {
            return back()->withErrors(['items' => 'Panier invalide.'])->withInput();
        }

        $lines = [];
        foreach ($raw as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
            $qty = is_array($row) ? (int) ($row['qty'] ?? 0) : 0;
            if ($id <= 0 || $qty <= 0) continue;
            if ($qty > 99) $qty = 99;
            $lines[$id] = ($lines[$id] ?? 0) + $qty;
        }

        if (empty($lines)) {
            return back()->withErrors(['items' => 'Ajoute au moins un article.'])->withInput();
        }

        $menuItems = RestaurantMenuItem::query()
            ->whereIn('id', array_keys($lines))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count($lines)) {
            return back()->withErrors(['items' => 'Certains articles ne sont plus disponibles.'])->withInput();
        }

        $order = DB::transaction(function () use ($validated, $lines, $menuItems) {
            $total = 0;
            foreach ($lines as $menuItemId => $qty) {
                $total += (int) $menuItems->get($menuItemId)->price * (int) $qty;
            }

            $order = RestaurantCustomerOrder::create([
                'source' => 'staff',
                'created_by' => Auth::id(),
                'table_number' => trim((string) $validated['table_number']),
                'customer_name' => $validated['customer_name'] ? trim((string) $validated['customer_name']) : null,
                'customer_phone' => $validated['customer_phone'] ? trim((string) $validated['customer_phone']) : null,
                'status' => 'confirmed',
                'total_amount' => $total,
                'notes' => $validated['notes'] ? trim((string) $validated['notes']) : null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $menuItemId => $qty) {
                $item = $menuItems->get($menuItemId);
                $lineTotal = (int) $item->price * (int) $qty;

                RestaurantCustomerOrderItem::create([
                    'restaurant_customer_order_id' => $order->id,
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $qty,
                    'unit_price' => (int) $item->price,
                    'total_price' => $lineTotal,
                    'special_requests' => null,
                ]);
            }

            return $order;
        });

        return redirect()
            ->route('restaurant.orders.show', $order)
            ->with('success', 'Commande creee.');
    }

    public function updateStatus(Request $request, RestaurantCustomerOrder $order): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $order->update(['status' => $validated['status']]);

        if ($request->expectsJson() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['ok' => true, 'status' => $order->status, 'order_id' => $order->id]);
        }

        return back()->with('success', 'Statut mis a jour.');
    }
}
