<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::withCount('stockItems', 'purchaseOrders')
            ->orderBy('name')
            ->get();

        return view('economat.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request) + [
            'is_active' => $request->boolean('is_active', true),
            'tenant_id' => $this->tenantId(),
        ]);

        return back()->with('success', 'Fournisseur ajouté.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier) + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        // Les articles et bons rattachés passent en nullOnDelete / cascade : on
        // évite néanmoins de perdre un fournisseur encore lié à des commandes.
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', "Ce fournisseur a des bons de commande : désactivez-le plutôt que de le supprimer.");
        }

        $supplier->delete();

        return back()->with('success', 'Fournisseur supprimé.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:160', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'code'         => ['nullable', 'string', 'max:30'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email'        => ['nullable', 'email', 'max:150'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'Un fournisseur portant ce nom existe déjà.',
        ]);
    }

    private function tenantId(): ?int
    {
        return auth()->user()->tenant_id
            ?? \App\Models\Tenant::current()?->id;
    }
}
