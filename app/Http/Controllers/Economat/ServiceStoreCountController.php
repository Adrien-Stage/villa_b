<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Services\PermissionResolver;
use App\Services\ServiceStoreCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Inventaire d'un dépôt de service : ouverture, comptage, clôture. */
class ServiceStoreCountController extends Controller
{
    public function __construct(private ServiceStoreCountService $counts)
    {
    }

    public function store(Request $request, ServiceStore $store): RedirectResponse
    {
        try {
            $inventaire = $this->counts->open($store, $request->user(), $request->input('notes'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stores.counts.show', $inventaire)
            ->with('success', "Inventaire {$inventaire->reference} ouvert : le dépôt ne reçoit plus de livraison jusqu'à sa clôture.");
    }

    public function show(ServiceStoreCount $count): View
    {
        $count->load('store', 'openedBy', 'closedBy', 'lines.item.category');

        return view('economat.stores.count', [
            'count'     => $count,
            'lines'     => $count->lines->sortBy(fn ($l) => $l->item?->name ?? ''),
            'canCount'  => app(PermissionResolver::class)->allows(auth()->user(), 'economat.stores.counts.modifier'),
            'canClose'  => app(PermissionResolver::class)->allows(auth()->user(), 'economat.stores.counts.close'),
        ]);
    }

    public function update(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        $validated = $request->validate([
            'lines'                    => ['required', 'array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.notes'            => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->counts->updateCounts($count, $validated['lines']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Comptages enregistrés.');
    }

    public function close(Request $request, ServiceStoreCount $count): RedirectResponse
    {
        try {
            $count = $this->counts->close($count, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $consommation = number_format($count->consumptionValue() / 100, 0, ',', ' ');

        return back()->with('success', "Inventaire {$count->reference} clôturé. Consommation constatée : {$consommation} FCFA.");
    }

    public function cancel(ServiceStoreCount $count): RedirectResponse
    {
        try {
            $this->counts->cancel($count);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stores.show', $count->service_store_id)
            ->with('success', "Inventaire {$count->reference} annulé : le dépôt reçoit de nouveau les livraisons.");
    }
}
