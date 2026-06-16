<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use App\Enums\BookingStatus;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopOrderController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = auth()->user()->tenant;
        $query = ShopOrder::where('tenant_id', $tenant->id)
            ->with(['items', 'customer', 'booking', 'createdBy']);

        // Filtres
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'ilike', "%{$search}%")
                    ->orWhere('customer_name', 'ilike', "%{$search}%")
                    ->orWhere('customer_phone', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('shop.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function create(): View|\Illuminate\Http\RedirectResponse
    {
        $tenant = auth()->user()->tenant;
        
        // Verifier s'il y a une caisse ouverte !
        $activeSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->whereNull('closed_at')
            ->first();
            
        if (!$activeSession) {
            return redirect()->route('shop.cash_register.open')->with('warning', 'Vous devez ouvrir votre caisse avant de pouvoir enregistrer une commande.');
        }

        $products = ShopProduct::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('category')
            ->orderBy('name')
            ->get();

        $customers = Customer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $bookings = Booking::query()
            ->where('status', BookingStatus::CHECKED_IN)
            ->with(['customer', 'room'])
            ->orderByDesc('id')
            ->get();

        return view('shop.orders.create', [
            'products' => $products,
            'customers' => $customers,
            'bookings' => $bookings,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_first_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_id' => 'nullable|exists:customers,id',
            'create_customer' => 'nullable|boolean',
            'booking_id' => 'nullable|exists:bookings,id',
            'payment_method' => 'required|in:cash,mobile_money,card,room_charge,other',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:shop_products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        // Si room_charge, le booking_id est obligatoire
        if ($validated['payment_method'] === 'room_charge' && empty($validated['booking_id'])) {
            return back()->withInput()->withErrors(['booking_id' => 'Vous devez sélectionner une chambre pour débiter sur le séjour.']);
        }

        // Si on associe à un booking, récupérer le client du booking
        $booking = null;
        if (!empty($validated['booking_id'])) {
            $booking = Booking::where('id', $validated['booking_id'])
                ->where('status', BookingStatus::CHECKED_IN)
                ->with('customer')
                ->first();

            if (!$booking) {
                return back()->withInput()->withErrors(['booking_id' => 'Le séjour sélectionné n\'est pas en cours.']);
            }

            // Auto-remplir les infos client depuis le booking
            $validated['customer_id'] = $booking->customer_id;
            $validated['customer_name'] = $booking->customer->last_name ?? 'Client';
        }

        // Créer un nouveau client si demandé
        if (!empty($validated['create_customer']) && empty($validated['customer_id'])) {
            $customer = Customer::create([
                'tenant_id' => $tenant->id,
                'first_name' => $validated['customer_first_name'] ?? 'Inconnu',
                'last_name' => $validated['customer_name'] ?? 'Inconnu',
                'phone' => $validated['customer_phone'] ?? null,
            ]);
            $validated['customer_id'] = $customer->id;
            $validated['customer_name'] = $customer->full_name ?? ($customer->first_name . ' ' . $customer->last_name);
        }

        // customer_name requis : fallback si vide
        if (empty($validated['customer_name'])) {
            $validated['customer_name'] = 'Client de passage';
        }

        // Vérifier que tous les produits appartiennent au tenant
        $productIds = collect($validated['items'])->pluck('product_id')->toArray();
        $products = ShopProduct::where('tenant_id', $tenant->id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        if ($products->count() !== count(array_unique($productIds))) {
            return back()->withErrors('Un ou plusieurs produits sont invalides');
        }

        // Vérifier les quantités
        foreach ($validated['items'] as $item) {
            $product = $products->get($item['product_id']);
            if ($product->stock_quantity < $item['quantity']) {
                return back()->withErrors("Stock insuffisant pour {$product->name}");
            }
        }

        $activeSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
            ->where('tenant_id', $tenant->id)
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return redirect()->route('shop.cash_register.open')->with('warning', 'Vous devez ouvrir votre caisse.');
        }

        // Créer la commande dans une transaction
        $order = DB::transaction(function () use ($validated, $tenant, $products, $activeSession, $booking) {
            $orderNumber = 'SHOP-' . date('YmdHis') . '-' . Str::random(4);
            $totalItems = 0;
            $subtotal = 0;

            $order = ShopOrder::create([
                'tenant_id' => $tenant->id,
                'order_number' => $orderNumber,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'booking_id' => $validated['booking_id'] ?? null,
                'payment_method' => $validated['payment_method'],
                'created_by' => auth()->id(),
                'cash_register_session_id' => $activeSession->id,
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'unpaid',
            ]);

            // Créer les items et mettre à jour les stocks
            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);
                $itemTotal = $product->price * $item['quantity'];
                $totalItems += $item['quantity'];
                $subtotal += $itemTotal;

                ShopOrderItem::create([
                    'shop_order_id' => $order->id,
                    'shop_product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'item_total' => $itemTotal,
                ]);

                // Décrémenter le stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Pas de taxe (TVA à 0)
            $taxAmount = 0;
            $totalAmount = $subtotal;

            // Mettre à jour la commande
            $order->update([
                'total_items' => $totalItems,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            // Si room_charge → créer la ligne folio et marquer payé immédiatement
            if ($validated['payment_method'] === 'room_charge' && $booking) {
                $folio = FolioItem::create([
                    'tenant_id' => $booking->tenant_id,
                    'booking_id' => $booking->id,
                    'customer_id' => $booking->customer_id,
                    'type' => FolioItem::TYPE_SHOP,
                    'description' => "Boutique — Commande #{$order->order_number}",
                    'quantity' => 1,
                    'unit_price' => (int) $totalAmount,
                    'total_price' => (int) $totalAmount,
                    'is_complimentary' => false,
                    'earns_points' => true,
                    'recorded_by' => Auth::id(),
                    'occurred_at' => now(),
                    'notes' => $order->notes,
                ]);

                $order->update([
                    'folio_item_id' => $folio->id,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

                // Recalculer les totaux du booking
                app(CheckOutService::class)->recalculateTotals($booking->fresh());
            }

            return $order;
        });

        return redirect()->route('shop.orders.show', $order)
            ->with('success', 'Commande créée avec succès');
    }

    public function show(ShopOrder $order): View
    {
        $order->load(['items.product', 'customer', 'booking.customer', 'booking.room', 'createdBy']);

        return view('shop.orders.show', [
            'order' => $order,
        ]);
    }

    public function markAsPaid(ShopOrder $order, Request $request)
    {
        if ($order->payment_status === 'paid') {
            return back()->with('info', 'Commande déjà payée');
        }

        $activeSession = \App\Models\CashRegisterSession::where('user_id', auth()->id())
            ->where('tenant_id', auth()->user()->tenant->id)
            ->whereNull('closed_at')
            ->first();

        if (!$activeSession) {
            return back()->with('warning', 'Veuillez ouvrir votre caisse pour encaisser.');
        }

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'cash_register_session_id' => $activeSession->id,
        ]);

        return back()->with('success', 'Paiement enregistré');
    }

    public function refund(ShopOrder $order)
    {

        if ($order->payment_status !== 'paid') {
            return back()->withErrors('Seules les commandes payées peuvent être remboursées');
        }

        // Remboursement et restauration du stock
        foreach ($order->items as $item) {
            $item->product->increment('stock_quantity', $item->quantity);
        }

        $order->update([
            'payment_status' => 'refunded',
        ]);

        return back()->with('success', 'Remboursement effectué et stock restauré');
    }

    public function receipt(ShopOrder $order): View
    {
        $order->load(['items.product', 'customer', 'booking.room', 'createdBy']);

        return view('shop.orders.receipt', [
            'order' => $order,
        ]);
    }
}
