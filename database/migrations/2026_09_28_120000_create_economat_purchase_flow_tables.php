<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 : Cycle Achats Économat
 * - Demandes d'achat internes (Purchase Requests) & validation hiérarchique
 * - Bons de réception (Goods Receipts) & contrôle contradictoire (livré, accepté, refusé)
 * - Liaison Bon de commande vers Demande d'achat
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Demandes d'achat internes (Purchase Requests) ─────────────────────
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            // Département demandeur : restaurant, cuisine, bar, housekeeping, maintenance, economat, direction, etc.
            $table->string('department', 50)->default('economat');
            // Priorité : low, normal, urgent
            $table->string('priority', 20)->default('normal');
            // Statut : pending, approved, rejected, converted, cancelled
            $table->string('status', 25)->default('pending');

            $table->text('purpose')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('review_notes')->nullable();

            // Montant estimé en centimes FCFA
            $table->unsignedBigInteger('total_estimated_amount')->default(0);

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['department']);
            $table->index(['priority']);
        });

        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_requested', 12, 3)->default(0);
            $table->unsignedBigInteger('estimated_unit_price')->default(0); // centimes FCFA
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        // ── Liaison Bon de commande vers Demande d'achat ──────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')->nullable()->after('supplier_id')->constrained('purchase_requests')->nullOnDelete();
        });

        // ── Bons de réception & Contrôle qualité/quantité (Goods Receipts) ─────
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique(); // BR-YYYY-XXXX
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            // N° du bordereau / bon de livraison papier apporté par le fournisseur
            $table->string('delivery_note_number', 80)->nullable();
            $table->timestamp('received_at');
            // Statut : received, cancelled
            $table->string('status', 25)->default('received');
            // Montant total accepté en centimes FCFA
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('notes')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['received_at']);
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();

            $table->decimal('quantity_ordered', 12, 3)->default(0);
            $table->decimal('quantity_delivered', 12, 3)->default(0);
            $table->decimal('quantity_accepted', 12, 3)->default(0);
            $table->decimal('quantity_rejected', 12, 3)->default(0);

            // Motif de refus si litige : damaged, spoilage, wrong_item, expired, packaging, other
            $table->string('rejection_reason', 100)->nullable();

            $table->unsignedBigInteger('unit_cost')->default(0);  // centimes FCFA
            $table->unsignedBigInteger('total_cost')->default(0); // centimes FCFA (quantité acceptée * unit_cost)
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn('purchase_request_id');
        });

        Schema::dropIfExists('purchase_request_lines');
        Schema::dropIfExists('purchase_requests');
    }
};
