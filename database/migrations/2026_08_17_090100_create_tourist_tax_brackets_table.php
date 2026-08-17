<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barème de la taxe de séjour, par classement d'établissement.
 *
 * La taxe de séjour n'est pas un produit : elle est perçue pour le compte de
 * la fiscalité locale et constitue une dette (compte 447x), jamais un
 * chiffre d'affaires. Elle est donc tenue à part du prix de la nuitée.
 *
 * Le barème vit en base plutôt qu'en dur : il relève de la loi de finances
 * et change sans que le code ait à bouger. L'établissement déclare son
 * classement dans les paramètres, et c'est lui qui sélectionne la ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourist_tax_brackets', function (Blueprint $table) {
            $table->id();

            // Classement : 'non_classe', '1', '2', '3', '4', '5'.
            $table->string('classification', 20)->unique();
            $table->string('label', 120);

            // Montant par nuitée, en centimes FCFA.
            $table->unsignedInteger('amount_per_night')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_tax_brackets');
    }
};
