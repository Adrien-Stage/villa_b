<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_order_items', function (Blueprint $table) {
            $table->boolean('is_beverage')->nullable();
        });

        Schema::create('restaurant_bar_drinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_menu_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('format', 20);
            $table->json('ingredients');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('restaurant_bar_bottles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_store_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('capacity_ml');
            $table->unsignedInteger('remaining_ml');
            $table->unsignedBigInteger('opening_cost');
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('opening_key')->unique();
            $table->timestamps();
            $table->index(['service_store_id', 'stock_item_id', 'remaining_ml'], 'bar_bottles_availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_bar_bottles');
        Schema::dropIfExists('restaurant_bar_drinks');
        Schema::table('restaurant_customer_order_items', function (Blueprint $table) {
            $table->dropColumn('is_beverage');
        });
    }
};
