<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptage contradictoire : qui a contrôlé le comptage, et quand.
 *
 * La colonne status existait déjà sans jamais servir ; elle porte désormais
 * l'état intermédiaire « pending_review » — la caisse est comptée, elle
 * n'accepte plus d'opération, mais elle n'est pas close tant qu'un tiers
 * n'a pas contresigné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->foreignId('witness_id')->nullable()->after('closing_notes')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('witnessed_at')->nullable()->after('witness_id');
            $table->text('witness_notes')->nullable()->after('witnessed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropForeign(['witness_id']);
            $table->dropColumn(['witness_id', 'witnessed_at', 'witness_notes']);
        });
    }
};
