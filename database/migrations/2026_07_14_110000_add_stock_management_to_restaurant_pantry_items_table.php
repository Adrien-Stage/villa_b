<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_pantry_items', function (Blueprint $table) {
            // Article fabriqué en cuisine (préparation de base : sauce, fond, marinade).
            // Il a sa propre fiche technique et se consomme comme n'importe quel ingrédient.
            $table->boolean('is_prepared')->default(false)->after('unit');

            // On achète en sacs de 50 kg mais on cuisine en grammes : l'unité d'achat
            // et son facteur de conversion vers l'unité de stock évitent l'erreur
            // classique de saisie.
            $table->string('purchase_unit', 40)->nullable()->after('is_prepared');
            $table->decimal('purchase_conversion', 12, 3)->default(1)->after('purchase_unit');
            $table->unsignedBigInteger('purchase_price')->nullable()->after('purchase_conversion');

            // Coût moyen pondéré, en centimes FCFA par unité de stock. Recalculé à
            // chaque entrée en stock : c'est lui qui valorise les recettes, les pertes
            // et les écarts d'inventaire.
            $table->decimal('average_cost', 14, 4)->default(0)->after('cost_price');
        });

        // Amorce le coût moyen avec le prix d'achat déjà saisi, s'il existe.
        DB::statement('UPDATE restaurant_pantry_items SET average_cost = cost_price WHERE cost_price IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('restaurant_pantry_items', function (Blueprint $table) {
            $table->dropColumn([
                'is_prepared',
                'purchase_unit',
                'purchase_conversion',
                'purchase_price',
                'average_cost',
            ]);
        });
    }
};
