<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rapprochement facture / bon de commande / réception.
 *
 * Une facture rattachée à un bon ne devrait porter que ce qui a été reçu et
 * pas encore facturé. Ce qui dépasse — transport, hausse de prix, livraison
 * non pointée — est accepté avec un motif : l'écart et sa justification sont
 * figés sur la facture, tels qu'ils étaient le jour de la saisie.
 *
 * Montants en centimes FCFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('reception_variance')->default(0)->after('net_payable');
            $table->string('variance_reason', 255)->nullable()->after('reception_variance');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn(['reception_variance', 'variance_reason']);
        });
    }
};
