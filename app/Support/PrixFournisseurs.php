<?php

namespace App\Support;

use App\Models\GoodsReceipt;
use App\Models\StockItem;
use App\Models\Supplier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Le prix d'un article chez ses fournisseurs, tel qu'ils l'ont livré.
 *
 * Une demande d'achat se chiffre au prix du fournisseur à la livraison : le
 * prix de sa dernière livraison réceptionnée, dans l'unité de stock. Pas de
 * moyenne entre fournisseurs. À défaut de livraison, son tarif au catalogue.
 *
 * Le coût moyen pondéré (CUMP) reste la valeur du stock ; il ne chiffre pas
 * les achats.
 */
class PrixFournisseurs
{
    /**
     * Les fournisseurs de l'article — ceux de son catalogue et ceux qui l'ont
     * livré — du moins cher au plus cher, avec leur dernière livraison et,
     * sur une période, leurs prix livrés.
     *
     * @return list<array<string, mixed>>
     */
    public static function comparer(StockItem $item, ?CarbonInterface $du = null, ?CarbonInterface $au = null): array
    {
        $livraisons = self::livraisons($item)->get();
        $tarifs = DB::table('stock_item_supplier')->where('stock_item_id', $item->id)
            ->pluck('purchase_price', 'supplier_id')->map(fn ($p) => (int) $p);
        $ids = $livraisons->pluck('supplier_id')->merge($tarifs->keys())->map(fn ($id) => (int) $id)->unique()->values();
        $noms = Supplier::query()->whereIn('id', $ids)->pluck('name', 'id');

        $fournisseurs = $ids->map(function (int $id) use ($livraisons, $tarifs, $noms, $item, $du, $au) {
            $siennes = $livraisons->where('supplier_id', $id)->sortByDesc('received_at')->values();
            $derniere = $siennes->first();
            $periode = $du && $au
                ? $siennes->filter(fn ($l) => Carbon::parse($l->received_at)->betweenIncluded($du->copy()->startOfDay(), $au->copy()->endOfDay()))->values()
                : collect();

            return [
                'id' => $id,
                'nom' => $noms[$id] ?? '—',
                'derniere' => $derniere ? ['prix' => (int) $derniere->unit_cost, 'date' => Carbon::parse($derniere->received_at)->format('Y-m-d')] : null,
                'tarif' => ($tarifs[$id] ?? 0) > 0 ? $tarifs[$id] : null,
                'habituel' => (int) $item->supplier_id === $id,
                'periode' => $du && $au ? [
                    'livraisons' => $periode->count(),
                    'min' => $periode->isEmpty() ? null : (int) $periode->min('unit_cost'),
                    'max' => $periode->isEmpty() ? null : (int) $periode->max('unit_cost'),
                    'dernier' => $periode->isEmpty() ? null : (int) $periode->first()->unit_cost,
                ] : null,
            ];
        });

        // Le prix qui servira : la dernière livraison, sinon le tarif.
        $prix = fn (array $f) => $f['derniere']['prix'] ?? $f['tarif'] ?? PHP_INT_MAX;
        $tries = $fournisseurs->sortBy([fn ($a, $b) => $prix($a) <=> $prix($b), fn ($a, $b) => strcmp($a['nom'], $b['nom'])])->values();
        $meilleur = $tries->first() && $prix($tries->first()) < PHP_INT_MAX ? $prix($tries->first()) : null;

        return $tries->map(fn (array $f) => $f + ['moinsCher' => $tries->count() > 1 && $meilleur !== null && $prix($f) === $meilleur])->all();
    }

    /**
     * Le prix de l'article chez ce fournisseur, en centimes : sa dernière
     * livraison, sinon son tarif ; null s'il n'en a aucun.
     */
    public static function prix(StockItem $item, int $supplierId): ?int
    {
        $derniere = self::livraisons($item)->where('r.supplier_id', $supplierId)->first();

        if ($derniere !== null && (int) $derniere->unit_cost > 0) {
            return (int) $derniere->unit_cost;
        }

        $tarif = (int) DB::table('stock_item_supplier')
            ->where('stock_item_id', $item->id)->where('supplier_id', $supplierId)->value('purchase_price');

        return $tarif > 0 ? $tarif : null;
    }

    /** Les lignes réceptionnées de l'article, les plus récentes d'abord. */
    private static function livraisons(StockItem $item)
    {
        return DB::table('goods_receipt_lines as l')
            ->join('goods_receipts as r', 'r.id', '=', 'l.goods_receipt_id')
            ->where('l.stock_item_id', $item->id)
            ->where('r.status', GoodsReceipt::STATUS_RECEIVED)
            ->where('l.quantity_accepted', '>', 0)
            ->where('l.unit_cost', '>', 0)
            ->whereNotNull('r.supplier_id')
            ->orderByDesc('r.received_at')->orderByDesc('l.id')
            ->select(['r.supplier_id', 'r.received_at', 'l.unit_cost']);
    }
}
