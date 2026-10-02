<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interventions de l'administrateur et sessions du support.
 *
 * L'administrateur — le service informatique — n'écrit dans l'exploitation
 * que pendant une intervention déclarée : motif, durée, périmètre. Le support
 * de l'éditeur entre par un compte technique dont les sessions restent
 * visibles par l'hôtel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('motif', 500);
            // Périmètres couverts (clés d'Intervention::PERIMETRES).
            $table->json('perimetres');
            $table->timestamp('debut');
            $table->timestamp('fin_prevue');
            $table->timestamp('fin_reelle')->nullable();
            // terminee (par l'administrateur) ou expiree (durée écoulée).
            $table->string('cloture', 20)->nullable();
            // Trace auprès de la console : à transmettre à chaque changement
            // d'état ; un échec la rend « tardive » pour de bon.
            $table->boolean('erp_a_transmettre')->default(true);
            $table->timestamp('erp_transmis_at')->nullable();
            $table->unsignedInteger('erp_echecs')->default(0);
            $table->boolean('erp_tardive')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'fin_reelle']);
        });

        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Le technicien de l'éditeur, tel que la console le nomme.
            $table->string('technicien');
            // Référence de la session côté console.
            $table->string('reference')->nullable();
            $table->timestamp('debut');
            $table->timestamp('fin')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_sessions');
        Schema::dropIfExists('interventions');
    }
};
