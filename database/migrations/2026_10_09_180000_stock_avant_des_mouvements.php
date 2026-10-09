<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le stock avant chaque mouvement de l'économat.
 *
 * Un mouvement gardait la quantité et le stock après. Pour un ajustement
 * d'inventaire, on lisait donc le stock compté, mais pas d'un coup d'œil le
 * stock initial dont il partait. Le stock avant est désormais écrit avec le
 * mouvement : un ajustement porte le stock initial de l'article et le stock
 * après comptage, et leur valeur se déduit du coût du mouvement.
 *
 * Les mouvements existants le retrouvent exactement : la quantité est signée,
 * le stock avant vaut le stock après moins la quantité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('stock_before', 12, 3)->nullable()->after('quantity');
        });

        DB::table('stock_movements')->update(['stock_before' => DB::raw('stock_after - quantity')]);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('stock_before');
        });
    }
};
