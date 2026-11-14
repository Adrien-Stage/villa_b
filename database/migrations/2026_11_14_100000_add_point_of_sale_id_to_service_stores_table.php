<?php

use App\Models\PointOfSale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattachement des dépôts de service (Cuisine, Bar) aux restaurants (points de vente).
 *
 * Lors de la création d'un restaurant, ses lieux de stockage (Cuisine, Bar)
 * sont créés comme dépôts de service visibles à l'économat pour approvisionnement
 * via bons de réquisitions et comptage lors des inventaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_stores', function (Blueprint $table) {
            $table->foreignId('point_of_sale_id')->nullable()->after('tenant_id')->constrained('points_of_sale')->nullOnDelete();
            $table->string('restaurant_service', 20)->nullable()->after('point_of_sale_id');
            $table->index(['point_of_sale_id', 'restaurant_service']);
        });

        // Synchroniser rétroactivement les restaurants existants
        foreach (PointOfSale::query()->restaurants()->get() as $restaurant) {
            $restaurant->synchroniserDepotsService();
        }
    }

    public function down(): void
    {
        Schema::table('service_stores', function (Blueprint $table) {
            $table->dropForeign(['point_of_sale_id']);
            $table->dropIndex(['point_of_sale_id', 'restaurant_service']);
            $table->dropColumn(['point_of_sale_id', 'restaurant_service']);
        });
    }
};
