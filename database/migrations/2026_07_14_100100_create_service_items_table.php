<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();

            // activity, spa, housekeeping, laundry, minibar, other
            $table->string('category', 30);

            $table->string('name', 140);
            $table->text('description')->nullable();

            // Montant en centimes FCFA
            $table->unsignedInteger('price')->default(0);

            // Durée indicative (spa, activités) — en minutes
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
            $table->unique(['category', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
