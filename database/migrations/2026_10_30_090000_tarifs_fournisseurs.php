<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifs fournisseurs : la date de chaque prix, et le retrait des tarifs
 * inventés par la première version de la migration du 29 octobre.
 *
 * Cette version ajoutait à chaque article deux ou trois fournisseurs, aux
 * prix du premier, +10 % et −5 % : de faux tarifs dans des établissements
 * réels. Elle a été corrigée, mais un établissement a pu la jouer avant. Ses
 * lignes se reconnaissent sans ambiguïté : toutes créées au même instant, le
 * premier instant de la table, jamais modifiées depuis, et pour un même
 * article deux prix dans le rapport exact de 1,10. Seuls ces articles sont
 * repris : leurs tarifs inventés partent, celui du fournisseur habituel
 * revient au dernier prix d'achat connu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_item_supplier')) {
            return;
        }

        if (! Schema::hasColumn('stock_item_supplier', 'priced_at')) {
            Schema::table('stock_item_supplier', function (Blueprint $table) {
                $table->timestamp('priced_at')->nullable()->after('purchase_price');
            });
        }

        $this->retirerLesTarifsInventes();

        // Un tarif existant date de sa dernière écriture.
        DB::table('stock_item_supplier')->whereNull('priced_at')->where('purchase_price', '>', 0)
            ->update(['priced_at' => DB::raw('updated_at')]);
    }

    private function retirerLesTarifsInventes(): void
    {
        $instant = DB::table('stock_item_supplier')->min('created_at');

        if ($instant === null) {
            return;
        }

        $lignes = DB::table('stock_item_supplier')
            ->where('created_at', $instant)
            ->whereColumn('updated_at', 'created_at')
            ->get(['id', 'stock_item_id', 'supplier_id', 'purchase_price'])
            ->groupBy('stock_item_id');

        foreach ($lignes as $itemId => $tarifs) {
            $prix = $tarifs->pluck('purchase_price')->map(fn ($p) => (int) $p);
            $empreinte = $prix->contains(fn ($p) => $p > 0 && $prix->contains((int) round($p * 1.10)) && (int) round($p * 1.10) !== $p);

            if (! $empreinte) {
                continue;
            }

            $item = DB::table('stock_items')->where('id', $itemId)->first(['supplier_id', 'last_purchase_price']);

            foreach ($tarifs as $tarif) {
                if ($item === null || (int) $tarif->supplier_id !== (int) $item->supplier_id) {
                    DB::table('stock_item_supplier')->where('id', $tarif->id)->delete();
                } else {
                    DB::table('stock_item_supplier')->where('id', $tarif->id)->update([
                        'purchase_price' => (int) $item->last_purchase_price > 0 ? (int) $item->last_purchase_price : 0,
                        'delivery_lead_time_days' => null,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_item_supplier', 'priced_at')) {
            Schema::table('stock_item_supplier', function (Blueprint $table) {
                $table->dropColumn('priced_at');
            });
        }
    }
};
