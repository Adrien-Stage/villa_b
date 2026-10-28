<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bons de réception en attente.
 *
 * Un bon de commande transmis au fournisseur ouvre un bon de réception « en
 * attente » : il n'a pas encore de date de réception. L'économat le remplit à
 * la livraison et dit si elle est partielle (le reste reste attendu) ou
 * complète (le bon de commande est soldé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->change();
            $table->string('delivery_type', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
