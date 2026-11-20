<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_reconciliation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained('journal_entry_lines')->restrictOnDelete();
            $table->string('code', 20);
            $table->boolean('matched');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['line_id', 'recorded_at']);
        });

        DB::statement("INSERT INTO ledger_reconciliation_events (line_id, code, matched, recorded_at)
            SELECT id, reconciliation_code, TRUE, COALESCE(reconciled_at, updated_at, CURRENT_TIMESTAMP)
            FROM journal_entry_lines WHERE reconciliation_code IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_reconciliation_events');
    }
};
