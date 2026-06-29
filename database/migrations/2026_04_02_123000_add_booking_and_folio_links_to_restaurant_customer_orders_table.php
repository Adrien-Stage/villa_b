<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('table_number')->constrained()->nullOnDelete();
            $table->foreignId('folio_item_id')->nullable()->after('booking_id')->constrained('folio_items')->nullOnDelete();

            $table->index(['booking_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'placed_at']);
            $table->dropForeign(['folio_item_id']);
            $table->dropColumn('folio_item_id');
            $table->dropForeign(['booking_id']);
            $table->dropColumn('booking_id');
        });
    }
};

