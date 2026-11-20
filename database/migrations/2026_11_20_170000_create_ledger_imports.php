<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_imports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('reference', 60);
            $table->string('digest', 64);
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'reference']);
            $table->unique(['kind', 'digest']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_imports');
    }
};
