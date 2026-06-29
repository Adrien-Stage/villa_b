<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();

            // Chaque type de chambre appartient à un établissement
            // cascade : si on supprime le tenant, ses types de chambres disparaissent aussi
            // Nom affiché au client : "Suite Présidentielle", "Chambre Standard"
            $table->string('name');

            // Code interne court : "SUITE-PRES", "STD"
            // Utilisé dans les rapports, exports, et l'URL du wizard de réservation
            // unique() PAR tenant : deux hôtels peuvent avoir leur propre "STD"
            $table->string('code', 50);

            $table->text('description')->nullable();

            // Capacité standard (2 adultes) vs maximale (2 adultes + 1 enfant avec lit appoint)
            // unsignedSmallInteger : valeur positive, max 65535 — largement suffisant pour des personnes
            $table->unsignedSmallInteger('base_capacity');
            $table->unsignedSmallInteger('max_capacity');

            $table->unsignedSmallInteger('size_sqm')->nullable(); // Surface en m²

            // Prix stocké en CENTIMES pour éviter les erreurs d'arrondi
            // 45000 FCFA → on stocke 4500000
            // Règle d'or : ne JAMAIS stocker de l'argent en FLOAT ou DECIMAL en PHP
            $table->unsignedInteger('base_price');

            // JSON natif PostgreSQL : liste d'équipements variable sans colonnes supplémentaires
            // Ex: ["wifi", "climatisation", "minibar", "jacuzzi", "coffre-fort"]
            $table->json('amenities')->nullable();

            $table->string('image_path')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Contrainte d'unicité composite : le code doit être unique POUR ce tenant
            $table->unique(['code']);

            // Index pour la requête la plus fréquente :
            // "Donne-moi tous les types actifs de cet hôtel"
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};