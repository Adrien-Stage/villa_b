<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Tarifs fournisseurs : le prix d'un article chez un fournisseur.
 *
 * Un article a plusieurs fournisseurs, un fournisseur livre plusieurs
 * articles : le prix n'appartient ni à l'un ni à l'autre, mais au couple. La
 * table stock_item_supplier en est la seule source, et tout ce qui l'écrit
 * passe par ici — le catalogue du fournisseur, la fiche de l'article, la
 * réception.
 *
 * Deux règles :
 *  - un tarif s'exprime dans l'unité de stock de l'article ;
 *  - le fournisseur habituel de l'article (stock_items.supplier_id) a
 *    toujours sa ligne de tarif.
 */
class SupplierTariffService
{
    /**
     * Fixe le prix de l'article chez ce fournisseur, et sa date.
     *
     * @param  int  $prix  en centimes, dans l'unité de stock de l'article
     */
    public function fixer(StockItem $item, Supplier|int $supplier, int $prix, ?CarbonInterface $le = null): void
    {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : $supplier;
        $existant = DB::table('stock_item_supplier')
            ->where('stock_item_id', $item->id)->where('supplier_id', $supplierId)->first();

        $valeurs = ['purchase_price' => max(0, $prix), 'unit' => $item->unit, 'updated_at' => now()];

        // La date ne bouge qu'avec le prix : enregistrer le même prix ne le rajeunit pas.
        if ($existant === null || (int) $existant->purchase_price !== $valeurs['purchase_price'] || $existant->priced_at === null) {
            $valeurs['priced_at'] = $valeurs['purchase_price'] > 0 ? ($le ?? now()) : null;
        }

        if ($existant === null) {
            DB::table('stock_item_supplier')->insert($valeurs + [
                'stock_item_id' => $item->id,
                'supplier_id'   => $supplierId,
                'created_at'    => now(),
            ]);
        } else {
            DB::table('stock_item_supplier')->where('id', $existant->id)->update($valeurs);
        }

        // Le premier fournisseur d'un article en devient le fournisseur habituel.
        if (empty($item->supplier_id)) {
            $item->forceFill(['supplier_id' => $supplierId])->save();
        }
    }

    /**
     * Retire le tarif. Si c'était le fournisseur habituel, le moins cher des
     * autres le remplace — ou personne.
     */
    public function retirer(StockItem $item, Supplier|int $supplier): void
    {
        $supplierId = $supplier instanceof Supplier ? $supplier->id : $supplier;

        DB::table('stock_item_supplier')
            ->where('stock_item_id', $item->id)->where('supplier_id', $supplierId)->delete();

        if ((int) $item->supplier_id === (int) $supplierId) {
            $item->forceFill(['supplier_id' => $this->moinsCher($item)])->save();
        }
    }

    /**
     * Le catalogue complet d'un fournisseur, tel que son formulaire le
     * présente : chaque ligne absente du formulaire est un tarif retiré.
     *
     * @param  array<int, int>  $prixParArticle  stock_item_id => prix en centimes
     * @return array{ajoutes: int, modifies: int, retires: int}
     */
    public function catalogue(Supplier $supplier, array $prixParArticle): array
    {
        $avant = DB::table('stock_item_supplier')->where('supplier_id', $supplier->id)
            ->pluck('purchase_price', 'stock_item_id')->map(fn ($p) => (int) $p)->all();
        $bilan = ['ajoutes' => 0, 'modifies' => 0, 'retires' => 0];

        foreach ($prixParArticle as $itemId => $prix) {
            $item = StockItem::find($itemId);

            if ($item === null) {
                continue;
            }

            if (! array_key_exists($itemId, $avant)) {
                $bilan['ajoutes']++;
            } elseif ($avant[$itemId] !== $prix) {
                $bilan['modifies']++;
            }

            $this->fixer($item, $supplier, $prix);
        }

        foreach (array_diff_key($avant, $prixParArticle) as $itemId => $_) {
            if ($item = StockItem::find($itemId)) {
                $this->retirer($item, $supplier);
                $bilan['retires']++;
            }
        }

        return $bilan;
    }

    /** Le fournisseur le moins cher de l'article, parmi ceux qui ont un prix. */
    private function moinsCher(StockItem $item): ?int
    {
        $tarifs = DB::table('stock_item_supplier')->where('stock_item_id', $item->id);

        return (clone $tarifs)->where('purchase_price', '>', 0)->orderBy('purchase_price')->value('supplier_id')
            ?? $tarifs->value('supplier_id');
    }
}
