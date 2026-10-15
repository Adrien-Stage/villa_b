<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facture complémentaire : ce que le client a consommé au mini-bar après la
 * clôture de sa note (parti sans attendre le relevé) lui est facturé à part, et
 * s'encaisse plus tard contre cette facture.
 *
 * La facture du séjour reste la seule « facture de séjour » d'une réservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // sejour | complementaire
            $table->string('kind', 20)->default('sejour');
        });

        Schema::table('minibar_checks', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
        });

        // Un encaissement peut solder une facture précise.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::table('minibar_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
