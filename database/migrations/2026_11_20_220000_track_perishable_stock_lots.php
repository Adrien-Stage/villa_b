<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_line_id')->unique()->constrained()->restrictOnDelete();
            $table->string('lot_number', 80);
            $table->date('expires_on');
            $table->decimal('quantity_remaining', 15, 3);
            $table->timestamps();
            $table->index(['stock_item_id', 'expires_on']);
        });
        foreach (['external_issue_lines', 'stock_transfer_lines'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) =>
                $table->foreignId('stock_movement_id')->nullable()->constrained()->nullOnDelete());
        }
    }

    public function down(): void
    {
        foreach (['external_issue_lines', 'stock_transfer_lines'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('stock_movement_id'));
        }
        Schema::dropIfExists('stock_lots');
    }
};
