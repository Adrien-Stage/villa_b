<?php

namespace App\Http\Controllers;

use App\Models\RestaurantPantryItem;
use App\Models\RestaurantWasteLog;
use App\Models\Tenant;
use App\Services\RestaurantStockService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * RestaurantWasteController : Contrôle et traçabilité des pertes, déchets, casse et offerts en cuisine.
 *
 * Conforme à la comptabilité matière hôtelière (Oracle Materials Control / HACCP).
 */
class RestaurantWasteController extends Controller
{
    public function __construct(
        private readonly RestaurantStockService $stockService
    ) {}

    /**
     * Liste et suivi analytique des pertes cuisine.
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

        $query = RestaurantWasteLog::query()
            ->with(['item.category', 'recordedBy'])
            ->forTenant($tenantId)
            ->whereBetween('occurred_at', [$startDate, $endDate])
            ->latest('occurred_at');

        if ($request->filled('reason')) {
            $query->where('reason', $request->input('reason'));
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('item_id')) {
            $query->where('restaurant_pantry_item_id', (int) $request->input('item_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $likeOp = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $likeOp) {
                $q->where('reference', $likeOp, "%{$search}%")
                  ->orWhere('responsible_person', $likeOp, "%{$search}%")
                  ->orWhere('notes', $likeOp, "%{$search}%")
                  ->orWhereHas('item', fn ($iq) => $iq->where('name', $likeOp, "%{$search}%"));
            });
        }

        $logs = $query->paginate(20)->withQueryString();

        // Statistiques de la période
        $statsBase = RestaurantWasteLog::query()
            ->forTenant($tenantId)
            ->whereBetween('occurred_at', [$startDate, $endDate]);

        $totalValuationCentimes = (int) (clone $statsBase)->sum('total_cost');
        $totalDeclarations = (clone $statsBase)->count();

        // Répartition par motif
        $reasonsBreakdown = (clone $statsBase)
            ->selectRaw('reason, COUNT(*) as count, SUM(total_cost) as total_val')
            ->groupBy('reason')
            ->get()
            ->keyBy('reason');

        $activeItems = RestaurantPantryItem::query()
            ->active()
            ->orderBy('name')
            ->get();

        return view('restaurant.waste.index', [
            'logs'                   => $logs,
            'startDate'              => $startDate->toDateString(),
            'endDate'                => $endDate->toDateString(),
            'totalValuationCentimes' => $totalValuationCentimes,
            'totalDeclarations'      => $totalDeclarations,
            'reasonsBreakdown'       => $reasonsBreakdown,
            'activeItems'            => $activeItems,
            'reasons'                => RestaurantWasteLog::REASONS,
            'reasonLabels'           => RestaurantWasteLog::REASON_LABELS,
            'departmentLabels'       => RestaurantWasteLog::DEPARTMENT_LABELS,
        ]);
    }

    /**
     * Formulaire de déclaration d'une perte / mise au rebut.
     */
    public function create(): View
    {
        $items = RestaurantPantryItem::query()
            ->active()
            ->orderBy('name')
            ->get();

        return view('restaurant.waste.create', [
            'items'            => $items,
            'reasons'          => RestaurantWasteLog::REASONS,
            'reasonLabels'     => RestaurantWasteLog::REASON_LABELS,
            'departmentLabels' => RestaurantWasteLog::DEPARTMENT_LABELS,
        ]);
    }

    /**
     * Enregistrement atomique de la perte et déduction immédiate du stock.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_pantry_item_id' => ['required', 'integer', Rule::exists('restaurant_pantry_items', 'id')],
            'quantity'                  => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason'                    => ['required', 'string', Rule::in(RestaurantWasteLog::REASONS)],
            'department'                => ['required', 'string', Rule::in(array_keys(RestaurantWasteLog::DEPARTMENT_LABELS))],
            'responsible_person'        => ['nullable', 'string', 'max:120'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'occurred_at'               => ['nullable', 'date'],
        ]);

        $item = RestaurantPantryItem::findOrFail($validated['restaurant_pantry_item_id']);

        try {
            $wasteLog = $this->stockService->recordWaste(
                item: $item,
                quantity: (float) $validated['quantity'],
                reason: $validated['reason'],
                department: $validated['department'],
                responsiblePerson: $validated['responsible_person'] ?? null,
                notes: $validated['notes'] ?? null,
                occurredAt: !empty($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now(),
                tenantId: Auth::user()?->tenant_id,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['waste' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('restaurant.waste.show', $wasteLog)
            ->with('success', sprintf(
                'Perte enregistrée (#%s). %s %s de %s déduits du garde-manger (valeur : %s).',
                $wasteLog->reference,
                rtrim(rtrim(number_format($wasteLog->quantity, 3, ',', ' '), '0'), ','),
                $item->unit,
                $item->name,
                $wasteLog->formattedTotalCost(),
            ));
    }

    /**
     * Détail d'une fiche de perte.
     */
    public function show(RestaurantWasteLog $waste): View
    {
        $waste->loadMissing(['item.category', 'recordedBy', 'movement']);

        return view('restaurant.waste.show', [
            'waste'            => $waste,
            'reasonLabels'     => RestaurantWasteLog::REASON_LABELS,
            'departmentLabels' => RestaurantWasteLog::DEPARTMENT_LABELS,
        ]);
    }

    /**
     * Impression du Procès-Verbal de perte / Bon de mise au rebut.
     * Conforme aux normes d'impression professionnelle (@page, sans boutons UI).
     */
    public function print(RestaurantWasteLog $waste): View
    {
        $waste->loadMissing(['item.category', 'recordedBy', 'movement', 'tenant']);

        return view('restaurant.waste.print', [
            'waste'            => $waste,
            'reasonLabels'     => RestaurantWasteLog::REASON_LABELS,
            'departmentLabels' => RestaurantWasteLog::DEPARTMENT_LABELS,
        ]);
    }
}
