<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventaire d'un dépôt de service.
 *
 * Le dépôt ne déclare pas ce qu'il consomme : le mini-bar, les étages, le
 * bar servent sans saisir chaque bouteille. L'inventaire le révèle :
 * consommation = stock théorique (entrées depuis le dernier comptage) moins
 * stock compté. La clôture l'inscrit au dépôt et le night audit la passe en
 * charge du service.
 *
 * Montants en centimes FCFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_store_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('service_store_id')->constrained()->cascadeOnDelete();
            // draft = comptage en cours ; closed = régularisé ; cancelled = abandonné
            $table->string('status', 20)->default('draft');
            $table->date('count_date');
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['service_store_id', 'status']);
        });

        Schema::create('service_store_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_store_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            // Stock théorique et coût moyen figés à l'ouverture.
            $table->decimal('theoretical_quantity', 12, 3)->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);
            // Nul tant que la ligne n'est pas comptée.
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['service_store_count_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_store_count_lines');
        Schema::dropIfExists('service_store_counts');
    }
};
