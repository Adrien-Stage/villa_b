<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            // restrict : impossible de supprimer un type de chambre
            // si des chambres physiques y sont encore rattachées
            // C'est une protection métier — tu dois d'abord reclasser les chambres
            $table->foreignId('room_type_id')->constrained()->onDelete('restrict');

            // Le numéro affiché : "101", "A-12", "Villa-3"
            // unique PAR tenant : deux hôtels peuvent avoir une chambre "101"
            $table->string('number', 20);

            $table->string('floor', 10)->nullable();

            // Vue depuis la chambre — valeur marketing importante
            // 'garden', 'pool', 'heritage', 'courtyard', 'city'
            // On utilise string et non enum SQL → plus facile à étendre sans migration
            $table->string('view_type', 50)->nullable();

            // Statut opérationnel de la chambre en temps réel
            // Géré via l'Enum RoomStatus dans le modèle
            // 'available', 'occupied', 'cleaning', 'maintenance', 'out_of_order'
            $table->string('status', 30)->default('available');

            $table->text('notes')->nullable(); // Notes internes staff

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Unicité du numéro de chambre par établissement
            $table->unique(['number']);

            // Index pour le dashboard temps réel :
            // "Combien de chambres disponibles dans cet hôtel ?"
            $table->index(['status']);

            // Index composite pour le wizard de réservation :
            // "Toutes les chambres Standard disponibles de cet hôtel"
            $table->index(['room_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};