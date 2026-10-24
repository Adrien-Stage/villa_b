<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Circuit d'achat avec responsable des achats.
 *
 *  - La quantité approuvée de chaque ligne de demande : le contrôleur de
 *    gestion peut l'ajuster en approuvant. La quantité demandée reste, pour
 *    que l'économat voie ce qui a changé.
 *  - La date d'archivage de la demande : elle quitte les demandes en cours
 *    quand ses bons sont partis chez les fournisseurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->decimal('quantity_approved', 12, 3)->nullable()->after('quantity_requested');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('reviewed_at');
            $table->index('archived_at');
        });

        // Les demandes déjà converties dont aucun bon n'attend plus d'envoi
        // sont archivées, comme elles le seront désormais à l'envoi.
        $enAttente = DB::table('purchase_orders')
            ->whereColumn('purchase_orders.purchase_request_id', 'purchase_requests.id')
            ->where('purchase_orders.status', 'draft');

        DB::table('purchase_requests')
            ->where('status', 'converted')
            ->whereNull('archived_at')
            ->whereNotExists($enAttente)
            ->update(['archived_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropColumn('quantity_approved');
        });
    }
};
