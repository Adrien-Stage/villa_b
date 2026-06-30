<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_customer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_customer_order_id')->constrained()->cascadeOnDelete();

            // null = article libre non reference dans le menu
            $table->unsignedBigInteger('menu_item_id')->nullable();

            // Snapshot du nom/prix au moment de la commande
            $table->string('item_name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total_price');

            $table->text('special_requests')->nullable();

            $table->timestamps();

            $table->index(['restaurant_customer_order_id']);
            $table->index(['menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_customer_order_items');
    }
};

