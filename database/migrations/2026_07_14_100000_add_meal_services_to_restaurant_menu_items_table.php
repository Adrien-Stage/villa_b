<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            // Services de repas ou l'article est proposé : breakfast, lunch, dinner
            $table->json('meal_services')->nullable()->after('type');
        });

        // Les articles existants restent disponibles sur tous les services
        DB::table('restaurant_menu_items')->update([
            'meal_services' => json_encode(['breakfast', 'lunch', 'dinner']),
        ]);
    }

    public function down(): void
    {
        Schema::table('restaurant_menu_items', function (Blueprint $table) {
            $table->dropColumn('meal_services');
        });
    }
};
