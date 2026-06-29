<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_customer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('table_number', 10)->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();

            // pending, confirmed, preparing, ready, served, canceled
            $table->string('status', 30)->default('pending');

            // Montants en centimes FCFA
            $table->unsignedInteger('total_amount')->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->useCurrent();

            $table->timestamps();

            $table->index(['status', 'placed_at']);
            $table->index(['table_number', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_customer_orders');
    }
};

