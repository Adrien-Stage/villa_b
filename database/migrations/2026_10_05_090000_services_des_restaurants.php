<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services d'un restaurant : salle, cuisine, bar, stock.
 *
 * Un restaurant n'a pas forcément les quatre : un bar de piscine sert sans
 * cuisine, une cuisine de production prépare sans salle. Chaque restaurant
 * dit lesquels il exploite, et l'application n'ouvre que ceux-là.
 *
 * Les restaurants existants gardent les quatre : rien ne change pour eux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_of_sale', function (Blueprint $table) {
            $table->json('services')->nullable()->after('service_modes');
        });

        DB::table('points_of_sale')
            ->where('kind', 'restauration')
            ->update(['services' => json_encode(['salle', 'cuisine', 'bar', 'stock'])]);
    }

    public function down(): void
    {
        Schema::table('points_of_sale', function (Blueprint $table) {
            $table->dropColumn('services');
        });
    }
};
