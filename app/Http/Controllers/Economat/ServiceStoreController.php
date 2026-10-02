<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\ServiceStore;
use App\Models\StockRequisition;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Dépôts de service : étages, mini-bar, bar, pâtisserie… L'économe les
 * déclare ; les livraisons de l'économat les alimentent.
 */
class ServiceStoreController extends Controller
{
    public function index(): View
    {
        $stores = ServiceStore::query()
            ->with('stocks')
            ->withCount('stocks')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('economat.stores.index', [
            'stores'      => $stores,
            'departments' => StockRequisition::DEPARTMENTS,
            'canManage'   => app(PermissionResolver::class)->allows(auth()->user(), 'economat.stores.modifier'),
        ]);
    }

    public function show(ServiceStore $store): View
    {
        $store->load(['stocks' => fn ($q) => $q->with('item.category')]);

        $stocks = $store->stocks
            ->filter(fn ($s) => $s->item !== null)
            ->sortBy(fn ($s) => $s->item->name);

        // Historique du dépôt : l'audit de ce qui y est entré et sorti.
        $movements = $store->movements()->with('item', 'user')->latest('occurred_at')->latest('id')->take(50)->get();

        return view('economat.stores.show', compact('store', 'stocks', 'movements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        ServiceStore::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => true,
            'tenant_id'  => auth()->user()->tenant_id ?? \App\Models\Tenant::current()?->id,
        ]);

        return back()->with('success', "Dépôt « {$validated['name']} » créé.");
    }

    public function update(Request $request, ServiceStore $store): RedirectResponse
    {
        $validated = $this->validated($request, $store);

        // Changer de service déplacerait le centre qui porte sa consommation
        // passée : refusé tant que le dépôt a un historique.
        if ($validated['department'] !== $store->department && $store->movements()->exists()) {
            return back()->with('error', "Le dépôt « {$store->name} » a déjà des mouvements : son service ne change plus.");
        }

        $store->update([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Dépôt « {$store->name} » mis à jour.");
    }

    public function destroy(ServiceStore $store): RedirectResponse
    {
        if ($store->movements()->exists()) {
            return back()->with('error', "Le dépôt « {$store->name} » a un historique : désactivez-le plutôt que de le supprimer.");
        }

        $store->delete();

        return back()->with('success', 'Dépôt supprimé.');
    }

    /** @return array{name: string, department: string, sort_order?: int|null} */
    private function validated(Request $request, ?ServiceStore $store = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:80', Rule::unique('service_stores', 'name')->ignore($store?->id)],
            'department' => ['required', Rule::in(array_keys(StockRequisition::DEPARTMENTS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
