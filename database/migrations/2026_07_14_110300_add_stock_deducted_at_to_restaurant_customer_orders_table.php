<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            // Horodatage de la sortie de stock des ingrédients. Sert de garde-fou :
            // une commande n'est jamais déduite deux fois, et seule une commande
            // déjà déduite peut être restituée à l'annulation.
            $table->timestamp('stock_deducted_at')->nullable()->after('paid_by');

            // Coût matière de la commande (centimes FCFA), figé au moment de la
            // déduction : c'est la marge réelle de la vente.
            $table->bigInteger('food_cost')->nullable()->after('stock_deducted_at');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropColumn(['stock_deducted_at', 'food_cost']);
        });
    }
};
