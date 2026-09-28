<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class PurchaseRequestController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function __construct(private PurchaseRequestService $requestService)
    {
    }

    public function index(Request $request): View
    {
        $query = PurchaseRequest::with(['requestedBy', 'reviewedBy'])->withCount('lines');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $requests = $query->orderByDesc('id')->paginate(self::PAR_PAGE)->withQueryString();

        $stats = [
            'total'     => PurchaseRequest::count(),
            'pending'   => PurchaseRequest::where('status', PurchaseRequest::STATUS_PENDING)->count(),
            'approved'  => PurchaseRequest::where('status', PurchaseRequest::STATUS_APPROVED)->count(),
            'converted' => PurchaseRequest::where('status', PurchaseRequest::STATUS_CONVERTED)->count(),
        ];

        return view('economat.purchase_requests.index', [
            'requests'    => $requests,
            'stats'       => $stats,
            'departments' => PurchaseRequest::DEPARTMENTS,
            'statuses'    => PurchaseRequest::STATUSES,
            'canManage'   => Auth::user()?->hasAnyRole(['econome', 'manager', 'admin', 'controller']) ?? false,
        ]);
    }

    public function create(): View
    {
        $items = StockItem::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'current_stock', 'average_cost', 'last_purchase_price']);

        return view('economat.purchase_requests.create', [
            'items'       => $items,
            'departments' => PurchaseRequest::DEPARTMENTS,
            'priorities'  => PurchaseRequest::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department'                    => ['required', 'string', 'max:50'],
            'priority'                      => ['required', 'in:low,normal,urgent'],
            'purpose'                       => ['nullable', 'string', 'max:1000'],
            'lines'                         => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id'         => ['required', 'exists:stock_items,id'],
            'lines.*.quantity_requested'    => ['required', 'numeric', 'min:0.001'],
            'lines.*.estimated_unit_price'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'                 => ['nullable', 'string', 'max:255'],
        ], [
            'lines.required' => 'Veuillez ajouter au moins un article à la demande d\'achat.',
        ]);

        try {
            $purchaseRequest = $this->requestService->create($validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.purchase_requests.show', $purchaseRequest)
            ->with('success', "Demande d'achat {$purchaseRequest->number} créée avec succès. Elle est en attente d'approbation.");
    }

    public function show(PurchaseRequest $purchaseRequest): View
    {
        $purchaseRequest->load(['requestedBy', 'reviewedBy', 'lines.item.supplier', 'purchaseOrders.supplier']);

        $suppliers = Supplier::query()->active()->orderBy('name')->get();

        return view('economat.purchase_requests.show', [
            'request'   => $purchaseRequest,
            'suppliers' => $suppliers,
            'canReview' => Auth::user()?->hasAnyRole(['manager', 'admin', 'controller']) ?? false,
            'canManage' => Auth::user()?->hasAnyRole(['econome', 'manager', 'admin']) ?? false,
        ]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->requestService->approve($purchaseRequest, Auth::user(), $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Demande d'achat {$purchaseRequest->number} approuvée. Vous pouvez maintenant la convertir en bon de commande.");
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ], [
            'rejection_reason.required' => 'Le motif de rejet est obligatoire.',
        ]);

        try {
            $this->requestService->reject($purchaseRequest, Auth::user(), $validated['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Demande d'achat {$purchaseRequest->number} refusée.");
    }

    public function convert(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        try {
            $orders = $this->requestService->convertToOrders(
                $purchaseRequest,
                Auth::user(),
                !empty($validated['supplier_id']) ? (int) $validated['supplier_id'] : null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($orders->count() === 1) {
            $firstOrder = $orders->first();
            return redirect()
                ->route('economat.orders.show', $firstOrder)
                ->with('success', "Bon de commande {$firstOrder->number} généré à partir de la demande {$purchaseRequest->number}.");
        }

        return redirect()
            ->route('economat.orders.index')
            ->with('success', "{$orders->count()} bons de commande ont été générés à partir de la demande {$purchaseRequest->number}.");
    }

    public function cancel(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        try {
            $this->requestService->cancel($purchaseRequest, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.purchase_requests.index')
            ->with('success', "Demande d'achat {$purchaseRequest->number} annulée.");
    }
}
