<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_note_id')->constrained()->onDelete('cascade');

            // null = article libre non référencé dans un menu
            $table->unsignedBigInteger('menu_item_id')->nullable();

            // Snapshot du nom au moment de la commande
            // car le menu peut changer après
            $table->string('item_name');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total_price');

            // 'pending', 'preparing', 'ready', 'served'
            $table->string('status', 30)->default('pending');

            $table->text('special_requests')->nullable(); // "sans oignon", "bien cuit"

            $table->timestamp('served_at')->nullable();
            $table->unsignedBigInteger('served_by')->nullable();
            $table->foreign('served_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['restaurant_note_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
    }
};