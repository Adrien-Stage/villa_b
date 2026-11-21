<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_exchange_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('original_minor');
            $table->unsignedTinyInteger('minor_digits');
            $table->string('xaf_rate', 24);
            $table->date('rate_on');
            $table->unsignedBigInteger('amount_xaf_centimes');
            $table->string('reference', 120);
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_exchange_evidence');
    }
};
