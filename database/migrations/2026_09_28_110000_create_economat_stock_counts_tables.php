<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Campagne d'inventaire physique de l'économat ────────────────────
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();

            // Filtrage optionnel par catégorie (ex: Boissons, Épicerie) ou null pour inventaire global
            $table->foreignId('stock_category_id')
                ->nullable()
                ->constrained('stock_categories')
                ->nullOnDelete();

            // draft = en cours de comptage ; closed = validé et écarts régularisés ; cancelled = annulé
            $table->string('status', 20)->default('draft');
            $table->date('count_date');
            $table->text('notes')->nullable();

            // Montants financiers en centimes FCFA
            $table->unsignedBigInteger('total_theoretical_value')->default(0);
            $table->unsignedBigInteger('total_counted_value')->default(0);
            $table->bigInteger('variance_value')->default(0); // signé (négatif = perte nette)
            $table->unsignedBigInteger('loss_value')->default(0); // total des pertes
            $table->unsignedBigInteger('surplus_value')->default(0); // total des excédents

            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'count_date']);
            $table->index(['stock_category_id']);
        });

        // ── Lignes d'inventaire physique ───────────────────────────────────
        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();

            // Stock théorique figé à l'ouverture de la feuille
            $table->decimal('theoretical_quantity', 12, 3)->default(0);

            // Quantité réellement constatée en rayon (null tant que non compté)
            $table->decimal('counted_quantity', 12, 3)->nullable();

            // Écart : counted - theoretical
            $table->decimal('variance_quantity', 12, 3)->default(0);

            // CUMP de l'article en centimes FCFA au moment de l'inventaire
            $table->unsignedBigInteger('unit_cost')->default(0);

            // Valeurs financières en centimes FCFA
            $table->unsignedBigInteger('theoretical_value')->default(0);
            $table->unsignedBigInteger('counted_value')->nullable();
            $table->bigInteger('variance_value')->default(0);

            // Motif de l'écart : waste, spoilage, packaging, input_error, theft, other
            $table->string('reason', 50)->nullable();
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->unique(['stock_count_id', 'stock_item_id']);
            $table->index(['reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
