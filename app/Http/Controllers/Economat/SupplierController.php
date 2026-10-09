<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\Supplier;
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
            ->with(['stockItems' => function ($q) {
                $q->select('id', 'name', 'reference', 'unit', 'last_purchase_price', 'average_cost', 'supplier_id', 'stock_category_id');
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
            ->select('id', 'name', 'reference', 'unit', 'last_purchase_price', 'average_cost', 'supplier_id', 'stock_category_id')
            ->orderBy('name')
            ->get();

        // Indicateurs clés (KPIs)
        $kpis = [
            'total_suppliers'    => Supplier::count(),
            'active_suppliers'   => Supplier::active()->count(),
            'with_email'         => Supplier::whereNotNull('email')->where('email', '!=', '')->count(),
            'total_items_linked' => StockItem::whereNotNull('supplier_id')->count(),
        ];

        $categories     = Supplier::CATEGORIES;
        $paymentTerms   = Supplier::PAYMENT_TERMS;
        $paymentMethods = Supplier::PAYMENT_METHODS;

        return view('economat.suppliers.index', compact(
            'suppliers', 'availableItems', 'kpis', 'categories', 'paymentTerms', 'paymentMethods'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $supplier = DB::transaction(function () use ($request, $validated) {
            $supplier = Supplier::create($validated + [
                'is_active' => $request->boolean('is_active', true),
                'tenant_id' => $this->tenantId(),
            ]);

            // Synchronisation des articles associés
            $this->syncSupplierItems($supplier, $request->input('linked_items', []));

            return $supplier;
        });

        // Journal d'audit
        AuditLog::record(
            Auth::id(),
            'supplier_create',
            "Création du fournisseur « {$supplier->name} » (" . ($supplier->category_label ?? 'Général') . ")",
            'economat',
            ['supplier_id' => $supplier->id]
        );

        return back()->with('success', "Fournisseur « {$supplier->name} » ajouté avec succès.");
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $this->validated($request, $supplier);

        DB::transaction(function () use ($request, $supplier, $validated) {
            $supplier->update($validated + [
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Synchronisation des articles associés
            $this->syncSupplierItems($supplier, $request->input('linked_items', []));
        });

        // Journal d'audit
        AuditLog::record(
            Auth::id(),
            'supplier_update',
            "Mise à jour du fournisseur « {$supplier->name} »",
            'economat',
            ['supplier_id' => $supplier->id]
        );

        return back()->with('success', "Fournisseur « {$supplier->name} » mis à jour avec succès.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', "Ce fournisseur a des bons de commande enregistrés : désactivez-le plutôt que de le supprimer pour préserver l'historique.");
        }

        DB::transaction(function () use ($supplier) {
            // Détachement des articles
            $supplier->stockItems()->update(['supplier_id' => null]);
            $supplier->delete();
        });

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

    protected function syncSupplierItems(Supplier $supplier, array $linkedItems): void
    {
        $submittedIds = [];
        foreach ($linkedItems as $entry) {
            $id = is_array($entry) ? (int) ($entry['id'] ?? 0) : (int) $entry;
            if ($id > 0) {
                $submittedIds[] = $id;
            }
        }

        // Dissocier les articles qui ne sont plus dans la liste
        StockItem::where('supplier_id', $supplier->id)
            ->whereNotIn('id', $submittedIds)
            ->update(['supplier_id' => null]);

        // Mettre à jour / lier les articles sélectionnés
        foreach ($linkedItems as $entry) {
            $id = is_array($entry) ? (int) ($entry['id'] ?? 0) : (int) $entry;
            if ($id <= 0) {
                continue;
            }

            $item = StockItem::find($id);
            if (!$item) {
                continue;
            }

            $data = ['supplier_id' => $supplier->id];
            if (is_array($entry)) {
                if (isset($entry['price']) && is_numeric($entry['price']) && $entry['price'] >= 0) {
                    $data['last_purchase_price'] = (int) round((float) $entry['price'] * 100);
                }
                // Seule une unité de la liste (Paramètres › Économat) remplace
                // celle de l'article.
                if (!empty($entry['unit']) && is_string($entry['unit'])
                    && ($unite = StockUnit::canonique($entry['unit'])) !== null) {
                    $data['unit'] = $unite;
                }
            }

            $item->update($data);
        }
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
