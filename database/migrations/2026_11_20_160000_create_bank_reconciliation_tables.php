<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20);
            $table->string('reference', 80);
            $table->string('digest', 64);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();
            $table->unique(['account_code', 'reference']);
            $table->unique(['account_code', 'digest']);
        });
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->date('booked_on');
            $table->string('reference', 80);
            $table->string('label', 255);
            $table->bigInteger('amount');
            $table->foreignId('journal_entry_line_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bank_statement_id', 'position']);
        });
        Schema::create('bank_reconciliation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_entry_line_id')->constrained()->restrictOnDelete();
            $table->boolean('matched');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_events');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
