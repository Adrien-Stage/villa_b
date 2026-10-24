<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le nombre de couverts d'une commande en salle : sans lui, le revenu par
 * couvert, premier indicateur de la restauration, ne se calcule pas. Facultatif
 * — une commande au bar ou à emporter n'a pas de couverts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('covers')->nullable()->after('table_number');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropColumn('covers');
        });
    }
};
