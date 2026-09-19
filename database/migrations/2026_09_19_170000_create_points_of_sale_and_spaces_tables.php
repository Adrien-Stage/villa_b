<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points de vente et espaces.
 *
 * Deux axes que l'application confondait, et qu'un hôtel à plusieurs
 * restaurants oblige à séparer :
 *
 *   - le POINT DE VENTE dit où le chiffre se comptabilise. Le journal des
 *     encaissements d'un établissement réel en aligne cinq — HOTEL, KOTIBE,
 *     BALENG, MINI BAR, BANQUET — chacun avec sa série de numérotation et sa
 *     ligne de récapitulatif.
 *   - l'ESPACE dit où la prestation se déroule. Un banquet se tient dans la
 *     salle de l'un ou l'autre restaurant tout en facturant sur sa propre
 *     série.
 *
 * Les confondre coûte deux choses : on ne peut ni tenir deux banquets dans
 * deux salles, ni voir qu'un banquet et le service du soir se disputent la
 * même salle un samedi.
 *
 * Jusqu'ici Restaurant, Boutique et Réception étaient trois silos écrits en
 * dur. Ajouter un second restaurant demandait du code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_sale', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();          // H, KOT, BAL, MB, BQT
            $table->string('name', 100);
            $table->string('slug', 64)->unique();

            // Nature du point de vente : elle décide des écrans et des
            // rattachements comptables, non son nom, qui appartient au client.
            $table->string('kind', 32);

            // Préfixe des numéros de note : « H-7906 », « KOT-4853 », « BQT167 ».
            // C'est lui qui rend une pièce reconnaissable sur un journal papier.
            $table->string('series_prefix', 8)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 64)->unique();
            $table->unsignedSmallInteger('capacity')->nullable();

            // Point de vente auquel l'espace appartient d'ordinaire. Nullable :
            // une salle polyvalente n'appartient à personne, et c'est l'usage
            // du jour qui décide de la série sur laquelle on facture.
            $table->foreignId('point_of_sale_id')->nullable()->constrained('points_of_sale')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
        Schema::dropIfExists('points_of_sale');
    }
};
