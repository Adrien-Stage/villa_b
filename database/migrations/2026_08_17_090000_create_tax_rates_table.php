<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taux de taxe applicables aux ventes et aux achats.
 *
 * Un taux porte ses comptes d'imputation : c'est ce qui permettra à la
 * comptabilité générale (phase 1) de savoir où créditer la TVA collectée et
 * où débiter la TVA déductible sans règle codée en dur.
 *
 * Le taux est stocké en points de base — 1 pdb = 0,01 %, donc 19,25 % => 1925
 * — pour la même raison que les montants sont en centimes : aucun flottant ne
 * doit intervenir dans une chaîne de calcul comptable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();

            // Code stable, utilisé par le code applicatif (STANDARD, EXONERE…).
            $table->string('code', 30)->unique();
            $table->string('label', 120);

            // Taux en points de base : 19,25 % = 1925. Granularité au
            // centième de point, sans jamais manipuler de float.
            $table->unsignedInteger('rate_basis_points')->default(0);

            // Comptes SYSCOHADA. Renseignés en clair plutôt qu'en clé
            // étrangère : le plan de comptes n'existe qu'en phase 1, et un
            // taux doit rester utilisable avant sa mise en place.
            $table->string('collected_account', 20)->nullable();   // 4431 — TVA facturée
            $table->string('deductible_account', 20)->nullable();  // 4451 — TVA déductible

            // Ventilation TVA / centimes additionnels communaux. Au Cameroun,
            // 19,25 % = 17,5 % de TVA majorés de 10 % de CAC. Tant que
            // l'expert-comptable n'a pas tranché entre compte unique et
            // ventilation, on stocke la décomposition sans l'imposer.
            $table->unsignedInteger('surtax_basis_points')->default(0); // part CAC
            $table->string('surtax_account', 20)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
