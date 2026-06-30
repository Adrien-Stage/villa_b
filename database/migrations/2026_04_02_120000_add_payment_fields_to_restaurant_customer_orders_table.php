<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            // unpaid, paid, refunded
            $table->string('payment_status', 20)->default('unpaid')->after('status');

            // cash, mobile_money, card, room_charge, other
            $table->string('payment_method', 30)->nullable()->after('payment_status');

            // Montants en centimes FCFA
            $table->unsignedInteger('amount_paid')->default(0)->after('total_amount');

            $table->timestamp('paid_at')->nullable()->after('placed_at');
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();

            $table->index(['payment_status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'paid_at']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'payment_status',
                'payment_method',
                'amount_paid',
                'paid_at',
                'paid_by',
            ]);
        });
    }
};

