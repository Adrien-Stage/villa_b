<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reséquence les catégories de stock existantes pour garantir l'unicité stricte
     * des ordres d'affichage (sort_order).
     */
    public function up(): void
    {
        $categories = DB::table('stock_categories')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $usedOrders = [];
        $nextOrder = 0;

        foreach ($categories as $cat) {
            $order = (int) $cat->sort_order;
            if (in_array($order, $usedOrders, true)) {
                // Trouver le premier ordre libre supérieur ou égal à $nextOrder
                while (in_array($nextOrder, $usedOrders, true)) {
                    $nextOrder++;
                }
                $order = $nextOrder;
                DB::table('stock_categories')->where('id', $cat->id)->update(['sort_order' => $order]);
            }

            $usedOrders[] = $order;
            if ($order >= $nextOrder) {
                $nextOrder = $order + 1;
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback nécessaire sur les numéros d'ordre
    }
};
