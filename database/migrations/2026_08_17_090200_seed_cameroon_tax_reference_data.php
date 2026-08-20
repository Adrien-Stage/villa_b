<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Données de référence fiscales — Cameroun.
 *
 * Passe par une migration et non par un seeder : côté production,
 * ProductionTenantSeeder ne tourne qu'au tout premier démarrage. Un barème
 * livré en seeder n'atteindrait aucun établissement déjà en service.
 *
 * ⚠️ Les montants de la taxe de séjour sont PROVISOIRES : ils viennent des
 * éléments transmis au moment de la conception, dont l'une des sources
 * concernait la Côte d'Ivoire. À faire confirmer par l'expert-comptable
 * avant mise en service — d'où le passage par une table paramétrable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── Taux de taxe ────────────────────────────────────────────────────
        // 19,25 % = TVA 17,5 % majorée de 10 % de centimes additionnels
        // communaux (17,5 × 1,10). On mémorise la part CAC pour pouvoir la
        // ventiler si l'expert-comptable l'exige, sans migration de plus.
        $rates = [
            [
                'code'                => 'STANDARD',
                'label'               => 'TVA 19,25 % (dont CAC)',
                'rate_basis_points'   => 1925,
                'surtax_basis_points' => 175,
                'collected_account'   => '4431',
                'deductible_account'  => '4451',
                'surtax_account'      => null,
                'is_default'          => true,
            ],
            [
                'code'                => 'EXONERE',
                'label'               => 'Exonéré (0 %)',
                'rate_basis_points'   => 0,
                'surtax_basis_points' => 0,
                'collected_account'   => null,
                'deductible_account'  => null,
                'surtax_account'      => null,
                'is_default'          => false,
            ],
        ];

        foreach ($rates as $rate) {
            DB::table('tax_rates')->updateOrInsert(
                ['code' => $rate['code']],
                $rate + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // ── Barème de la taxe de séjour (centimes FCFA) ─────────────────────
        $brackets = [
            ['classification' => 'non_classe', 'label' => 'Non classé',  'amount_per_night' => 50_000],
            ['classification' => '1',          'label' => '1 étoile',    'amount_per_night' => 50_000],
            ['classification' => '2',          'label' => '2 étoiles',   'amount_per_night' => 100_000],
            ['classification' => '3',          'label' => '3 étoiles',   'amount_per_night' => 200_000],
            ['classification' => '4',          'label' => '4 étoiles',   'amount_per_night' => 300_000],
            ['classification' => '5',          'label' => '5 étoiles',   'amount_per_night' => 500_000],
        ];

        foreach ($brackets as $bracket) {
            DB::table('tourist_tax_brackets')->updateOrInsert(
                ['classification' => $bracket['classification']],
                $bracket + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('tax_rates')->whereIn('code', ['STANDARD', 'EXONERE'])->delete();
        DB::table('tourist_tax_brackets')->delete();
    }
};
