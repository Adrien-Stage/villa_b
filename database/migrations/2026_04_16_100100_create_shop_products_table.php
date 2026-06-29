<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_category_id')->constrained('shop_categories')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->nullable();
            $table->integer('price'); // en centimes FCFA
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('shop_category_id');
            $table->unique(['sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_products');
    }
};
