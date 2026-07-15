<?php

namespace App\Http\Controllers;

use App\Models\RestaurantStockCount;
use App\Services\RestaurantStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * L'inventaire physique : on compte réellement le garde-manger et on confronte le
 * résultat au stock théorique tenu par les fiches techniques.
 *
 * L'écart est le chiffre qui compte. Le secteur tolère 2 à 5 % ; au-delà, il y a
 * du gaspillage, du sur-portionnage ou du vol.
 */
class RestaurantStockCountController extends Controller
{
    public function __construct(private readonly RestaurantStockService $stock)
    {
    }

    public function index(): View
    {
        $counts = RestaurantStockCount::query()
            ->with(['openedBy', 'closedBy'])
            ->withCount('lines')
            ->latest('id')
            ->paginate(20);

        $openCount = RestaurantStockCount::query()
            ->where('status', RestaurantStockCount::STATUS_DRAFT)
            ->latest('id')
            ->first();

        return view('restaurant.stock_counts.index', [
            'counts' => $counts,
            'openCount' => $openCount,
            'canManage' => Auth::user()->hasRole('restaurant_chief'),
        ]);
    }

    public function show(RestaurantStockCount $stockCount): View
    {
        $stockCount->load(['lines.item.category', 'openedBy', 'closedBy']);

        $lines = $stockCount->lines->sortBy(fn ($line) => $line->item?->name ?? '');

        return view('restaurant.stock_counts.show', [
            'count' => $stockCount,
            'lines' => $lines,
            'canManage' => Auth::user()->hasRole('restaurant_chief'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Un seul inventaire ouvert à la fois : deux feuilles concurrentes figeraient
        // deux stocks théoriques différents.
        $existing = RestaurantStockCount::query()
            ->where('status', RestaurantStockCount::STATUS_DRAFT)
            ->first();

        if ($existing) {
            return redirect()
                ->route('restaurant.stock_counts.show', $existing)
                ->withErrors(['count' => 'Un inventaire est déjà en cours. Clôture-le avant d\'en ouvrir un autre.']);
        }

        $count = $this->stock->openStockCount($validated['notes'] ?? null);

        return redirect()
            ->route('restaurant.stock_counts.show', $count)
            ->with('success', "Inventaire {$count->reference} ouvert. Saisis les quantités réellement comptées.");
    }

    /**
     * Saisie des quantités comptées. Les lignes laissées vides sont considérées
     * comme non comptées et n'entraîneront aucun ajustement à la clôture.
     */
    public function update(Request $request, RestaurantStockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return redirect()
                ->route('restaurant.stock_counts.show', $stockCount)
                ->withErrors(['count' => 'Cet inventaire est clôturé : il n\'est plus modifiable.']);
        }

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $stockCount->load('lines');

        DB::transaction(function () use ($stockCount, $validated) {
            foreach ($stockCount->lines as $line) {
                if (!array_key_exists($line->id, $validated['lines'])) {
                    continue;
                }

                $input = $validated['lines'][$line->id];
                $counted = $input['counted_quantity'] ?? null;

                $variance = $counted === null
                    ? 0
                    : (float) $counted - (float) $line->theoretical_quantity;

                $line->update([
                    'counted_quantity' => $counted === null ? null : (float) $counted,
                    'variance_quantity' => round($variance, 3),
                    'variance_value' => (int) round($variance * (float) $line->unit_cost),
                    'notes' => $input['notes'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('restaurant.stock_counts.show', $stockCount)
            ->with('success', 'Comptage enregistré.');
    }

    public function close(RestaurantStockCount $stockCount): RedirectResponse
    {
        try {
            $stockCount = $this->stock->closeStockCount($stockCount);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('restaurant.stock_counts.show', $stockCount)
                ->withErrors(['count' => $e->getMessage()]);
        }

        $variance = (int) $stockCount->variance_value;

        $message = $variance === 0
            ? "Inventaire {$stockCount->reference} clôturé : aucun écart."
            : sprintf(
                'Inventaire %s clôturé. Écart valorisé : %s FCFA. Le stock est aligné sur le comptage réel.',
                $stockCount->reference,
                number_format($variance / 100, 0, ',', ' '),
            );

        return redirect()
            ->route('restaurant.stock_counts.show', $stockCount)
            ->with('success', $message);
    }

    public function destroy(RestaurantStockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return redirect()
                ->route('restaurant.stock_counts.index')
                ->withErrors(['count' => 'Un inventaire clôturé ne se supprime pas : il fait partie de la piste d\'audit.']);
        }

        $stockCount->delete();

        return redirect()
            ->route('restaurant.stock_counts.index')
            ->with('success', 'Feuille de comptage abandonnée.');
    }
}
