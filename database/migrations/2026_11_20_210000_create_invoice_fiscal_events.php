<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_fiscal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('status', 15);
            $table->string('issuer_niu', 60)->nullable();
            $table->string('authority_reference', 120)->nullable();
            $table->string('digest', 64);
            $table->longText('evidence');
            $table->text('observations')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_fiscal_events');
    }
};
