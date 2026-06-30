<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            // Lié à une réservation — le folio est attaché au séjour
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');

            // Lié au client pour retrouver l'historique même après archivage
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            // Type de prestation
            // 'room', 'restaurant', 'activity', 'spa', 'minibar',
            // 'laundry', 'phone', 'discount', 'payment', 'other'
            $table->string('type', 30);

            $table->string('description');         // "Nuit chambre 101", "Dîner gastronomique"
            $table->decimal('quantity', 8, 2)->default(1);
            $table->integer('unit_price');         // En centimes, peut être 0
            $table->integer('total_price');        // quantity * unit_price, peut être 0

            // Activités non payantes : montant 0 mais on garde la trace
            $table->boolean('is_complimentary')->default(false); // true = offert

            // Ce poste génère-t-il des points fidélité ?
            $table->boolean('earns_points')->default(true);

            // Qui a enregistré cette ligne (réceptionniste, serveur, etc.)
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            // Horodatage de la prestation (pas forcément = created_at)
            // Ex: minibar consommé à 23h mais enregistré le lendemain matin
            $table->timestamp('occurred_at');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Index pour afficher le folio d'un séjour chronologiquement
            $table->index(['booking_id', 'occurred_at']);

            // Index pour reconstruire l'historique complet d'un client
            $table->index(['customer_id', 'occurred_at']);

            // Index pour les rapports par type de prestation
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_items');
    }
};