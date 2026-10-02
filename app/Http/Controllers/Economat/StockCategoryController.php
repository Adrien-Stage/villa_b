<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Services\PermissionResolver;
use App\Services\StockAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Catégories d'articles de l'économat, et le compte de stock qui les valorise
 * au grand livre.
 */
class StockCategoryController extends Controller
{
    public function __construct(private StockAccountService $stockAccounts)
    {
    }

    public function index(): View
    {
        $categories = StockCategory::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Valeur du stock par catégorie : ce qu'un changement de compte
        // déplacerait au grand livre.
        $valeurs = StockItem::query()
            ->whereNotNull('stock_category_id')
            ->get(['stock_category_id', 'current_stock', 'average_cost'])
            ->groupBy('stock_category_id')
            ->map(fn ($items) => $items->sum(fn (StockItem $item) => $item->stockValue()));

        $maxOrder = $categories->max('sort_order');
        $nextSortOrder = $maxOrder !== null ? ((int) $maxOrder + 1) : 0;

        return view('economat.categories.index', [
            'categories'    => $categories,
            'valeurs'       => $valeurs,
            'comptes'       => StockCategory::STOCK_ACCOUNTS,
            'canManage'     => app(PermissionResolver::class)->allows(auth()->user(), 'economat.categories.modifier'),
            'nextSortOrder' => $nextSortOrder,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        StockCategory::create([
            'name'          => trim($validated['name']),
            'stock_account' => $validated['stock_account'] ?? null,
            'sort_order'    => $validated['sort_order'] ?? 0,
            'tenant_id'     => auth()->user()->tenant_id ?? \App\Models\Tenant::current()?->id,
        ]);

        return back()->with('success', 'Catégorie créée.');
    }

    public function update(Request $request, StockCategory $category): RedirectResponse
    {
        $validated = $this->validated($request, $category);

        try {
            $reclassement = DB::transaction(function () use ($category, $validated) {
                $category->update([
                    'name'       => trim($validated['name']),
                    'sort_order' => $validated['sort_order'] ?? 0,
                ]);

                return $this->stockAccounts->changeCategoryAccount($category, $validated['stock_account'] ?? null);
            });
        } catch (RuntimeException $e) {
            // Période verrouillée, journée clôturée : rien n'est modifié.
            return back()->with('error', 'Catégorie inchangée : ' . $e->getMessage());
        }

        $message = 'Catégorie mise à jour.';
        if ($reclassement) {
            $message .= ' Le stock existant a été reclassé au grand livre ('
                . number_format($reclassement->lines->sum('debit') / 100, 0, ',', ' ') . ' FCFA).';
        }

        return back()->with('success', $message);
    }

    public function destroy(StockCategory $category): RedirectResponse
    {
        if ($category->items()->exists()) {
            return back()->with('error', "La catégorie « {$category->name} » contient des articles : déplacez-les d'abord.");
        }

        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    private function validated(Request $request, ?StockCategory $category = null): array
    {
        // Si sort_order n'est pas fourni (ex: appel programmatique ou test), attribuer le prochain ordre libre
        if (!$request->has('sort_order') || $request->input('sort_order') === null || $request->input('sort_order') === '') {
            $maxOrder = StockCategory::max('sort_order');
            $request->merge(['sort_order' => $maxOrder !== null ? ((int) $maxOrder + 1) : 0]);
        }

        $targetOrder = (int) $request->input('sort_order');
        $conflict = StockCategory::where('sort_order', $targetOrder)
            ->when($category, fn ($q) => $q->where('id', '!=', $category->id))
            ->first();

        $conflictMessage = $conflict
            ? "L'ordre d'affichage {$targetOrder} est déjà attribué à la catégorie « {$conflict->name} ». Veuillez en choisir un autre."
            : "Cet ordre d'affichage est déjà attribué à une autre catégorie.";

        return $request->validate([
            'name'          => ['required', 'string', 'max:120', Rule::unique('stock_categories', 'name')->ignore($category?->id)],
            'stock_account' => ['nullable', Rule::in(array_keys(StockCategory::STOCK_ACCOUNTS))],
            'sort_order'    => [
                'required',
                'integer',
                'min:0',
                'max:9999',
                Rule::unique('stock_categories', 'sort_order')->ignore($category?->id),
            ],
        ], [
            'name.required'       => 'Le nom de la catégorie est obligatoire.',
            'name.unique'         => 'Une catégorie portant ce nom existe déjà.',
            'sort_order.required' => 'L\'ordre d\'affichage est obligatoire.',
            'sort_order.integer'  => 'L\'ordre d\'affichage doit être un nombre entier.',
            'sort_order.min'      => 'L\'ordre d\'affichage doit être supérieur ou égal à 0.',
            'sort_order.unique'   => $conflictMessage,
        ]);
    }
}
