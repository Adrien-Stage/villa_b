<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('restrict');
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');

            // Numérotation légale : F-2025-000001
            // unique PAR tenant : chaque hôtel a sa propre séquence
            $table->string('invoice_number');
            $table->date('invoice_date');

            // Tous les montants en centimes FCFA
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->integer('balance_due')->default(0); // signé : peut être négatif (avoir)

            // 'draft', 'sent', 'paid', 'overdue', 'cancelled'
            $table->string('status', 30)->default('draft');

            $table->string('pdf_path')->nullable(); // Stockage MinIO/S3
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_email')->nullable();

            $table->text('legal_notes')->nullable();  // Mentions légales Cameroun
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            $table->unique(['invoice_number']);
            $table->index(['status']);
            $table->index(['invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};