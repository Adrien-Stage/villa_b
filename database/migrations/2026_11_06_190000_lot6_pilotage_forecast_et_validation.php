<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6 : Pilotage RAF & Contrôle de gestion.
 *
 * 1. Distinction stricte entre Budget et Prévision (Forecast) :
 *    - La table `budget_lines` reçoit un statut de validation et son signataire RAF.
 *    - La table `forecast_lines` porte les prévisions d'atterrissage annuelles révisables.
 *
 * 2. Traçabilité et Ségrégation SoD :
 *    - Seul le contrôleur de gestion prépare/saisit (draft).
 *    - Seul le RAF valide et scelle (validated).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Évolution de la table budget_lines
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('status', 20)->default('validated')->after('nature'); // 'draft', 'validated'
            $table->foreignId('validated_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
        });

        // 2. Création de la table forecast_lines
        Schema::create('forecast_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('center', 20); // hebergement | restaurant | boutique | economat | structure
            $table->string('nature', 10); // revenue | cost
            $table->string('status', 20)->default('draft'); // draft | validated

            // Montants prévisionnels mensuels en centimes FCFA
            foreach (range(1, 12) as $mois) {
                $table->bigInteger("m{$mois}")->default(0);
            }

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'center', 'nature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_lines');

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['status', 'validated_by', 'validated_at']);
        });
    }
};
