<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('subtotal')->default(0); // en centimes
            $table->integer('tax_amount')->default(0); // en centimes
            $table->integer('total_amount')->default(0); // en centimes
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'mobile_money', 'card', 'room_charge', 'other'])->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('customer_id');
            $table->index('payment_status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
