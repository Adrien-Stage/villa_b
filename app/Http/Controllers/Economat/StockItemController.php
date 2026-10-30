<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\StorageZone;
use App\Models\Supplier;
use App\Services\StockAccountService;
use App\Services\StockService;
use App\Services\SupplierTariffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockItemController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function index(Request $request): View
    {
        // Le nombre de mouvements dit si l'article peut encore recevoir sa reprise.
        $query = StockItem::with('category', 'supplier', 'packagings', 'zone')->withCount('movements');

        // Filtre rapide sur les articles à traiter.
        if ($request->query('filter') === 'alert') {
            $query->belowThreshold();
        }

        // Une zone de rangement : ce qui se trouve en cave, en chambre froide…
        $zones = StorageZone::query()->dansLOrdre()->get();
        $zone = $zones->firstWhere('id', (int) $request->query('zone'));
        if ($zone) {
            $query->where('storage_zone_id', $zone->id);
        }

        // Recherche à la saisie : le nom, la référence ou la catégorie.
        $recherche = trim((string) $request->query('q', ''));
        if ($recherche !== '') {
            $motif = '%' . mb_strtolower($recherche) . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$motif])
                ->orWhereRaw('LOWER(reference) LIKE ?', [$motif])
                ->orWhereHas('category', fn ($c) => $c->whereRaw('LOWER(name) LIKE ?', [$motif])));
        }

        $items = $query->orderBy('name')->paginate(self::PAR_PAGE)->withQueryString();

        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();
        $suppliers  = Supplier::active()->orderBy('name')->get();

        return view('economat.items.index', [
            'items'      => $items,
            'categories' => $categories,
            'suppliers'  => $suppliers,
            'unites'     => StockUnit::selectOptions(),
            'filter'     => $request->query('filter'),
            'recherche'  => $recherche,
            'zones'      => $zones,
            'zone'       => $zone,
        ]);
    }

    public function show(StockItem $item): View
    {
        // Les tarifs du moins cher au plus cher ; ceux sans prix à la fin.
        $item->load(['category', 'supplier', 'packagings', 'zone', 'suppliers' => fn ($q) => $q
            ->orderByRaw('CASE WHEN stock_item_supplier.purchase_price > 0 THEN 0 ELSE 1 END')
            ->orderBy('stock_item_supplier.purchase_price')->orderBy('suppliers.name')]);

        // Historique des mouvements : l'audit de l'article.
        $movements = $item->movements()->with('user', 'item')->latest('occurred_at')->latest('id')->take(50)->get();

        $allSuppliers = Supplier::active()->orderBy('name')->get();
        $zones = StorageZone::active()->dansLOrdre()->get();

        return view('economat.items.show', compact('item', 'movements', 'allSuppliers', 'zones'));
    }

    /** Fixe le prix de l'article chez un fournisseur, depuis la fiche de l'article. */
    public function linkSupplier(Request $request, StockItem $item, SupplierTariffService $tarifs): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            // FCFA, dans l'unité de stock de l'article.
            'purchase_price' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], [
            'purchase_price.gt' => 'Le prix d\'achat est supérieur à 0.',
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $prix = (int) round((float) $validated['purchase_price'] * 100);

        DB::transaction(fn () => $tarifs->fixer($item, $supplier, $prix));

        AuditLog::record(Auth::id(), 'stock_item_tarif',
            "Tarif de « {$item->name} » chez {$supplier->name} : " . number_format($prix / 100, 0, ',', ' ') . ' FCFA',
            'economat', ['stock_item_id' => $item->id, 'supplier_id' => $supplier->id, 'prix' => $prix]);

        return back()->with('success', "Prix de {$supplier->name} enregistré pour cet article.");
    }

    public function unlinkSupplier(StockItem $item, Supplier $supplier, SupplierTariffService $tarifs): RedirectResponse
    {
        DB::transaction(fn () => $tarifs->retirer($item, $supplier));

        AuditLog::record(Auth::id(), 'stock_item_tarif',
            "Tarif de « {$item->name} » chez {$supplier->name} retiré",
            'economat', ['stock_item_id' => $item->id, 'supplier_id' => $supplier->id]);

        return back()->with('success', 'Fournisseur détaché de cet article.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        StockItem::create([
            'stock_category_id' => $validated['stock_category_id'] ?? null,
            // Le service achats propose la zone ; l'économat la change ensuite.
            'storage_zone_id'   => $validated['storage_zone_id'] ?? StorageZone::parDefaut()?->id,
            'name'              => trim($validated['name']),
            'reference'         => $validated['reference'] ?? null,
            'unit'              => StockUnit::symbolFor($validated['unit']),
            'description'       => $validated['description'] ?? null,
            'min_stock'         => $validated['min_stock'] ?? 0,
            // Les fournisseurs et leurs prix se tiennent au catalogue de
            // chaque fournisseur, ou sur la fiche de l'article.
            // Le stock initial se cale via un ajustement, jamais en saisie
            // directe : ainsi toute quantité présente a un mouvement d'origine.
            'current_stock'     => 0,
            'average_cost'      => (int) ($validated['average_cost'] ?? 0) * 100,
            'is_active'         => $request->boolean('is_active', true),
            'tenant_id'         => $this->tenantId(),
        ]);

        return back()->with('success', 'Article ajouté au magasin.');
    }

    public function update(Request $request, StockItem $item, StockAccountService $stockAccounts): RedirectResponse
    {
        $validated = $this->validated($request, $item);

        try {
            DB::transaction(function () use ($request, $item, $validated, $stockAccounts) {
                // On ne touche pas au stock courant ici : il n'évolue que par mouvement.
                $item->update([
                    'name'        => trim($validated['name']),
                    'reference'   => $validated['reference'] ?? null,
                    'unit'        => StockUnit::symbolFor($validated['unit']),
                    'description' => $validated['description'] ?? null,
                    'min_stock'   => $validated['min_stock'] ?? 0,
                    'is_active'   => $request->boolean('is_active', true),
                ]);

                // Changer de catégorie peut changer de compte de stock : la
                // valeur déjà en stock suit l'article au grand livre.
                $stockAccounts->moveItem($item, $validated['stock_category_id'] ?? null);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Article inchangé : ' . $e->getMessage());
        }

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

    /**
     * Reprise du stock initial : quantité déjà en magasin et son coût, saisis
     * une fois, avant tout autre mouvement de l'article.
     */
    public function opening(Request $request, StockItem $item, StockService $stock): RedirectResponse
    {
        $validated = $request->validate([
            'quantity'  => ['required', 'numeric', 'gt:0', 'max:99999999'],
            // Coût unitaire en FCFA, stocké en centimes.
            'unit_cost' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $stock->recordOpening($item, (float) $validated['quantity'], (int) round((float) $validated['unit_cost'] * 100));
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Stock initial de « {$item->name} » repris. Sa valeur entrera au grand livre par les à-nouveaux.");
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
            // L'unité se choisit dans la liste tenue en Paramètres › Économat.
            'unit'              => ['required', 'string', Rule::in(StockUnit::choix($item?->unit))],
            'description'       => ['nullable', 'string', 'max:500'],
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'storage_zone_id'   => ['nullable', Rule::exists('storage_zones', 'id')->where('is_active', true)],
            'min_stock'         => ['nullable', 'numeric', 'min:0'],
            // Coût moyen initial (FCFA), utile si l'article existe déjà en stock
            // au démarrage du module ; sinon il se construit aux réceptions.
            'average_cost'      => ['nullable', 'integer', 'min:0'],
        ], [
            'unit.required' => "Choisissez l'unité de l'article.",
            'unit.in'       => "Cette unité n'est pas dans la liste : ajoutez-la dans Paramètres › Économat.",
        ]);
    }

    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id
            ?? \App\Models\Tenant::current()?->id;
    }
}
