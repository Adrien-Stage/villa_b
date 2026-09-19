<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache chaque pièce de recette à son point de vente.
 *
 * Nullable partout : les lignes déjà enregistrées n'en portent pas, et rien
 * ne change tant que le rattachement n'est pas posé. Le back-fill qui suit
 * renseigne l'existant depuis le silo dont il provient.
 */
return new class extends Migration
{
    private const TABLES = [
        'folio_items',
        'reception_sales',
        'restaurant_customer_orders',
        'shop_orders',
        'payments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'point_of_sale_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('point_of_sale_id')->nullable()->constrained('points_of_sale')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'point_of_sale_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('point_of_sale_id');
            });
        }
    }
};
