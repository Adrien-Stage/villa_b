<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_pantry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_pantry_category_id')
                ->nullable()
                ->constrained('restaurant_pantry_categories')
                ->nullOnDelete();

            $table->string('name', 140);
            $table->string('unit', 20)->default('pcs'); // pcs, kg, g, l, ml

            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);

            // Prix d'achat (optionnel) en centimes
            $table->unsignedInteger('cost_price')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
            $table->index(['restaurant_pantry_category_id']);
            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_pantry_items');
    }
};

