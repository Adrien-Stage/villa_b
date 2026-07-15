<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prise de service d'un serveur en salle. Un serveur « en service » a une
        // ligne dont closed_at est null : seuls ceux-là reçoivent des commandes du
        // portail via la répartition automatique.
        Schema::create('restaurant_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_shifts');
    }
};
