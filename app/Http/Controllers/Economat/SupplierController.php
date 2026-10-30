<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Services\SupplierTariffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    public function index(Request $request): View
    {
        $search   = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $status   = trim((string) $request->query('status', ''));

        $query = Supplier::query()
            ->withCount('stockItems', 'purchaseOrders')
            // Colonnes qualifiées : la table des tarifs a aussi un id et un
            // supplier_id, et une colonne ambiguë fait échouer la requête.
            ->with(['stockItems' => function ($q) {
                $q->select('stock_items.id', 'stock_items.name', 'stock_items.reference', 'stock_items.unit',
                    'stock_items.last_purchase_price', 'stock_items.supplier_id', 'stock_items.stock_category_id')
                    ->orderBy('stock_items.name');
            }]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($category !== '' && array_key_exists($category, Supplier::CATEGORIES)) {
            $query->where('category', $category);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query->orderBy('name')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        // Articles actifs disponibles pour association directe
        $availableItems = StockItem::active()
            ->with('category:id,name')
            ->select('id', 'name', 'reference', 'unit', 'last_purchase_price', 'supplier_id', 'stock_category_id')
            ->orderBy('name')
            ->get();

        // Indicateurs clés (KPIs)
        $kpis = [
            'total_suppliers'    => Supplier::count(),
            'active_suppliers'   => Supplier::active()->count(),
            'with_email'         => Supplier::whereNotNull('email')->where('email', '!=', '')->count(),
            'total_items_linked' => DB::table('stock_item_supplier')->distinct()->count('stock_item_id'),
        ];

        $categories     = Supplier::CATEGORIES;
        $paymentTerms   = Supplier::PAYMENT_TERMS;
        $paymentMethods = Supplier::PAYMENT_METHODS;

        return view('economat.suppliers.index', compact(
            'suppliers', 'availableItems', 'kpis', 'categories', 'paymentTerms', 'paymentMethods'
        ));
    }

    public function store(Request $request, SupplierTariffService $tarifs): RedirectResponse
    {
        $validated = $this->validated($request);
        $catalogue = $this->catalogueSaisi($request);

        [$supplier, $bilan] = DB::transaction(function () use ($request, $validated, $catalogue, $tarifs) {
            $supplier = Supplier::create($validated + [
                'is_active' => $request->boolean('is_active', true),
                'tenant_id' => $this->tenantId(),
            ]);

            return [$supplier, $tarifs->catalogue($supplier, $catalogue)];
        });

        // Journal d'audit
        AuditLog::record(
            Auth::id(),
            'supplier_create',
            "Création du fournisseur « {$supplier->name} » (" . ($supplier->category_label ?? 'Général') . ")"
                . ($bilan['ajoutes'] > 0 ? " — {$bilan['ajoutes']} article(s) au catalogue" : ''),
            'economat',
            ['supplier_id' => $supplier->id, 'catalogue' => $catalogue]
        );

        return back()->with('success', "Fournisseur « {$supplier->name} » ajouté avec succès.");
    }

    public function update(Request $request, Supplier $supplier, SupplierTariffService $tarifs): RedirectResponse
    {
        $validated = $this->validated($request, $supplier);
        $catalogue = $this->catalogueSaisi($request);

        $bilan = DB::transaction(function () use ($request, $supplier, $validated, $catalogue, $tarifs) {
            $supplier->update($validated + [
                'is_active' => $request->boolean('is_active', true),
            ]);

            return $tarifs->catalogue($supplier, $catalogue);
        });

        $changements = array_filter([
            $bilan['ajoutes'] > 0 ? "{$bilan['ajoutes']} article(s) ajouté(s)" : null,
            $bilan['modifies'] > 0 ? "{$bilan['modifies']} prix modifié(s)" : null,
            $bilan['retires'] > 0 ? "{$bilan['retires']} article(s) retiré(s)" : null,
        ]);

        // Journal d'audit
        AuditLog::record(
            Auth::id(),
            'supplier_update',
            "Mise à jour du fournisseur « {$supplier->name} »" . ($changements !== [] ? ' — catalogue : ' . implode(', ', $changements) : ''),
            'economat',
            ['supplier_id' => $supplier->id, 'catalogue' => $catalogue]
        );

        return back()->with('success', "Fournisseur « {$supplier->name} » mis à jour avec succès.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', "Ce fournisseur a des bons de commande enregistrés : désactivez-le plutôt que de le supprimer pour préserver l'historique.");
        }

        // Ses articles sont détachés par la base (supplier_id mis à nul) : la
        // corbeille note lesquels, pour les lui rendre à la restauration.
        $supplier->delete();

        // Journal d'audit
        AuditLog::record(
            Auth::id(),
            'supplier_delete',
            "Suppression du fournisseur « {$supplier->name} »",
            'economat',
            ['supplier_id' => $supplier->id]
        );

        return back()->with('success', 'Fournisseur supprimé.');
    }

    /**
     * Le catalogue saisi dans le formulaire : article => prix en centimes,
     * dans l'unité de stock de l'article. Un prix est obligatoire : un tarif
     * sans prix ne sert à rien, et 0 ferait croire à un article gratuit.
     *
     * @return array<int, int>
     */
    private function catalogueSaisi(Request $request): array
    {
        $request->validate([
            'linked_items'         => ['nullable', 'array'],
            'linked_items.*.id'    => ['required', 'integer', 'distinct', 'exists:stock_items,id'],
            'linked_items.*.price' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], [
            'linked_items.*.price.required' => 'Chaque article du catalogue a son prix.',
            'linked_items.*.price.gt'       => 'Chaque article du catalogue a un prix supérieur à 0.',
            'linked_items.*.id.distinct'    => 'Un même article figure deux fois au catalogue.',
        ]);

        $catalogue = [];
        foreach ((array) $request->input('linked_items', []) as $ligne) {
            $catalogue[(int) $ligne['id']] = (int) round((float) $ligne['price'] * 100);
        }

        return $catalogue;
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name'                    => ['required', 'string', 'max:160', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'code'                    => ['nullable', 'string', 'max:30'],
            'category'                => ['nullable', 'string', Rule::in(array_keys(Supplier::CATEGORIES))],
            'tax_id'                  => ['nullable', 'string', 'max:60'],
            'rccm'                    => ['nullable', 'string', 'max:60'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'contact_name'            => ['nullable', 'string', 'max:120'],
            'email'                   => ['nullable', 'email', 'max:150'],
            'phone'                   => ['nullable', 'string', 'max:30'],
            'address'                 => ['nullable', 'string', 'max:255'],
            'payment_terms'           => ['nullable', 'string', Rule::in(array_keys(Supplier::PAYMENT_TERMS))],
            'payment_method'          => ['nullable', 'string', Rule::in(array_keys(Supplier::PAYMENT_METHODS))],
            'delivery_lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'bank_details'            => ['nullable', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Le nom ou raison sociale du fournisseur est obligatoire.',
            'name.unique'   => 'Un fournisseur portant ce nom existe déjà.',
            'email.email'   => 'L\'adresse email doit être valide pour l\'envoi des commandes.',
        ]);
    }

    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id
            ?? \App\Models\Tenant::current()?->id;
    }
}
