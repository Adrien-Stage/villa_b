<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gratuités accordées dans la journée, figées au Night Audit.
 *
 * Le constat de clôture porte le chiffre d'affaires par département et les
 * écarts de caisse, mais rien sur les recettes abandonnées : une chambre
 * offerte y pesait exactement autant qu'une chambre restée vide. Ces deux
 * colonnes rendent le manque à gagner visible au même endroit et à la même
 * date que le reste, sans avoir à interroger les séjours a posteriori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('night_audits', function (Blueprint $table) {
            $table->unsignedInteger('complimentary_count')->default(0)->after('revenue_total');
            $table->unsignedBigInteger('complimentary_value')->default(0)->after('complimentary_count');
        });
    }

    public function down(): void
    {
        Schema::table('night_audits', function (Blueprint $table) {
            $table->dropColumn(['complimentary_count', 'complimentary_value']);
        });
    }
};
