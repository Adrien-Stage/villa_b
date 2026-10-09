<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les unités de stockage des articles de l'économat (Paramètres › Économat).
 *
 * L'unité d'un article se tapait librement : « kg », « Kg », « kilo »
 * désignaient la même chose et rendaient les fiches de comptage et les
 * commandes incohérentes. Elle se choisit désormais dans une liste que
 * l'économe tient à jour.
 *
 * L'article garde son unité en clair (stock_items.unit) : bons, fiches et
 * éditions la lisent telle quelle. La liste est amorcée avec les unités
 * courantes et celles déjà employées par les articles ; une unité saisie
 * avec une autre casse est ramenée à celle de la liste.
 */
return new class extends Migration
{
    private const UNITES_COURANTES = [
        'pièce', 'kg', 'g', 'litre', 'cl', 'carton', 'casier', 'sac',
        'paquet', 'boîte', 'bouteille', 'rouleau', 'mètre', 'lot',
    ];

    public function up(): void
    {
        Schema::create('stock_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $maintenant = now();
        $connues = [];

        foreach (self::UNITES_COURANTES as $rang => $nom) {
            DB::table('stock_units')->insert([
                'name' => $nom, 'sort_order' => $rang + 1, 'is_active' => true,
                'created_at' => $maintenant, 'updated_at' => $maintenant,
            ]);
            $connues[mb_strtolower($nom)] = $nom;
        }

        $rang = count(self::UNITES_COURANTES);
        $employees = DB::table('stock_items')->whereNotNull('unit')->distinct()->pluck('unit');

        foreach ($employees as $unite) {
            $nom = trim((string) $unite);
            if ($nom === '') {
                continue;
            }

            $cle = mb_strtolower($nom);
            if (!isset($connues[$cle])) {
                DB::table('stock_units')->insert([
                    'name' => mb_substr($nom, 0, 20), 'sort_order' => ++$rang, 'is_active' => true,
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
                $connues[$cle] = mb_substr($nom, 0, 20);
            }

            if ($connues[$cle] !== $unite) {
                DB::table('stock_items')->where('unit', $unite)->update(['unit' => $connues[$cle]]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_units');
    }
};
