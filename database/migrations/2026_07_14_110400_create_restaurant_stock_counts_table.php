<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Inventaire physique : on compte réellement, et on compare au stock
        // théorique issu des fiches techniques. L'écart est ce qui révèle le
        // gaspillage, le sur-portionnage et le vol.
        Schema::create('restaurant_stock_counts', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 30)->unique();

            // draft = feuille de comptage en cours ; closed = écarts appliqués au stock
            $table->string('status', 10)->default('draft');

            $table->text('notes')->nullable();

            // Écart total valorisé (centimes FCFA, signé : négatif = manquant)
            $table->bigInteger('variance_value')->default(0);

            $table->unsignedBigInteger('opened_by')->nullable();
            $table->foreign('opened_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('closed_by')->nullable();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('restaurant_stock_count_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_stock_count_id')
                ->constrained('restaurant_stock_counts')
                ->cascadeOnDelete();

            $table->foreignId('restaurant_pantry_item_id')
                ->constrained('restaurant_pantry_items')
                ->cascadeOnDelete();

            // Stock théorique figé à l'ouverture de la feuille de comptage
            $table->decimal('theoretical_quantity', 12, 3)->default(0);

            // Quantité réellement comptée (null tant que la ligne n'est pas saisie)
            $table->decimal('counted_quantity', 12, 3)->nullable();

            // counted - theoretical (négatif = manquant)
            $table->decimal('variance_quantity', 12, 3)->default(0);

            // Coût moyen pondéré au moment du comptage, et écart valorisé
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->bigInteger('variance_value')->default(0);

            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->unique(['restaurant_stock_count_id', 'restaurant_pantry_item_id'], 'stock_count_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_stock_count_lines');
        Schema::dropIfExists('restaurant_stock_counts');
    }
};
