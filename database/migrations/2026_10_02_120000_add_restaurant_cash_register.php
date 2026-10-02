<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une caisse par restaurant.
 *
 * Le restaurant encaissait sans caisse : aucun fond, aucun comptage, aucun
 * contrôle. Chaque session de caisse désigne désormais son point de vente —
 * pour un restaurant, sa caisse —, et une note encaissée garde la session qui
 * l'a encaissée, comme un paiement de la réception ou une vente de la
 * boutique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->foreignId('point_of_sale_id')->nullable()->after('module')
                ->constrained('points_of_sale')->nullOnDelete();
        });

        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()
                ->constrained('cash_register_sessions')->nullOnDelete();
        });

        // Les sessions existantes rejoignent le point de vente de leur caisse.
        foreach (['reception' => 'hotel', 'shop' => 'boutique'] as $module => $slug) {
            $pointDeVente = DB::table('points_of_sale')->where('slug', $slug)->value('id');

            if ($pointDeVente) {
                DB::table('cash_register_sessions')->where('module', $module)
                    ->whereNull('point_of_sale_id')->update(['point_of_sale_id' => $pointDeVente]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('point_of_sale_id');
        });
    }
};
