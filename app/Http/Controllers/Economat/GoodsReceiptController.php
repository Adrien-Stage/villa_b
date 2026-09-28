<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function __construct(private GoodsReceiptService $receiptService)
    {
    }

    public function index(Request $request): View
    {
        $query = GoodsReceipt::with(['purchaseOrder', 'supplier', 'receivedBy'])->withCount('lines');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('received_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('received_at', '<=', $request->input('date_to'));
        }

        $receipts = $query->orderByDesc('received_at')->orderByDesc('id')->paginate(self::PAR_PAGE)->withQueryString();

        $suppliers = Supplier::active()->orderBy('name')->get();

        $stats = [
            'total_receipts' => GoodsReceipt::count(),
            'total_amount'   => (int) GoodsReceipt::where('status', GoodsReceipt::STATUS_RECEIVED)->sum('total_amount'),
        ];

        return view('economat.receipts.index', [
            'receipts'  => $receipts,
            'suppliers' => $suppliers,
            'stats'     => $stats,
        ]);
    }

    public function create(PurchaseOrder $order): View|RedirectResponse
    {
        if (!$order->canBeReceived()) {
            return redirect()
                ->route('economat.orders.show', $order)
                ->with('error', "Ce bon de commande ne peut pas être réceptionné dans son état actuel.");
        }

        $order->load(['supplier', 'lines.item']);

        return view('economat.receipts.create', [
            'order'   => $order,
            'reasons' => GoodsReceiptLine::REASONS,
        ]);
    }

    public function store(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_note_number'       => ['nullable', 'string', 'max:80'],
            'received_at'                => ['nullable', 'date'],
            'notes'                      => ['nullable', 'string', 'max:1000'],
            'lines'                      => ['required', 'array'],
            'lines.*.quantity_delivered' => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_accepted'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity_rejected'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.rejection_reason'   => ['nullable', 'string', 'max:100'],
            'lines.*.notes'              => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $receipt = $this->receiptService->receive($order, $validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.receipts.show', $receipt)
            ->with('success', "Bon de réception {$receipt->number} validé. Les marchandises acceptées sont entrées en stock.");
    }

    public function show(GoodsReceipt $receipt): View
    {
        $receipt->load(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item']);

        return view('economat.receipts.show', [
            'receipt' => $receipt,
        ]);
    }

    /**
     * Bordereau officiel de réception contradictoire imprimable (BR).
     * Conforme aux normes d'audit et sans éléments d'interface web polluants.
     */
    public function print(GoodsReceipt $receipt): View
    {
        $receipt->load(['purchaseOrder', 'supplier', 'receivedBy', 'lines.item']);

        return view('economat.receipts.print', [
            'receipt' => $receipt,
        ]);
    }

    public function cancel(GoodsReceipt $receipt): RedirectResponse
    {
        try {
            $this->receiptService->cancel($receipt, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.receipts.index')
            ->with('success', "Bon de réception {$receipt->number} annulé et sorties de stock enregistrées.");
    }
}
