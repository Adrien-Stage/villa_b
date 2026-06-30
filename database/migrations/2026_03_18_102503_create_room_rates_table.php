<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();

            // Ce tarif s'applique à UN type de chambre
            // restrict : on ne supprime pas un type si des tarifs y sont attachés
            $table->foreignId('room_type_id')->constrained()->onDelete('restrict');

            // Nom lisible par l'humain : "Haute Saison 2025", "Tarif FESPAM"
            $table->string('name');

            // Le type détermine COMMENT ce tarif est évalué
            // 'seasonal' | 'weekly' | 'event' | 'long_stay' | 'last_minute'
            $table->string('rate_type', 30);

            // --- PÉRIODE D'APPLICATION ---

            $table->date('start_date');
            $table->date('end_date');

            // Jours de la semaine concernés : [0=dim, 1=lun, ..., 6=sam]
            // null = tous les jours
            // Ex: [5, 6] = week-end seulement
            $table->json('days_of_week')->nullable();

            // --- RÈGLE DE PRIX ---

            // 'fixed'      : le prix final est price_adjustment (ex: 60000 centimes)
            // 'percentage' : on ajoute X% au prix de base (ex: +20 pour haute saison)
            // 'amount_off' : on soustrait un montant fixe (ex: -5000 pour early bird)
            $table->string('price_adjustment_type', 20);
            $table->integer('price_adjustment'); // Peut être négatif (réduction)

            // --- CONDITIONS D'ÉLIGIBILITÉ ---

            // Pour long_stay : s'applique à partir de X nuits
            $table->unsignedSmallInteger('min_nights')->nullable();
            $table->unsignedSmallInteger('max_nights')->nullable();

            // Pour last_minute : réservation faite entre X et Y jours avant l'arrivée
            $table->unsignedSmallInteger('min_advance_days')->nullable();
            $table->unsignedSmallInteger('max_advance_days')->nullable();

            // Pour les événements : nom pour les rapports ("FESPAM 2025")
            $table->string('event_name')->nullable();

            $table->boolean('is_active')->default(true);

            // Priorité : si deux tarifs sont applicables le même jour,
            // celui avec la priorité la PLUS HAUTE gagne
            // Ex: un tarif événementiel (priority=10) bat un tarif saisonnier (priority=5)
            $table->unsignedSmallInteger('priority')->default(1);

            $table->timestamps();

            // Index pour le calcul de prix lors d'une recherche de disponibilité
            // "Quels tarifs actifs existent pour ce type de chambre de ce tenant ?"
            $table->index(['room_type_id', 'is_active']);

            // Index pour filtrer par période rapidement
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};