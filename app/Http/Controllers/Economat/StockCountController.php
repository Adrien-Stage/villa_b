<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Services\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class StockCountController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function __construct(private StockCountService $stockCountService)
    {
    }

    public function index(Request $request): View
    {
        $query = StockCount::with(['category', 'openedBy', 'closedBy'])->withCount('lines');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('stock_category_id', $request->input('category'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('count_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('count_date', '<=', $request->input('date_to'));
        }

        $counts = $query->orderByDesc('count_date')->orderByDesc('id')->paginate(self::PAR_PAGE)->withQueryString();

        $openCount = StockCount::where('status', StockCount::STATUS_DRAFT)->latest('id')->first();
        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();

        // Statistiques globales d'inventaire
        $stats = [
            'total_counts'  => StockCount::count(),
            'closed_counts' => StockCount::where('status', StockCount::STATUS_CLOSED)->count(),
            'total_losses'  => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('loss_value'),
            'total_surplus' => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('surplus_value'),
            'net_variance'  => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('variance_value'),
        ];

        return view('economat.stock_counts.index', [
            'counts'     => $counts,
            'openCount'  => $openCount,
            'categories' => $categories,
            'stats'      => $stats,
            'canManage'  => Auth::user()?->hasAnyRole(['econome', 'manager', 'admin']) ?? false,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $existing = StockCount::where('status', StockCount::STATUS_DRAFT)->first();
        if ($existing) {
            return redirect()
                ->route('economat.stock_counts.show', $existing)
                ->with('error', "L'inventaire {$existing->reference} est déjà en cours. Veuillez le clôturer ou l'annuler avant d'en ouvrir un nouveau.");
        }

        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('economat.stock_counts.create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'count_date'        => ['nullable', 'date'],
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $count = $this->stockCountService->open($validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('economat.stock_counts.show', $count)
            ->with('success', "Feuille d'inventaire {$count->reference} ouverte. Vous pouvez saisir les quantités constatées en rayon.");
    }

    public function show(StockCount $stockCount): View
    {
        $stockCount->load(['category', 'openedBy', 'closedBy', 'lines.item.category']);

        $lines = $stockCount->lines->sortBy(fn (StockCountLine $l) => $l->item?->name ?? '');

        return view('economat.stock_counts.show', [
            'count'     => $stockCount,
            'lines'     => $lines,
            'reasons'   => StockCountLine::REASONS,
            'canManage' => Auth::user()?->hasAnyRole(['econome', 'manager', 'admin']) ?? false,
        ]);
    }

    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Cet inventaire est déjà clôturé et ne peut plus être modifié.");
        }

        $validated = $request->validate([
            'lines'                    => ['required', 'array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.reason'           => ['nullable', 'string', 'max:50'],
            'lines.*.notes'            => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->stockCountService->updateCounts($stockCount, $validated['lines']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Comptages physiques et motifs enregistrés avec succès.");
    }

    public function close(Request $request, StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Cet inventaire est déjà clôturé.");
        }

        try {
            $stockCount = $this->stockCountService->close($stockCount, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $varianceFCFA = number_format(abs($stockCount->variance_value) / 100, 0, ',', ' ');
        $sign = $stockCount->variance_value < 0 ? '-' : ($stockCount->variance_value > 0 ? '+' : '');

        return redirect()
            ->route('economat.stock_counts.show', $stockCount)
            ->with('success', "Inventaire {$stockCount->reference} clôturé. Les stocks de l'économat ont été régularisés. Écart net : {$sign}{$varianceFCFA} FCFA.");
    }

    public function cancel(StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Un inventaire clôturé ne peut pas être annulé.");
        }

        try {
            $this->stockCountService->cancel($stockCount);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stock_counts.index')
            ->with('success', "Feuille d'inventaire {$stockCount->reference} annulée.");
    }

    /**
     * Procès-Verbal officiel d'inventaire physique et d'écarts de stocks (PV).
     * Vue imprimable conforme aux standards d'audit et sans interface web polluante.
     */
    public function report(StockCount $stockCount): View
    {
        $stockCount->load(['category', 'openedBy', 'closedBy', 'lines.item.category']);

        $lines = $stockCount->lines->sortBy(fn (StockCountLine $l) => $l->item?->name ?? '');

        return view('economat.stock_counts.report', [
            'count' => $stockCount,
            'lines' => $lines,
        ]);
    }
}
