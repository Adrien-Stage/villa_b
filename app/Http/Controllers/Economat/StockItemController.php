<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockItemController extends Controller
{
    public function index(Request $request): View
    {
        $query = StockItem::with('category', 'supplier');

        // Filtre rapide sur les articles à traiter.
        if ($request->query('filter') === 'alert') {
            $query->belowThreshold();
        }

        $items = $query->orderBy('name')->get();

        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();
        $suppliers  = Supplier::active()->orderBy('name')->get();

        return view('economat.items.index', [
            'items'      => $items,
            'categories' => $categories,
            'suppliers'  => $suppliers,
            'filter'     => $request->query('filter'),
        ]);
    }

    public function show(StockItem $item): View
    {
        $item->load('category', 'supplier');

        // Historique des mouvements : l'audit de l'article.
        $movements = $item->movements()->with('user')->latest('occurred_at')->take(50)->get();

        return view('economat.items.show', compact('item', 'movements'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        StockItem::create([
            'stock_category_id' => $validated['stock_category_id'] ?? null,
            'name'              => trim($validated['name']),
            'reference'         => $validated['reference'] ?? null,
            'unit'              => $validated['unit'],
            'description'       => $validated['description'] ?? null,
            'min_stock'         => $validated['min_stock'] ?? 0,
            'supplier_id'       => $validated['supplier_id'] ?? null,
            // Le stock initial se cale via un ajustement, jamais en saisie
            // directe : ainsi toute quantité présente a un mouvement d'origine.
            'current_stock'     => 0,
            'average_cost'      => (int) ($validated['average_cost'] ?? 0) * 100,
            'is_active'         => $request->boolean('is_active', true),
            'tenant_id'         => $this->tenantId(),
        ]);

        return back()->with('success', 'Article ajouté au magasin.');
    }

    public function update(Request $request, StockItem $item): RedirectResponse
    {
        $validated = $this->validated($request, $item);

        // On ne touche pas au stock courant ici : il n'évolue que par mouvement.
        $item->update([
            'stock_category_id' => $validated['stock_category_id'] ?? null,
            'name'              => trim($validated['name']),
            'reference'         => $validated['reference'] ?? null,
            'unit'              => $validated['unit'],
            'description'       => $validated['description'] ?? null,
            'min_stock'         => $validated['min_stock'] ?? 0,
            'supplier_id'       => $validated['supplier_id'] ?? null,
            'is_active'         => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Article mis à jour.');
    }

    /**
     * Ajustement d'inventaire : cale le stock sur une quantité constatée. Passe
     * par le StockService pour journaliser l'écart.
     */
    public function adjust(Request $request, StockItem $item, StockService $stock): RedirectResponse
    {
        $validated = $request->validate([
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'reason'           => ['nullable', 'string', 'max:255'],
        ]);

        $stock->adjust($item, (float) $validated['counted_quantity'], $validated['reason'] ?? null);

        return back()->with('success', "Stock de « {$item->name} » ajusté.");
    }

    public function destroy(StockItem $item): RedirectResponse
    {
        if ($item->movements()->exists()) {
            return back()->with('error', "Cet article a un historique de mouvements : désactivez-le plutôt que de le supprimer.");
        }

        $item->delete();

        return back()->with('success', 'Article supprimé.');
    }

    private function validated(Request $request, ?StockItem $item = null): array
    {
        return $request->validate([
            'name'              => ['required', 'string', 'max:160'],
            'reference'         => ['nullable', 'string', 'max:60'],
            'unit'              => ['required', 'string', 'max:20'],
            'description'       => ['nullable', 'string', 'max:500'],
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'supplier_id'       => ['nullable', 'exists:suppliers,id'],
            'min_stock'         => ['nullable', 'numeric', 'min:0'],
            // Coût moyen initial (FCFA), utile si l'article existe déjà en stock
            // au démarrage du module ; sinon il se construit aux réceptions.
            'average_cost'      => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id
            ?? \App\Models\Tenant::where('slug', 'villa-boutanga')->value('id');
    }
}
