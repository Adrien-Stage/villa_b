<?php

use App\Models\PointOfSale;
use App\Support\PointOfSaleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache l'existant à son point de vente.
 *
 * Les recettes déjà enregistrées proviennent des trois silos écrits en dur.
 * Elles sont rattachées à l'équivalent, de sorte qu'un journal des
 * encaissements construit sur les points de vente couvre aussi le passé.
 * Sans ce back-fill, tout l'historique tomberait dans « non rattaché ».
 */
return new class extends Migration
{
    public function up(): void
    {
        PointOfSaleCatalog::sync();

        $silos = [
            'reception_sales'            => 'hotel',
            'restaurant_customer_orders' => 'restaurant',
            'shop_orders'                => 'boutique',
            'folio_items'                => 'hotel',
            'payments'                   => 'hotel',
        ];

        foreach ($silos as $table => $slug) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'point_of_sale_id')) {
                continue;
            }

            $id = PointOfSale::where('slug', $slug)->value('id');

            if ($id !== null) {
                DB::table($table)->whereNull('point_of_sale_id')->update(['point_of_sale_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['reception_sales', 'restaurant_customer_orders', 'shop_orders', 'folio_items', 'payments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'point_of_sale_id')) {
                DB::table($table)->update(['point_of_sale_id' => null]);
            }
        }
    }
};
