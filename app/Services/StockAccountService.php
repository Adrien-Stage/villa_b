<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\StockCategory;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

/**
 * Le compte de stock d'un article : celui de sa catégorie.
 *
 * Changer ce compte — en modifiant la catégorie, ou en déplaçant un article
 * vers une autre catégorie — ne déplace pas la marchandise, mais déplace sa
 * valeur au grand livre. Le stock déjà présent est donc reclassé de l'ancien
 * compte vers le nouveau dans la même transaction ; sans cela, l'ancien
 * compte garderait une valeur que plus aucun mouvement ne viendrait solder.
 */
class StockAccountService
{
    public function __construct(private LedgerPostingService $posting)
    {
    }

    /**
     * @param  string|null  $account  Nul pour revenir au compte par défaut.
     */
    public function changeCategoryAccount(StockCategory $category, ?string $account): ?JournalEntry
    {
        return DB::transaction(function () use ($category, $account) {
            $category = StockCategory::query()->lockForUpdate()->findOrFail($category->id);
            $from = $category->effectiveStockAccount();

            // Verrou des articles : un mouvement en cours finit avant la
            // valorisation, et le suivant lira déjà le nouveau compte.
            $items = StockItem::query()
                ->where('stock_category_id', $category->id)
                ->lockForUpdate()
                ->get();

            $category->update(['stock_account' => $account ?: null]);

            return $this->posting->postStockReclassification(
                from: $from,
                to: $category->effectiveStockAccount(),
                amount: (int) $items->sum(fn (StockItem $item) => $item->stockValue()),
                label: "Reclassement du stock « {$category->name} »",
                date: now(),
            );
        });
    }

    public function moveItem(StockItem $item, ?int $categoryId): ?JournalEntry
    {
        return DB::transaction(function () use ($item, $categoryId) {
            $item = StockItem::query()->lockForUpdate()->findOrFail($item->id);
            $from = $item->stockAccount();

            $item->update(['stock_category_id' => $categoryId]);
            $item->load('category');

            return $this->posting->postStockReclassification(
                from: $from,
                to: $item->stockAccount(),
                amount: $item->stockValue(),
                label: "Reclassement de « {$item->name} »",
                date: now(),
            );
        });
    }
}
