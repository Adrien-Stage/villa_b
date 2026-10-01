<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaire permanent : chaque mouvement de l'économat se reflète en classe 3.
 *
 * La catégorie d'article porte le compte de stock qui la valorise (boissons en
 * 311000, denrées en 321000, produits d'entretien en 331000…). Sans compte
 * renseigné, l'article tombe en 332000 « Fournitures d'économat ».
 *
 * Le plan ne connaissait pas la variation des autres approvisionnements
 * (603300), contrepartie des comptes 33x : on l'ajoute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_categories', function (Blueprint $table) {
            $table->string('stock_account', 10)->nullable()->after('icon');
        });

        $now = now();

        DB::table('accounts')->updateOrInsert(
            ['code' => '603300'],
            [
                'label'         => 'Variation des stocks d’autres approvisionnements',
                'account_class' => 6,
                'is_collective' => false,
                'is_postable'   => true,
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('stock_categories', function (Blueprint $table) {
            $table->dropColumn('stock_account');
        });

        DB::table('accounts')->where('code', '603300')->delete();
    }
};
