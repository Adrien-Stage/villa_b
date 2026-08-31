<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Notifications\PurchaseOrderUpdated;
use App\Services\Notifier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    /** Direction et comptabilité suivent l'engagement puis la dette fournisseur. */
    private const WATCHERS = ['manager', 'admin', 'accountant'];

    public function __construct(private Notifier $notifier)
    {
    }

    public function index(): View
    {
        $orders = PurchaseOrder::with('supplier')
            ->withCount('lines')
            ->latest()
            ->get();

        return view('economat.orders.index', compact('orders'));
    }

    public function create(): View
    {
        $suppliers = Supplier::active()->orderBy('name')->get();
        $items     = StockItem::active()->orderBy('name')->get();

        return view('economat.orders.create', compact('suppliers', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'expected_at'        => ['nullable', 'date'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'lines'              => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'exists:stock_items,id'],
            'lines.*.quantity'   => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],   // FCFA
        ], [
            'lines.required' => 'Ajoutez au moins un article au bon de commande.',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $order = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'expected_at' => $validated['expected_at'] ?? null,
                'notes'       => $validated['notes'] ?? null,
                'created_by'  => auth()->id(),
                'tenant_id'   => auth()->user()->tenant_id
                    ?? \App\Models\Tenant::current()?->id,
            ]);

            foreach ($validated['lines'] as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $order->id,
                    'stock_item_id'     => $line['stock_item_id'],
                    'quantity_ordered'  => $line['quantity'],
                    'unit_price'        => (int) $line['unit_price'] * 100,
                ]);
            }

            $order->recalculateTotal();

            return $order;
        });

        return redirect()
            ->route('economat.orders.show', $order)
            ->with('success', "Bon {$order->number} créé. Vous pouvez maintenant l'envoyer au fournisseur.");
    }

    public function show(PurchaseOrder $order): View
    {
        $order->load('supplier', 'lines.item', 'createdBy', 'receivedBy');

        return view('economat.orders.show', compact('order'));
    }

    /** Envoi du bon par email au fournisseur. */
    public function send(PurchaseOrder $order, PurchaseOrderService $service): RedirectResponse
    {
        try {
            $sent = $service->send($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Le bon est marqué envoyé même si l'email échoue : l'engagement de
        // dépense existe, la direction doit le savoir dans les deux cas.
        $this->notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), auth()->id());

        return $sent
            ? back()->with('success', "Bon {$order->number} envoyé à {$order->supplier->email}.")
            : back()->with('error', "Le bon est marqué comme envoyé, mais l'email n'a pas pu partir. Vérifiez l'adresse et réessayez.");
    }

    /** Réception (totale ou partielle) : entrée en stock des quantités livrées. */
    public function receive(Request $request, PurchaseOrder $order, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'received'   => ['required', 'array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->receive($order, array_map('floatval', $validated['received']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // La marchandise est entrée en stock : la facture fournisseur suit.
        $this->notifier->toRoles(self::WATCHERS, new PurchaseOrderUpdated($order->fresh('supplier')), auth()->id());

        return back()->with('success', "Réception enregistrée pour le bon {$order->number}.");
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        if (!$order->canBeCancelled()) {
            return back()->with('error', 'Ce bon ne peut plus être annulé.');
        }

        $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        return back()->with('success', "Bon {$order->number} annulé.");
    }
}
