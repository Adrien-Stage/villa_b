<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passerelle entre le Magasin Central (Économat) et le Garde-Manger (Restaurant).
 *
 * Permet à un ingrédient du restaurant d'être relié à son article d'économat source,
 * et journalise la réquisition d'origine sur le mouvement d'entrée en cuisine
 * lors d'un transfert interne (TRANSFER_IN).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('restaurant_pantry_items') && !Schema::hasColumn('restaurant_pantry_items', 'stock_item_id')) {
            Schema::table('restaurant_pantry_items', function (Blueprint $table) {
                $table->foreignId('stock_item_id')
                    ->nullable()
                    ->after('restaurant_pantry_category_id')
                    ->constrained('stock_items')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('restaurant_pantry_movements') && !Schema::hasColumn('restaurant_pantry_movements', 'stock_requisition_id')) {
            Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
                $table->foreignId('stock_requisition_id')
                    ->nullable()
                    ->after('restaurant_recipe_id')
                    ->constrained('stock_requisitions')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurant_pantry_movements') && Schema::hasColumn('restaurant_pantry_movements', 'stock_requisition_id')) {
            Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('stock_requisition_id');
            });
        }

        if (Schema::hasTable('restaurant_pantry_items') && Schema::hasColumn('restaurant_pantry_items', 'stock_item_id')) {
            Schema::table('restaurant_pantry_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('stock_item_id');
            });
        }
    }
};
