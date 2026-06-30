<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_menu_category_id')
                ->nullable()
                ->constrained('restaurant_menu_categories')
                ->nullOnDelete();

            $table->string('name', 140);
            $table->text('description')->nullable();

            // Montants en centimes FCFA
            $table->unsignedInteger('price')->default(0);

            // food, drink, other
            $table->string('type', 20)->default('food');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'type', 'sort_order']);
            $table->index(['restaurant_menu_category_id', 'sort_order']);
            $table->unique(['name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_menu_items');
    }
};

