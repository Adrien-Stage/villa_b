<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fiche technique : la nomenclature d'un plat (ou d'une préparation de base).
        Schema::create('restaurant_recipes', function (Blueprint $table) {
            $table->id();

            $table->string('name', 140);

            // dish = fiche d'un plat du menu ; prep = préparation de base fabriquée
            // en batch (sauce ndolé, fond, marinade) et consommée par d'autres fiches.
            $table->string('type', 10)->default('dish');

            $table->foreignId('restaurant_menu_item_id')
                ->nullable()
                ->unique()
                ->constrained('restaurant_menu_items')
                ->cascadeOnDelete();

            // Pour une préparation : l'article de garde-manger qu'elle alimente.
            $table->foreignId('produces_pantry_item_id')
                ->nullable()
                ->unique()
                ->constrained('restaurant_pantry_items')
                ->cascadeOnDelete();

            // Rendement. Plat : nombre de portions produites par la fiche (souvent 1).
            // Préparation : quantité produite, dans l'unité de l'article produit.
            $table->decimal('yield_quantity', 12, 3)->default(1);

            $table->text('notes')->nullable();

            // Fiche désactivée = le plat ne décrémente plus le stock à la vente.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // Les ingrédients de la fiche.
        Schema::create('restaurant_recipe_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_recipe_id')
                ->constrained('restaurant_recipes')
                ->cascadeOnDelete();

            $table->foreignId('restaurant_pantry_item_id')
                ->constrained('restaurant_pantry_items')
                ->restrictOnDelete();

            // Quantité NETTE nécessaire pour la totalité du rendement, exprimée dans
            // l'unité de stock de l'ingrédient.
            $table->decimal('quantity', 12, 3);

            // Perte au parage / à la cuisson : un kilo de poisson entier ne donne pas
            // un kilo de filet. La quantité réellement sortie du stock est
            // quantity / (1 - waste_percent / 100).
            $table->decimal('waste_percent', 5, 2)->default(0);

            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->unique(['restaurant_recipe_id', 'restaurant_pantry_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_recipe_lines');
        Schema::dropIfExists('restaurant_recipes');
    }
};
