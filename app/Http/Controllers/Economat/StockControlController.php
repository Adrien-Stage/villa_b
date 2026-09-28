<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\StockControlService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * StockControlController : Tableau de bord de contrôle des stocks, valorisation consolidée,
 * alertes de réapprovisionnement, propositions de commande et audit des écarts.
 */
class StockControlController extends Controller
{
    public function __construct(
        private readonly StockControlService $controlService
    ) {}

    /**
     * Tableau de bord de contrôle général des stocks (Économat & Restaurant).
     */
    public function index(Request $request): View
    {
        $tenantId = Auth::user()?->tenant_id ?? Tenant::first()?->id;

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $report = $this->controlService->getExecutiveReport($startDate, $endDate, $tenantId);

        return view('economat.control.index', [
            'report'    => $report,
            'startDate' => $startDate->toDateString(),
            'endDate'   => $endDate->toDateString(),
        ]);
    }

    /**
     * Propositions automatiques de commande pour réapprovisionnement des stocks bas.
     */
    public function suggestions(Request $request): View
    {
        $tenantId = Auth::user()?->tenant_id ?? Tenant::first()?->id;

        $suggestions = $this->controlService->generateOrderSuggestions($tenantId);

        return view('economat.control.suggestions', [
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Transforme les propositions de commande sélectionnées en Demande d'Achat interne.
     */
    public function generatePurchaseRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.item_id'  => ['required', 'integer', 'exists:stock_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.notes'    => ['nullable', 'string', 'max:255'],
            'priority'         => ['nullable', 'string', 'in:low,normal,urgent'],
        ]);

        try {
            $purchaseRequest = $this->controlService->createPurchaseRequestFromSuggestions(
                selectedItems: $validated['items'],
                user: Auth::user(),
                tenantId: Auth::user()?->tenant_id,
                priority: $validated['priority'] ?? 'normal'
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['suggestions' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('economat.purchase_requests.show', $purchaseRequest)
            ->with('success', sprintf(
                'Demande d\'achat #%s créée avec succès à partir des propositions de réapprovisionnement (%d article(s)).',
                $purchaseRequest->number,
                $purchaseRequest->lines->count()
            ));
    }

    /**
     * Analyse et audit des écarts d'inventaires physiques (Économat & Restaurant).
     */
    public function variances(Request $request): View
    {
        $tenantId = Auth::user()?->tenant_id ?? Tenant::first()?->id;

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $variances = $this->controlService->getInventoryVariancesSummary($startDate, $endDate, $tenantId);

        return view('economat.control.variances', [
            'variances' => $variances,
            'startDate' => $startDate->toDateString(),
            'endDate'   => $endDate->toDateString(),
        ]);
    }

    /**
     * Impression officielle du Rapport de Contrôle & Food Cost.
     * Conforme aux normes d'impression (@page, sans boutons UI).
     */
    public function printReport(Request $request): View
    {
        $tenantId = Auth::user()?->tenant_id ?? Tenant::first()?->id;

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $report = $this->controlService->getExecutiveReport($startDate, $endDate, $tenantId);

        return view('economat.control.print', [
            'report'    => $report,
            'startDate' => $startDate->toDateString(),
            'endDate'   => $endDate->toDateString(),
            'tenant'    => Tenant::find($tenantId) ?? Tenant::first(),
        ]);
    }
}
