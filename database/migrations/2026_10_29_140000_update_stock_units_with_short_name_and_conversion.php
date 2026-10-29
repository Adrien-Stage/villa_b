<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Évolution des unités de mesure :
 * - Nom long (ex: Kilogramme, Gramme, Litre, Pièce)
 * - Nom court / symbole (ex: kg, g, l, pcs)
 * - Valeur / facteur et unité de référence (ex: 1 kg = 1000 g, 1 cl = 10 ml, 1 pièce = 1 pcs)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_units', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('short_name', 20)->nullable()->after('name');
            $table->decimal('factor', 14, 4)->default(1.0)->after('short_name');
            $table->foreignId('reference_unit_id')->nullable()->after('factor')->constrained('stock_units')->nullOnDelete();
        });

        // 1. Définition des unités standard avec leurs équivalences
        // On crée d'abord les unités de base (Gramme, Millilitre, Pièce)
        $now = now();

        $unitesStandards = [
            // [nom_actuel_ou_cle, nom_long, nom_court, unite_ref_cle, facteur]
            ['g',         'Gramme',      'g',       null, 1.0],
            ['kg',        'Kilogramme',  'kg',      'g',  1000.0],
            ['ml',        'Millilitre',  'ml',      null, 1.0],
            ['cl',        'Centilitre',  'cl',      'ml', 10.0],
            ['litre',     'Litre',       'l',       'ml', 1000.0],
            ['pièce',     'Pièce',       'pcs',     null, 1.0],
            ['carton',    'Carton',      'ctn',     'pièce', 1.0],
            ['casier',    'Casier',      'casier',  'pièce', 1.0],
            ['sac',       'Sac',         'sac',     null, 1.0],
            ['paquet',    'Paquet',      'paquet',  null, 1.0],
            ['boîte',     'Boîte',       'bte',     null, 1.0],
            ['bouteille', 'Bouteille',   'btl',     null, 1.0],
            ['rouleau',   'Rouleau',     'rlx',     null, 1.0],
            ['mètre',     'Mètre',       'm',       null, 1.0],
            ['lot',       'Lot',         'lot',     null, 1.0],
            ['ramette',   'Ramette',     'ramette', null, 1.0],
            ['flacon',    'Flacon',      'flc',     null, 1.0],
            ['bidon',     'Bidon',       'bidon',   null, 1.0],
            ['paire',     'Paire',       'paire',   null, 1.0],
            ['portion',   'Portion',     'portion', null, 1.0],
        ];

        // Mappage par clé minuscule
        $idMap = [];

        // Première passe : mettre à jour ou insérer les noms longs et courts
        $rang = (int) DB::table('stock_units')->max('sort_order');
        foreach ($unitesStandards as [$cle, $nomLong, $nomCourt, $refCle, $facteur]) {
            $existant = DB::table('stock_units')
                ->whereRaw('LOWER(name) = ? OR LOWER(short_name) = ?', [mb_strtolower($cle), mb_strtolower($nomCourt)])
                ->first();

            if ($existant) {
                DB::table('stock_units')->where('id', $existant->id)->update([
                    'name'       => $nomLong,
                    'short_name' => $nomCourt,
                    'factor'     => $facteur,
                    'updated_at' => $now,
                ]);
                $idMap[$cle] = $existant->id;
                $idMap[$nomCourt] = $existant->id;
                $idMap[mb_strtolower($nomLong)] = $existant->id;
            } else {
                $nouveauId = DB::table('stock_units')->insertGetId([
                    'name'       => $nomLong,
                    'short_name' => $nomCourt,
                    'factor'     => $facteur,
                    'sort_order' => ++$rang,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $idMap[$cle] = $nouveauId;
                $idMap[$nomCourt] = $nouveauId;
                $idMap[mb_strtolower($nomLong)] = $nouveauId;
            }
        }

        // Deuxième passe : lier les unités de référence
        foreach ($unitesStandards as [$cle, $nomLong, $nomCourt, $refCle, $facteur]) {
            if ($refCle && isset($idMap[$refCle]) && isset($idMap[$cle])) {
                DB::table('stock_units')->where('id', $idMap[$cle])->update([
                    'reference_unit_id' => $idMap[$refCle],
                ]);
            }
        }

        // Pour toute autre unité existante qui n'a pas encore de short_name : lui affecter son name comme short_name
        DB::table('stock_units')->whereNull('short_name')->update([
            'short_name' => DB::raw('name'),
            'factor'     => 1.0,
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_units', function (Blueprint $table) {
            $table->dropForeign(['reference_unit_id']);
            $table->dropColumn(['reference_unit_id', 'factor', 'short_name']);
            $table->string('name', 20)->change();
        });
    }
};
