<?php

namespace App\Http\Controllers;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\User;
use App\Notifications\RestaurantOrderReady;
use App\Notifications\RestaurantOrderSentToKitchen;
use App\Services\RestaurantAssignmentService;
use App\Services\RestaurantStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

    public function __construct(
        private readonly RestaurantStockService $stock,
        private readonly RestaurantAssignmentService $assignment,
    ) {
    }

    public function index(Request $request): View
    {
        $query = RestaurantCustomerOrder::query()
            ->withCount('items')
            ->with('assignedServer:id,name')
            ->orderByDesc('id');

        // Un serveur peut se concentrer sur ses propres commandes.
        if ($request->boolean('mine')) {
            $query->assignedTo((int) Auth::id());
        }

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

        $user = Auth::user();
        $canManage = $user->hasAnyRole(['restaurant_chief', 'restaurant_staff']);

        return view('restaurant.orders.index', [
            'orders' => $orders,
            'statuses' => self::STATUSES,
            'statusLabels' => RestaurantCustomerOrder::STATUS_LABELS,
            'canManage' => $canManage,
            'isServer' => $user->hasAnyRole(['restaurant_staff', 'restaurant_chief']),
            'onDuty' => $user->isOnRestaurantDuty(),
            'onDutyServers' => $this->assignment->onDutyServers(),
            'categories' => $categories,
            'menuItems' => $menuItems,
        ]);
    }

    public function show(RestaurantCustomerOrder $order): View
    {
        $order->load('items', 'assignedServer:id,name');
        $user = Auth::user();

        return view('restaurant.orders.show', [
            'order' => $order,
            'statuses' => self::STATUSES,
            'statusLabels' => RestaurantCustomerOrder::STATUS_LABELS,
            'canManage' => $user->hasAnyRole(['restaurant_chief', 'restaurant_staff']),
            'isChief' => $user->hasRole('restaurant_chief'),
            'isServer' => $user->hasAnyRole(['restaurant_staff', 'restaurant_chief']),
            'isCook' => $user->hasAnyRole(['restaurant_cook', 'restaurant_chief']),
            'onDutyServers' => $this->assignment->onDutyServers(),
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

            // Le serveur saisit la commande à la table et la transmet à la cuisine
            // dans le même geste : elle est donc directement affectée à lui et déjà
            // « en cuisine ».
            $order = RestaurantCustomerOrder::create([
                'source' => 'staff',
                'created_by' => Auth::id(),
                'assigned_server_id' => Auth::id(),
                'table_number' => trim((string) $validated['table_number']),
                'customer_name' => $validated['customer_name'] ? trim((string) $validated['customer_name']) : null,
                'customer_phone' => $validated['customer_phone'] ? trim((string) $validated['customer_phone']) : null,
                'status' => 'confirmed',
                'total_amount' => $total,
                'notes' => $validated['notes'] ? trim((string) $validated['notes']) : null,
                'placed_at' => now(),
                'assigned_at' => now(),
                'sent_to_kitchen_at' => now(),
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

        // La commande part directement en cuisine : les ingrédients sortent du stock
        // et le bon de commande arrive chez les cuisiniers.
        $shortages = $this->stock->deductForOrder($order);
        $this->notifyKitchen($order);

        return redirect()
            ->route('restaurant.orders.show', $order)
            ->with('success', 'Commande créée et transmise en cuisine.')
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
     * Un serveur prend en charge une commande du portail restée sans preneur
     * (aucun serveur n'était en service à sa réception).
     */
    public function claim(RestaurantCustomerOrder $order): RedirectResponse
    {
        if ($order->assigned_server_id && $order->assigned_server_id !== Auth::id()) {
            return back()->withErrors(['order' => 'Cette commande est déjà prise par un autre serveur.']);
        }

        $order->update([
            'assigned_server_id' => Auth::id(),
            'assigned_at' => $order->assigned_at ?? now(),
        ]);

        return back()->with('success', 'Commande prise en charge. Transmettez-la en cuisine.');
    }

    /**
     * Le serveur réaffecte une commande à un autre serveur (chef uniquement, ou le
     * serveur lui-même qui passe la main).
     */
    public function reassign(Request $request, RestaurantCustomerOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_server_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ]);

        $order->update([
            'assigned_server_id' => (int) $validated['assigned_server_id'],
            'assigned_at' => now(),
        ]);

        $server = User::find($validated['assigned_server_id']);

        $this->safeNotify(fn () => $server?->notify(new \App\Notifications\RestaurantOrderAssigned($order->fresh())), $order);

        return back()->with('success', 'Commande réaffectée à ' . ($server?->name ?? 'un autre serveur') . '.');
    }

    /**
     * Le serveur transmet le bon de commande à la cuisine : le stock est engagé et
     * les cuisiniers sont prévenus. Idempotent (une commande déjà en cuisine n'est
     * pas re-transmise).
     */
    public function sendToKitchen(RestaurantCustomerOrder $order): RedirectResponse
    {
        if ($order->status === RestaurantCustomerOrder::STATUS_CANCELED) {
            return back()->withErrors(['order' => 'Cette commande est annulée.']);
        }

        if ($order->wasSentToKitchen()) {
            return back()->with('success', 'Commande déjà transmise en cuisine.');
        }

        // Un serveur qui transmet une commande encore libre se l'attribue au passage.
        $order->update([
            'status' => RestaurantCustomerOrder::STATUS_CONFIRMED,
            'assigned_server_id' => $order->assigned_server_id ?? Auth::id(),
            'assigned_at' => $order->assigned_at ?? now(),
            'sent_to_kitchen_at' => now(),
        ]);

        $shortages = $this->stock->deductForOrder($order->fresh('items'));
        $this->notifyKitchen($order);

        return back()
            ->with('success', 'Bon de commande transmis en cuisine.')
            ->with('stock_warning', $this->shortageMessage($shortages));
    }

    /**
     * La cuisine prend la commande en préparation.
     */
    public function markPreparing(RestaurantCustomerOrder $order): RedirectResponse
    {
        if (!$order->wasSentToKitchen()) {
            return back()->withErrors(['order' => 'Cette commande n\'a pas encore été transmise en cuisine.']);
        }

        $order->update(['status' => RestaurantCustomerOrder::STATUS_PREPARING]);

        return back()->with('success', 'Commande en préparation.');
    }

    /**
     * La cuisine signale le plat prêt : le serveur responsable est prévenu qu'il
     * peut venir le chercher.
     */
    public function markReady(RestaurantCustomerOrder $order): RedirectResponse
    {
        if (!$order->wasSentToKitchen()) {
            return back()->withErrors(['order' => 'Cette commande n\'a pas encore été transmise en cuisine.']);
        }

        $order->update([
            'status' => RestaurantCustomerOrder::STATUS_READY,
            'ready_at' => now(),
        ]);

        // Signale au serveur qui a la table qu'il peut apporter le plat.
        if ($order->assigned_server_id) {
            $server = User::find($order->assigned_server_id);
            $this->safeNotify(fn () => $server?->notify(new RestaurantOrderReady($order->fresh())), $order);
        }

        return back()->with('success', 'Plat signalé prêt. Le serveur est prévenu.');
    }

    /**
     * Le serveur a apporté le plat à la table.
     */
    public function markServed(RestaurantCustomerOrder $order): RedirectResponse
    {
        if ($order->status === RestaurantCustomerOrder::STATUS_CANCELED) {
            return back()->withErrors(['order' => 'Cette commande est annulée.']);
        }

        $order->update([
            'status' => RestaurantCustomerOrder::STATUS_SERVED,
            'served_at' => now(),
        ]);

        return back()->with('success', 'Commande servie.');
    }

    /**
     * Prévient les cuisiniers (et le chef) qu'un bon de commande les attend.
     */
    private function notifyKitchen(RestaurantCustomerOrder $order): void
    {
        $cooks = User::query()
            ->havingRole(['restaurant_cook', 'restaurant_chief'])
            ->active()
            ->get();

        if ($cooks->isEmpty()) {
            return;
        }

        $this->safeNotify(fn () => Notification::send($cooks, new RestaurantOrderSentToKitchen($order->fresh('items'))), $order);
    }

    private function safeNotify(callable $callback, RestaurantCustomerOrder $order): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::error("Notification commande restaurant #{$order->id} : " . $e->getMessage());
        }
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
