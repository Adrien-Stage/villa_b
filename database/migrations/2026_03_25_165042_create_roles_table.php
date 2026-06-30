<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom du rôle (admin, manager, reception, etc.)
            $table->string('slug')->unique(); // Slug unique pour les vérifications
            $table->text('description')->nullable(); // Description du rôle
            // Multi-tenant (null pour admin global)
            $table->timestamps();

            // Index pour les performances
            $table->index(['slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
