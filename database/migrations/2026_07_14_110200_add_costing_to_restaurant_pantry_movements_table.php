<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
            // Valorisation du mouvement : coût unitaire appliqué (centimes FCFA par
            // unité de stock) et valeur totale. Permet de chiffrer les pertes,
            // la consommation et les écarts d'inventaire.
            $table->decimal('unit_cost', 14, 4)->nullable()->after('quantity');
            $table->bigInteger('total_cost')->nullable()->after('unit_cost');

            // Stock résultant après le mouvement — piste d'audit.
            $table->decimal('stock_after', 12, 3)->nullable()->after('total_cost');

            // Traçabilité : quelle vente a consommé cet ingrédient, quelle fiche
            // technique a produit ce batch, quel inventaire a généré cet ajustement.
            $table->foreignId('restaurant_customer_order_id')
                ->nullable()
                ->after('stock_after')
                ->constrained('restaurant_customer_orders')
                ->nullOnDelete();

            $table->foreignId('restaurant_recipe_id')
                ->nullable()
                ->after('restaurant_customer_order_id')
                ->constrained('restaurant_recipes')
                ->nullOnDelete();

            $table->index(['reason', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
            $table->dropForeign(['restaurant_customer_order_id']);
            $table->dropForeign(['restaurant_recipe_id']);
            $table->dropIndex(['reason', 'occurred_at']);
            $table->dropColumn([
                'unit_cost',
                'total_cost',
                'stock_after',
                'restaurant_customer_order_id',
                'restaurant_recipe_id',
            ]);
        });
    }
};
