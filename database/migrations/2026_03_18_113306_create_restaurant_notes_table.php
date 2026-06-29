<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('restrict');

            // null = client externe sans réservation hôtel
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // 'open', 'closed', 'disputed'
            $table->string('status', 30)->default('open');

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            // Montants en centimes FCFA
            $table->unsignedInteger('total_amount')->default(0);
            $table->unsignedInteger('tip_amount')->default(0);

            $table->string('table_number', 10)->nullable();
            $table->string('server_name')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_notes');
    }
};