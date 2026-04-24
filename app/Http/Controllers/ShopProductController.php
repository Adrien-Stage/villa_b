<?php

namespace App\Http\Controllers;

use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopProductController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = auth()->user()->tenant;
        $query = ShopProduct::where('tenant_id', $tenant->id)
            ->with('category');

        // Filtres
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category')) {
            $query->where('shop_category_id', $categoryId);
        }

        if ($status = $request->input('status')) {
            $query->where('is_active', $status === 'active');
        }

        $products = $query->orderBy('name')->paginate(20);
        $categories = ShopCategory::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get();

        return view('shop.products.index', [
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        $tenant = auth()->user()->tenant;
        $categories = ShopCategory::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get();

        $nextSku = $this->generateSku($tenant->id);

        return view('shop.products.create', [
            'categories' => $categories,
            'nextSku' => $nextSku,
        ]);
    }

    public function store(Request $request)
    {
        $tenant = auth()->user()->tenant;

        // Auto-générer le SKU si vide
        if (!$request->filled('sku')) {
            $request->merge(['sku' => $this->generateSku($tenant->id)]);
        }

        $validated = $request->validate([
            'shop_category_id' => 'required|exists:shop_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|unique:shop_products,sku',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
        ]);

        // Vérifier que la catégorie appartient au tenant
        ShopCategory::where('tenant_id', $tenant->id)
            ->where('id', $validated['shop_category_id'])
            ->firstOrFail();

        // Convertir le prix en centimes
        $validated['price'] = (int)($validated['price'] * 100);
        $validated['tenant_id'] = $tenant->id;
        $validated['is_active'] = $request->has('is_active');

        ShopProduct::create($validated);

        return redirect()->route('shop.products.index')
            ->with('success', 'Article créé avec succès');
    }

    public function edit(ShopProduct $product): View
    {
        $tenant = auth()->user()->tenant;

        $categories = ShopCategory::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get();

        // Format price for display (convert centimes to FCFA)
        $product->display_price = $product->price / 100;

        return view('shop.products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, ShopProduct $product)
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'shop_category_id' => 'required|exists:shop_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'required|string|unique:shop_products,sku,' . $product->id,
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
        ]);

        // Vérifier que la catégorie appartient au tenant
        ShopCategory::where('tenant_id', $tenant->id)
            ->where('id', $validated['shop_category_id'])
            ->firstOrFail();

        // Convertir le prix en centimes
        $validated['price'] = (int)($validated['price'] * 100);
        $validated['is_active'] = $request->has('is_active');

        $product->update($validated);

        return redirect()->route('shop.products.index')
            ->with('success', 'Article mis à jour avec succès');
    }

    public function destroy(ShopProduct $product)
    {
        $product->delete();

        return redirect()->route('shop.products.index')
            ->with('success', 'Article supprimé avec succès');
    }

    /**
     * Génère un SKU automatique au format ART-XXXXXX
     */
    private function generateSku(int $tenantId): string
    {
        $lastProduct = ShopProduct::where('tenant_id', $tenantId)
            ->where('sku', 'like', 'ART-%')
            ->orderByRaw("CAST(SUBSTRING(sku FROM 5) AS INTEGER) DESC")
            ->first();

        if ($lastProduct && preg_match('/^ART-(\d+)$/', $lastProduct->sku, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        } else {
            $nextNumber = 1;
        }

        return 'ART-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
