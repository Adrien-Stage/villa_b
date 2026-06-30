<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            // null = transaction manuelle (bonus offert par le manager)
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();

            // Positif = gain de points, négatif = utilisation
            $table->integer('points');

            // 'earned'   : points gagnés après un séjour
            // 'redeemed' : points dépensés comme remise
            // 'expired'  : points annulés après inactivité
            // 'manual'   : ajustement manuel par le manager
            $table->string('type', 30);

            $table->text('description')->nullable();

            // Solde après cette transaction — évite de recalculer à chaque fois
            $table->unsignedInteger('balance_after');

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};