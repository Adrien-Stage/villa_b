<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche technique d'un type de chambre : de quoi mesurer la marge sur une
 * chambre louée (coût variable par nuitée vs prix de vente).
 *
 * Deux tables :
 *  - room_cost_sheets : les hypothèses de la fiche (une par type de chambre) ;
 *  - room_cost_items  : les lignes de coût (électricité, eau, consommables…).
 *
 * Montants en centimes FCFA, quantités en décimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->unique()->constrained()->cascadeOnDelete();

            // Nombre de personnes servant de référence pour les lignes « par
            // personne et par nuitée ». Par défaut, la capacité de base du type.
            $table->unsignedTinyInteger('reference_occupants')->nullable();

            // Durée moyenne de séjour, sur laquelle on amortit les coûts « par
            // séjour » pour les exprimer par nuitée. Null = calculée sur les
            // réservations réelles, avec repli à 1 nuit.
            $table->decimal('avg_length_of_stay', 6, 2)->nullable();

            // Charge fixe estimée allouée à une nuitée (amortissement, personnel
            // fixe…). Optionnelle : permet d'afficher une marge nette en plus de
            // la marge de contribution.
            $table->unsignedBigInteger('fixed_cost_per_night')->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('room_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            // energy | water | consumable | linen | housekeeping | maintenance | amenity | other
            $table->string('category', 20)->default('other');
            $table->string('label', 160);

            // Base de calcul : per_night (par nuitée occupée) | per_guest_night
            // (par personne et nuitée) | per_stay (une fois par séjour, amorti).
            $table->string('basis', 20)->default('per_night');

            // Inducteur (kWh, m³, nombre d'unités, minutes…).
            $table->decimal('quantity', 12, 3)->default(1);
            // Prix unitaire (par kWh, par m³, par unité…). Ignoré si un article
            // d'économat est lié : le prix vient alors de son coût moyen pondéré.
            $table->unsignedBigInteger('unit_cost')->default(0);

            // Lien optionnel vers un article de l'économat : la ligne se valorise
            // au coût moyen pondéré de l'article, synchronisé avec les achats.
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['room_type_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_cost_items');
        Schema::dropIfExists('room_cost_sheets');
    }
};
