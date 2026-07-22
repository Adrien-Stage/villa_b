<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module économat : magasin central de l'établissement.
 *
 * L'économat approvisionne tous les départements (hébergement, housekeeping,
 * restaurant, boutique). Il ne remplace pas le garde-manger du restaurant, qui
 * reste le stock de travail de la cuisine : l'économat lui livre sur demande.
 *
 * Tous les montants sont en centimes FCFA ; les quantités en décimal pour
 * gérer les unités fractionnables (kg, litres).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Fournisseurs ──────────────────────────────────────────────────
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 30)->nullable();
            $table->string('contact_name', 120)->nullable();
            // L'email est la clé de l'envoi des bons de commande.
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
        });

        // ── Catégories d'articles ─────────────────────────────────────────
        Schema::create('stock_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // ── Articles ──────────────────────────────────────────────────────
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('reference', 60)->nullable();
            // Unité de gestion : kg, litre, pièce, carton…
            $table->string('unit', 20)->default('pièce');
            $table->text('description')->nullable();

            $table->decimal('current_stock', 12, 3)->default(0);
            // Seuil déclenchant l'alerte de réapprovisionnement.
            $table->decimal('min_stock', 12, 3)->default(0);

            // Coût moyen pondéré, recalculé à chaque réception. C'est lui qui
            // valorise le stock et les sorties, pas le dernier prix payé.
            $table->unsignedBigInteger('average_cost')->default(0);
            $table->unsignedBigInteger('last_purchase_price')->default(0);

            // Fournisseur habituel : pré-sélectionné à la création d'un bon.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
            $table->index(['name']);
        });

        // ── Bons de commande ──────────────────────────────────────────────
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            // draft | sent | partially_received | received | cancelled
            $table->string('status', 25)->default('draft');

            $table->date('expected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            // Trace de l'envoi : permet de savoir si le fournisseur a bien été
            // notifié, et de rejouer l'envoi en cas d'échec.
            $table->string('sent_to_email', 150)->nullable();
            $table->text('send_error')->nullable();

            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_ordered', 12, 3)->default(0);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->timestamps();
        });

        // ── Demandes des départements ─────────────────────────────────────
        Schema::create('stock_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            // hebergement | housekeeping | restaurant | boutique | autre
            $table->string('department', 30);
            // pending | approved | rejected | delivered | cancelled
            $table->string('status', 20)->default('pending');

            $table->text('purpose')->nullable();
            $table->text('review_notes')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['department']);
        });

        Schema::create('stock_requisition_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_requested', 12, 3)->default(0);
            // Servi peut différer du demandé : l'économe ajuste selon le stock.
            $table->decimal('quantity_issued', 12, 3)->default(0);
            $table->timestamps();
        });

        // ── Mouvements de stock ───────────────────────────────────────────
        // Journal unique de toutes les variations : c'est la source de vérité
        // qui permet de reconstituer un stock et d'auditer un écart.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            // in | out | adjustment
            $table->string('type', 15);
            // Positif pour une entrée, négatif pour une sortie.
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_after', 12, 3)->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);

            // Origine du mouvement, pour remonter au document source.
            $table->string('source_type', 30)->nullable();  // purchase_order | requisition | manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['stock_item_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_requisition_lines');
        Schema::dropIfExists('stock_requisitions');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('stock_categories');
        Schema::dropIfExists('suppliers');
    }
};
