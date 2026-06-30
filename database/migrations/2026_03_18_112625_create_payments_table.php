<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('restrict');

            // null = paiement restaurant sans réservation hôtel
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Positif = encaissement, négatif = remboursement
            $table->integer('amount');
            $table->string('currency', 3)->default('XAF');

            // 'stripe', 'orange_money', 'mtn_momo', 'cash', 'bank_transfer', 'check'
            $table->string('method', 30);
            // 'pending', 'completed', 'failed', 'refunded', 'disputed'
            $table->string('status', 30)->default('pending');

            // Références pour la traçabilité bancaire
            $table->string('reference')->unique();        // PAY-2025-000001 (interne)
            $table->string('external_reference')->nullable(); // ID Stripe / OM / MTN
            $table->string('external_receipt_url')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();

            $table->unsignedBigInteger('processed_by')->nullable();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->json('gateway_response')->nullable(); // Réponse brute API opérateur

            $table->timestamps();

            $table->index(['status']);
            $table->index(['booking_id']);
            $table->index(['paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};