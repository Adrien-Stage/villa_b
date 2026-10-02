<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque mouvement de l'économat retient le compte de stock de son article
 * au moment où il a lieu.
 *
 * Le compte d'une catégorie peut changer en cours de journée, alors que la
 * comptabilisation des mouvements n'a lieu qu'au night audit. Sans cette
 * trace, les mouvements d'avant le changement seraient passés sur le nouveau
 * compte, et le reclassement du stock compterait deux fois leur valeur.
 *
 * Nul pour les mouvements antérieurs : la comptabilisation retombe alors sur
 * le compte courant de l'article, comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('stock_account', 10)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('stock_account');
        });
    }
};
