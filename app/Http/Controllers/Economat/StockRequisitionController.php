<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Services\StockRequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Demandes des départements à l'économat.
 *
 * L'économe (et le manager) voient toutes les demandes et les traitent ; un
 * responsable de département ne voit et ne crée que les siennes.
 */
class StockRequisitionController extends Controller
{
    public function index(): View
    {
        $isKeeper = $this->isStoreKeeper();

        $query = StockRequisition::with('requestedBy', 'lines')->latest();

        // Un département ne voit que ses propres demandes.
        if (!$isKeeper) {
            $query->where('requested_by', Auth::id());
        }

        $requisitions = $query->get();

        return view('economat.requisitions.index', [
            'requisitions' => $requisitions,
            'isKeeper'     => $isKeeper,
        ]);
    }

    public function create(): View
    {
        $items = StockItem::active()->orderBy('name')->get();

        // Départements que l'utilisateur est habilité à représenter.
        $departments = collect(StockRequisition::DEPARTMENT_ROLES)
            ->filter(fn ($roles) => Auth::user()->hasAnyRole($roles))
            ->keys()
            ->mapWithKeys(fn ($key) => [$key => StockRequisition::DEPARTMENTS[$key]])
            ->all();

        // Un économe/manager sans département précis peut demander pour « autre ».
        if (empty($departments)) {
            $departments = ['autre' => StockRequisition::DEPARTMENTS['autre']];
        }

        return view('economat.requisitions.create', compact('items', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department'  => ['required', 'in:' . implode(',', array_keys(StockRequisition::DEPARTMENTS))],
            'purpose'     => ['nullable', 'string', 'max:500'],
            'lines'       => ['required', 'array', 'min:1'],
            'lines.*.stock_item_id' => ['required', 'exists:stock_items,id'],
            'lines.*.quantity'      => ['required', 'numeric', 'min:0.001'],
        ], [
            'lines.required' => 'Ajoutez au moins un article à votre demande.',
        ]);

        $requisition = DB::transaction(function () use ($validated) {
            $requisition = StockRequisition::create([
                'department'   => $validated['department'],
                'purpose'      => $validated['purpose'] ?? null,
                'requested_by' => Auth::id(),
                'tenant_id'    => Auth::user()->tenant_id
                    ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id'),
            ]);

            foreach ($validated['lines'] as $line) {
                StockRequisitionLine::create([
                    'stock_requisition_id' => $requisition->id,
                    'stock_item_id'        => $line['stock_item_id'],
                    'quantity_requested'   => $line['quantity'],
                ]);
            }

            return $requisition;
        });

        return redirect()
            ->route('economat.requisitions.show', $requisition)
            ->with('success', "Demande {$requisition->number} transmise à l'économat.");
    }

    public function show(StockRequisition $requisition): View
    {
        $this->authorizeView($requisition);

        $requisition->load('lines.item', 'requestedBy', 'reviewedBy');

        return view('economat.requisitions.show', [
            'requisition' => $requisition,
            'isKeeper'    => $this->isStoreKeeper(),
        ]);
    }

    public function approve(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper();

        try {
            $service->approve($requisition, $request->input('review_notes'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Demande {$requisition->number} validée. Vous pouvez procéder à la livraison.");
    }

    public function reject(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper();

        try {
            $service->reject($requisition, $request->input('review_notes'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Demande {$requisition->number} refusée.");
    }

    /** Livraison : déstocke les quantités réellement servies. */
    public function deliver(Request $request, StockRequisition $requisition, StockRequisitionService $service): RedirectResponse
    {
        $this->authorizeKeeper();

        $validated = $request->validate([
            'issued'   => ['nullable', 'array'],
            'issued.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $service->deliver($requisition, array_map('floatval', $validated['issued'] ?? []));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Articles livrés au département — demande {$requisition->number} clôturée.");
    }

    public function cancel(StockRequisition $requisition): RedirectResponse
    {
        // Le demandeur peut annuler sa demande ; l'économe aussi.
        if (!$this->isStoreKeeper() && $requisition->requested_by !== Auth::id()) {
            abort(403);
        }
        if (!$requisition->canBeCancelled()) {
            return back()->with('error', 'Cette demande ne peut plus être annulée.');
        }

        $requisition->update(['status' => StockRequisition::STATUS_CANCELLED]);

        return back()->with('success', "Demande {$requisition->number} annulée.");
    }

    // ── Habilitations ────────────────────────────────────────────────────────

    /** L'économe et le manager gèrent le magasin (valident, livrent). */
    private function isStoreKeeper(): bool
    {
        return Auth::user()->hasAnyRole(['econome', 'manager', 'admin']);
    }

    private function authorizeKeeper(): void
    {
        if (!$this->isStoreKeeper()) {
            abort(403, "Seul l'économat peut traiter cette demande.");
        }
    }

    private function authorizeView(StockRequisition $requisition): void
    {
        if (!$this->isStoreKeeper() && $requisition->requested_by !== Auth::id()) {
            abort(403);
        }
    }
}
