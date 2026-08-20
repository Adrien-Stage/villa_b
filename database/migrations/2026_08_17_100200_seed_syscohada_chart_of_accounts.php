<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plan de comptes SYSCOHADA révisé — sous-ensemble hôtelier — et journaux.
 *
 * Passe par une migration et non par un seeder : côté production,
 * ProductionTenantSeeder ne tourne qu'au tout premier démarrage. Un plan de
 * comptes livré en seeder n'atteindrait aucun établissement déjà en service.
 *
 * ⚠️ Ce plan doit être validé par l'expert-comptable avant mise en service.
 * Il couvre les opérations courantes de l'établissement ; il est volontairement
 * restreint — un plan pléthorique nuit à la lisibilité de la balance autant
 * qu'un plan pollué par des comptes de tiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // [code, libellé, classe, collectif, imputable]
        $accounts = [
            // ── Classe 1 — Ressources durables ──────────────────────────────
            ['10',     'Capital',                                        1, false, false],
            ['101000', 'Capital social',                                 1, false, true],
            ['11',     'Réserves',                                       1, false, false],
            ['110000', 'Report à nouveau',                               1, false, true],
            ['12',     'Report à nouveau',                               1, false, false],
            ['120000', 'Report à nouveau créditeur',                     1, false, true],
            ['129000', 'Report à nouveau débiteur',                      1, false, true],
            ['13',     'Résultat net de l’exercice',                     1, false, false],
            ['130000', 'Résultat net de l’exercice',                     1, false, true],
            ['16',     'Emprunts et dettes assimilées',                  1, false, false],
            ['162000', 'Emprunts auprès des établissements de crédit',   1, false, true],

            // ── Classe 2 — Actif immobilisé ─────────────────────────────────
            ['22',     'Terrains',                                       2, false, false],
            ['220000', 'Terrains',                                       2, false, true],
            ['23',     'Bâtiments, installations et agencements',        2, false, false],
            ['231000', 'Bâtiments industriels et commerciaux',           2, false, true],
            ['24',     'Matériel, mobilier et actifs biologiques',       2, false, false],
            ['241000', 'Matériel et outillage',                          2, false, true],
            ['244000', 'Matériel et mobilier',                           2, false, true],
            ['245000', 'Matériel de transport',                          2, false, true],
            ['28',     'Amortissements',                                 2, false, false],
            ['281000', 'Amortissements des bâtiments',                   2, false, true],
            ['284000', 'Amortissements du matériel et mobilier',         2, false, true],

            // ── Classe 3 — Stocks ───────────────────────────────────────────
            ['31',     'Marchandises',                                   3, false, false],
            ['311000', 'Marchandises — boissons',                        3, false, true],
            ['312000', 'Marchandises — boutique',                        3, false, true],
            ['32',     'Matières premières et fournitures liées',        3, false, false],
            ['321000', 'Matières premières — cuisine',                   3, false, true],
            ['33',     'Autres approvisionnements',                      3, false, false],
            ['331000', 'Fournitures d’entretien et petit équipement',    3, false, true],
            ['332000', 'Fournitures d’économat',                         3, false, true],

            // ── Classe 4 — Tiers ────────────────────────────────────────────
            ['40',     'Fournisseurs et comptes rattachés',              4, false, false],
            ['401000', 'Fournisseurs',                                   4, true,  true],
            ['408000', 'Fournisseurs — factures non parvenues',          4, false, true],
            ['41',     'Clients et comptes rattachés',                   4, false, false],
            ['411000', 'Clients',                                        4, true,  true],
            ['416000', 'Clients douteux ou litigieux',                   4, true,  true],
            ['419000', 'Clients créditeurs — avances et acomptes reçus', 4, true,  true],
            ['42',     'Personnel',                                      4, false, false],
            ['421000', 'Personnel — rémunérations dues',                 4, true,  true],
            ['44',     'État et collectivités publiques',                4, false, false],
            ['442100', 'État — retenues à la source',                    4, false, true],
            ['443100', 'État — TVA facturée sur ventes',                 4, false, true],
            ['443700', 'État — centimes additionnels communaux',         4, false, true],
            ['445100', 'État — TVA récupérable sur achats',              4, false, true],
            ['447000', 'État — autres impôts et taxes',                  4, false, false],
            ['447100', 'Taxe de séjour à reverser',                      4, false, true],

            // ── Classe 5 — Trésorerie ───────────────────────────────────────
            ['52',     'Banques',                                        5, false, false],
            ['521000', 'Banques locales',                                5, false, true],
            ['53',     'Établissements financiers et assimilés',         5, false, false],
            ['531000', 'Mobile money',                                   5, false, true],
            ['57',     'Caisse',                                         5, false, false],
            ['571000', 'Caisse — siège social',                          5, false, true],
            ['58',     'Virements internes',                             5, false, false],
            ['585000', 'Virements de fonds',                             5, false, true],
            ['588000', 'Autres virements internes',                      5, false, true],

            // ── Classe 6 — Charges ──────────────────────────────────────────
            ['60',     'Achats et variations de stocks',                 6, false, false],
            ['601000', 'Achats de marchandises',                         6, false, true],
            ['602000', 'Achats de matières premières et fournitures',    6, false, true],
            ['603200', 'Variation des stocks de matières premières',     6, false, true],
            ['603100', 'Variation des stocks de marchandises',           6, false, true],
            ['605000', 'Autres achats — eau, électricité, carburant',    6, false, true],
            ['61',     'Transports',                                     6, false, false],
            ['611000', 'Transports sur achats',                          6, false, true],
            ['62',     'Services extérieurs A',                          6, false, false],
            ['622000', 'Locations et charges locatives',                 6, false, true],
            ['624000', 'Entretien, réparations et maintenance',          6, false, true],
            ['625000', 'Primes d’assurance',                             6, false, true],
            ['63',     'Services extérieurs B',                          6, false, false],
            ['632000', 'Rémunérations d’intermédiaires et honoraires',   6, false, true],
            ['633000', 'Frais de formation du personnel',                6, false, true],
            ['64',     'Impôts et taxes',                                6, false, false],
            ['641000', 'Impôts et taxes directs',                        6, false, true],
            ['646000', 'Droits d’enregistrement',                        6, false, true],
            ['65',     'Autres charges',                                 6, false, false],
            ['658000', 'Charges diverses',                               6, false, true],
            ['66',     'Charges de personnel',                           6, false, false],
            ['661000', 'Rémunérations directes versées au personnel',    6, false, true],
            ['664000', 'Charges sociales',                               6, false, true],

            // ── Classe 7 — Produits ─────────────────────────────────────────
            ['70',     'Ventes',                                         7, false, false],
            ['701000', 'Ventes de marchandises — boutique',              7, false, true],
            ['702000', 'Ventes de marchandises — boissons',              7, false, true],
            ['706000', 'Services vendus — hébergement',                  7, false, true],
            ['706100', 'Services vendus — restauration',                 7, false, true],
            ['706200', 'Services vendus — prestations annexes',          7, false, true],
            ['707000', 'Produits accessoires',                           7, false, true],
            ['75',     'Autres produits',                                7, false, false],
            ['758000', 'Produits divers',                                7, false, true],

            // ── Classe 9 — Comptabilité analytique ──────────────────────────
            ['90',     'Comptes reflétés',                               9, false, false],
            ['901000', 'Comptes reflétés — charges',                     9, false, true],
            ['902000', 'Comptes reflétés — produits',                    9, false, true],
            ['91',     'Comptes de reclassement',                        9, false, false],
            ['911000', 'Reclassement des charges par destination',       9, false, true],
            ['92',     'Comptes de coûts',                               9, false, false],
            ['921000', 'Coûts — hébergement',                            9, false, true],
            ['922000', 'Coûts — restauration',                           9, false, true],
            ['923000', 'Coûts — boutique',                               9, false, true],
            ['924000', 'Coûts — économat',                               9, false, true],
        ];

        foreach ($accounts as [$code, $label, $class, $collective, $postable]) {
            DB::table('accounts')->updateOrInsert(
                ['code' => $code],
                [
                    'label'         => $label,
                    'account_class' => $class,
                    'is_collective' => $collective,
                    'is_postable'   => $postable,
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
        }

        // ── Journaux ────────────────────────────────────────────────────────
        $journals = [
            ['VT', 'Journal des ventes',              null],
            ['AC', 'Journal des achats',              null],
            ['BQ', 'Journal de banque',               '521000'],
            ['CA', 'Journal de caisse',               '571000'],
            ['OD', 'Journal des opérations diverses', null],
        ];

        foreach ($journals as [$code, $label, $default]) {
            DB::table('journals')->updateOrInsert(
                ['code' => $code],
                [
                    'label'           => $label,
                    'default_account' => $default,
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('journals')->delete();
        DB::table('accounts')->delete();
    }
};
