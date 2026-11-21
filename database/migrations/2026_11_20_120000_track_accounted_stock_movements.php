<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_stock_movements', function (Blueprint $table) {
            $table->string('stream', 40);
            $table->unsignedBigInteger('movement_id');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->primary(['stream', 'movement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_stock_movements');
    }
};
