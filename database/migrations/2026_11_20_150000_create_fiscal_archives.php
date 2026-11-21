<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->unique()->constrained()->restrictOnDelete();
            $table->string('regime', 10);
            $table->string('sha256', 64);
            $table->longText('payload');
            $table->date('retain_until');
            $table->unsignedBigInteger('sealed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_archives');
    }
};
