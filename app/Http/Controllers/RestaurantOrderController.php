<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Services\RestaurantStockService;
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

    /**
     * Statuts à partir desquels la cuisine a engagé les ingrédients : le stock
     * est sorti du garde-manger dès l'envoi en cuisine, pas au paiement.
     */
    private const KITCHEN_STATUSES = ['confirmed', 'preparing', 'ready', 'served'];

    public function __construct(private readonly RestaurantStockService $stock)
    {
    }

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

        // La commande part directement en cuisine : les ingrédients sortent du stock.
        $shortages = $this->stock->deductForOrder($order);

        return redirect()
            ->route('restaurant.orders.show', $order)
            ->with('success', 'Commande creee.')
            ->with('stock_warning', $this->shortageMessage($shortages));
    }

    public function updateStatus(Request $request, RestaurantCustomerOrder $order): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $status = $validated['status'];
        $order->update(['status' => $status]);

        // Le stock suit le parcours de la commande : il sort à l'envoi en cuisine,
        // et revient si la commande est annulée après coup.
        $shortages = [];

        if (in_array($status, self::KITCHEN_STATUSES, true)) {
            $shortages = $this->stock->deductForOrder($order);
        } elseif ($status === 'canceled') {
            $this->stock->restoreForOrder($order);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'ok' => true,
                'status' => $order->status,
                'order_id' => $order->id,
                'stock_warning' => $this->shortageMessage($shortages),
            ]);
        }

        return back()
            ->with('success', 'Statut mis a jour.')
            ->with('stock_warning', $this->shortageMessage($shortages));
    }

    /**
     * Les ingrédients passés en négatif ne bloquent pas la vente, mais le chef doit
     * le savoir immédiatement : soit le garde-manger est mal tenu, soit il faut
     * réapprovisionner.
     *
     * @param  array<int, array{item: string, needed: float, available: float, unit: string}>  $shortages
     */
    private function shortageMessage(array $shortages): ?string
    {
        if ($shortages === []) {
            return null;
        }

        $details = collect($shortages)
            ->map(fn (array $shortage) => sprintf(
                '%s (besoin %s %s, stock %s %s)',
                $shortage['item'],
                rtrim(rtrim(number_format($shortage['needed'], 3, ',', ' '), '0'), ','),
                $shortage['unit'],
                rtrim(rtrim(number_format($shortage['available'], 3, ',', ' '), '0'), ','),
                $shortage['unit'],
            ))
            ->implode(' · ');

        return "Stock insuffisant : {$details}. La commande est passée, mais le garde-manger est en négatif.";
    }
}
