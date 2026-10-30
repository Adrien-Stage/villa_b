<?php

namespace App\Support;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Support\Facades\DB;

/**
 * La fiche d'un fournisseur : qui il est, ce qu'il nous livre et à quel prix,
 * ce qu'on lui a commandé et ce qu'il a livré.
 *
 * Seul l'historique des livraisons s'imprime et s'exporte, par l'édition
 * « Historique des livraisons » (App\Editions\Documents\HistoriqueLivraisons).
 */
class FicheFournisseur
{
    /** Livraisons montrées par l'écran ; l'édition donne tout l'historique. */
    public const LIVRAISONS_A_L_ECRAN = 30;

    /**
     * @return array<string, mixed>
     */
    public static function pour(Supplier $supplier, User $user): array
    {
        $droits = app(PermissionResolver::class);

        return [
            'supplier' => $supplier,
            // Les coordonnées bancaires servent à payer : à qui tient le
            // fournisseur ou les comptes, pas à tout lecteur de la fiche.
            'voitBanque' => $droits->allows($user, 'economat.suppliers.modifier') || $droits->allows($user, 'accounting.voir'),
            'voitBons' => $droits->allows($user, 'economat.orders.voir'),
            'voitReceptions' => $droits->allows($user, 'economat.receipts.voir'),
            'catalogue' => self::catalogue($supplier),
            'livraisons' => self::livraisons($supplier),
            'attendues' => $supplier->goodsReceipts()->where('status', GoodsReceipt::STATUS_PENDING)
                ->with('purchaseOrder:id,number,sent_at,expected_at')->latest('id')->get(),
            'enCours' => $supplier->purchaseOrders()
                ->whereIn('status', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->latest('sent_at')->get(['id', 'number', 'status', 'sent_at', 'expected_at', 'total_amount']),
            'chiffres' => self::chiffres($supplier),
        ];
    }

    /**
     * Ses articles et leur prix chez lui, avec ce qui les situe face aux
     * autres fournisseurs : habituel, moins cher, nombre de concurrents.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private static function catalogue(Supplier $supplier)
    {
        $articles = $supplier->stockItems()->with('category:id,name')->orderBy('stock_items.name')->get();

        $marche = DB::table('stock_item_supplier')
            ->whereIn('stock_item_id', $articles->pluck('id'))
            ->where('purchase_price', '>', 0)
            ->groupBy('stock_item_id')
            ->selectRaw('stock_item_id, MIN(purchase_price) AS meilleur, COUNT(*) AS offres')
            ->get()->keyBy('stock_item_id');

        return $articles->map(function ($item) use ($supplier, $marche) {
            $prix = (int) $item->pivot->purchase_price;
            $offre = $marche[$item->id] ?? null;

            return [
                'item' => $item,
                'prix' => $prix,
                'date' => $item->pivot->priced_at ? \Illuminate\Support\Carbon::parse($item->pivot->priced_at) : null,
                'habituel' => (int) $item->supplier_id === (int) $supplier->id,
                'moinsCher' => $prix > 0 && $offre !== null && (int) $offre->offres > 1 && $prix <= (int) $offre->meilleur,
                'autres' => max(0, (int) ($offre->offres ?? 0) - ($prix > 0 ? 1 : 0)),
            ];
        });
    }

    /** Ses livraisons réceptionnées ou annulées, les plus récentes d'abord. */
    private static function livraisons(Supplier $supplier)
    {
        return $supplier->goodsReceipts()
            ->where('status', '!=', GoodsReceipt::STATUS_PENDING)
            ->with(['purchaseOrder:id,number', 'receivedBy:id,name'])
            ->withCount('attachments')
            ->latest('received_at')->latest('id')
            ->limit(self::LIVRAISONS_A_L_ECRAN)
            ->get();
    }

    /** @return array<string, mixed> */
    private static function chiffres(Supplier $supplier): array
    {
        $recues = $supplier->goodsReceipts()->where('status', GoodsReceipt::STATUS_RECEIVED);

        return [
            'livraisons' => (clone $recues)->count(),
            'montant_12_mois' => (int) (clone $recues)->where('received_at', '>=', now()->subYear()->startOfDay())->sum('total_amount'),
            'montant_total' => (int) (clone $recues)->sum('total_amount'),
            'derniere' => (clone $recues)->max('received_at'),
            'articles' => DB::table('stock_item_supplier')->where('supplier_id', $supplier->id)->count(),
            'bons_en_cours' => $supplier->purchaseOrders()
                ->whereIn('status', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])->count(),
        ];
    }
}
