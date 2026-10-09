<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le visa du chef de service sur une demande interne.
 *
 * Une demande faite par un membre d'un service (réceptionniste, valet,
 * cuisinier, vendeur, comptable) passe d'abord par son chef, qui la vise ou
 * la refuse ; elle n'arrive chez l'économe qu'une fois visée. La demande
 * faite par le chef lui-même porte déjà son visa.
 *
 * Les demandes existantes ont suivi l'ancien circuit : elles restent telles
 * quelles, sans visa enregistré.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_requisitions', function (Blueprint $table) {
            $table->foreignId('endorsed_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable()->after('endorsed_by');
            $table->text('endorsement_notes')->nullable()->after('endorsed_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('endorsed_by');
            $table->dropColumn(['endorsed_at', 'endorsement_notes']);
        });
    }
};
