<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réceptions et sorties par conditionnement : on reçoit 10 cartons, on sort
 * 3 paquets. Les quantités des lignes restent dans l'unité de l'article ; la
 * ligne garde le conditionnement dans lequel elles ont été saisies, pour que
 * le bon les relise ainsi (« 10 cartons (2 000 pièces) »). Des cartons reçus
 * entiers entrent en stock comme cartons fermés.
 *
 * À l'inventaire, un article conditionné se compte par niveau : cartons
 * fermés, paquets fermés, vrac. La ligne garde ce détail ; la clôture cale
 * les unités fermées de l'article sur ce qui a été compté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->string('packaging_name', 40)->nullable()->after('quantity_rejected');
        });

        Schema::table('external_issue_lines', function (Blueprint $table) {
            $table->string('packaging_name', 40)->nullable()->after('quantity');
        });

        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->json('packaging_counts')->nullable()->after('counted_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_lines', function (Blueprint $table) {
            $table->dropColumn('packaging_counts');
        });

        Schema::table('external_issue_lines', function (Blueprint $table) {
            $table->dropColumn('packaging_name');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('packaging_name');
        });
    }
};
