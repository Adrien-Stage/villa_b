<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RestaurantPortalController extends Controller
{
    public function menu(Request $request, Tenant $tenant): View
    {
        abort_unless($tenant->is_active, 404);

        $tableNumber = $request->query('table');
        $tableNumber = is_string($tableNumber) ? trim($tableNumber) : null;
        if ($tableNumber !== null && $tableNumber !== '' && Str::length($tableNumber) > 10) {
            $tableNumber = Str::substr($tableNumber, 0, 10);
        }

        $categories = RestaurantMenuCategory::query()
            ->withoutGlobalScopes()
            
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $items = RestaurantMenuItem::query()
            ->withoutGlobalScopes()
            
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('portal.restaurant.menu', [
            'tenant' => $tenant,
            'tableNumber' => $tableNumber,
            'categories' => $categories,
            'items' => $items,
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($tenant->is_active, 404);

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

            if ($id <= 0 || $qty <= 0) {
                continue;
            }
            if ($qty > 99) {
                $qty = 99;
            }
            $lines[$id] = ($lines[$id] ?? 0) + $qty;
        }

        if (empty($lines)) {
            return back()->withErrors(['items' => 'Ajoute au moins un article avant de valider.'])->withInput();
        }

        $itemIds = array_keys($lines);
        $menuItems = RestaurantMenuItem::query()
            ->withoutGlobalScopes()
            
            ->where('is_active', true)
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count($itemIds)) {
            return back()->withErrors(['items' => 'Certains articles ne sont plus disponibles. Recharge la page.'])->withInput();
        }

        $tableNumber = trim((string) $validated['table_number']);
        if ($tableNumber === '') {
            return back()->withErrors(['table_number' => 'Le numero de table est obligatoire.'])->withInput();
        }

        $customerName = $validated['customer_name'] !== null ? trim((string) $validated['customer_name']) : null;
        if ($customerName === '') {
            $customerName = null;
        }

        $customerPhone = $validated['customer_phone'] !== null ? trim((string) $validated['customer_phone']) : null;
        if ($customerPhone === '') {
            $customerPhone = null;
        }

        $notes = $validated['notes'] !== null ? trim((string) $validated['notes']) : null;
        if ($notes === '') {
            $notes = null;
        }

        $order = DB::transaction(function () use ($tenant, $tableNumber, $customerName, $customerPhone, $notes, $lines, $menuItems) {
            $total = 0;

            foreach ($lines as $menuItemId => $qty) {
                $item = $menuItems->get($menuItemId);
                $total += (int) $item->price * (int) $qty;
            }

            $order = RestaurantCustomerOrder::query()
                ->withoutGlobalScopes()
                ->create([
                    'source' => 'portal',
                    'created_by' => null,
                    'table_number' => $tableNumber,
                    'customer_name' => $customerName,
                    'customer_phone' => $customerPhone,
                    'status' => 'pending',
                    'total_amount' => $total,
                    'notes' => $notes,
                    'placed_at' => now(),
                ]);

            foreach ($lines as $menuItemId => $qty) {
                $item = $menuItems->get($menuItemId);
                $lineTotal = (int) $item->price * (int) $qty;

                RestaurantCustomerOrderItem::query()
                    ->withoutGlobalScopes()
                    ->create([
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

        return redirect()->route('portal.restaurant.order', ['tenant' => $tenant->slug, 'order' => $order->id]);
    }

    public function order(Request $request, Tenant $tenant, int $order): View
    {
        abort_unless($tenant->is_active, 404);

        $orderModel = RestaurantCustomerOrder::query()
            ->withoutGlobalScopes()
            
            ->with('items')
            ->findOrFail($order);

        return view('portal.restaurant.order', [
            'tenant' => $tenant,
            'order' => $orderModel,
        ]);
    }
}
