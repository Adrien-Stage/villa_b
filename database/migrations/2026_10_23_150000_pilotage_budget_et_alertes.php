<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le pilotage de gestion : le budget de l'année et les alertes de dérive.
 *
 * Le budget se tient par centre (hébergement, restauration, boutique,
 * économat, structure) et par nature (produits, charges), mois par mois :
 * c'est la maille où la comptabilité analytique livre le réalisé, donc celle
 * où l'écart se lit sans conversion.
 *
 * Une alerte de gestion naît une fois par règle, centre et mois : le même
 * dépassement constaté chaque matin ne réveille pas la direction chaque matin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('center', 20);      // hebergement | restaurant | boutique | economat | structure
            $table->string('nature', 10);      // revenue | cost
            // Montants du mois, en centimes FCFA.
            foreach (range(1, 12) as $mois) {
                $table->bigInteger("m{$mois}")->default(0);
            }
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'center', 'nature']);
        });

        Schema::create('management_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('rule', 40);
            $table->string('center', 20)->nullable();
            $table->string('period', 7);       // AAAA-MM
            $table->bigInteger('value')->nullable();
            $table->bigInteger('reference')->nullable();
            $table->string('message', 500);
            $table->timestamps();

            $table->unique(['rule', 'center', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_alerts');
        Schema::dropIfExists('budget_lines');
    }
};
