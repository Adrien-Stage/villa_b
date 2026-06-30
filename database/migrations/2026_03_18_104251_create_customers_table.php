<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // --- IDENTITÉ ---
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable(); // nullable : client walk-in sans email
            $table->string('phone', 30)->nullable();
            $table->string('nationality', 5)->nullable(); // Code ISO : 'CM', 'FR', 'US'
            $table->date('date_of_birth')->nullable();

            // --- DOCUMENT D'IDENTITÉ ---
            // Obligatoire pour la police du tourisme au Cameroun
            // 'passport', 'id_card', 'driver_license', 'residence_permit'
            $table->string('id_document_type', 30)->nullable();
            $table->string('id_document_number')->nullable();

            // --- ADRESSE ---
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 5)->nullable(); // Code ISO pays

            // --- PRÉFÉRENCES (JSON) ---
            // Évite des dizaines de colonnes booléennes
            // Ex: {"room_type": "suite", "floor": "high", "pillow": "firm"}
            $table->json('preferences')->nullable();

            // Allergies séparées des préférences : c'est de la sécurité, pas du confort
            // Ex: ["arachides", "gluten", "lactose"]
            $table->json('allergies')->nullable();

            $table->text('special_requests')->nullable();

            // --- FIDÉLITÉ ---
            // Points cumulés (non dépensés)
            $table->unsignedInteger('loyalty_points')->default(0);
            // 'bronze', 'silver', 'gold', 'platinum'
            $table->string('loyalty_level', 20)->default('bronze');
            // Métriques cumulées — mises à jour à chaque check-out
            $table->unsignedInteger('total_nights_stayed')->default(0);
            // Montant total dépensé en centimes FCFA
            $table->unsignedBigInteger('total_spent')->default(0);
            // unsignedBigInteger ici : un client fidèle sur 10 ans peut dépenser
            // des dizaines de millions de FCFA → dépasse unsignedInteger (4 milliards centimes)

            $table->string('photo_path')->nullable();

            // --- FLAGS ---
            $table->boolean('is_vip')->default(false);
            // Client blacklisté : impayés, comportement, dégradations
            // On ne supprime pas, on blackliste — pour garder la trace
            $table->boolean('is_blacklisted')->default(false);
            $table->text('notes')->nullable(); // Notes internes staff (jamais visibles client)

            $table->timestamps();

            // Index pour la recherche rapide par nom (réception qui cherche "Dupont")
            $table->index(['last_name', 'first_name']);

            // Index pour retrouver un client par email
            $table->index(['email']);

            // Index pour les rapports VIP et blacklist
            $table->index(['is_vip']);
            $table->index(['is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
