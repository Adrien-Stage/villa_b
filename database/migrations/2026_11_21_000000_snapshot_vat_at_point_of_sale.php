<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SALES = ['restaurant_customer_orders', 'shop_orders', 'minibar_checks', 'reception_sales',
        'restaurant_buffet_entries', 'restaurant_banquets'];

    public function up(): void
    {
        foreach (self::SALES as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->json('vat_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        foreach (self::SALES as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('vat_snapshot'));
        }
    }
};
