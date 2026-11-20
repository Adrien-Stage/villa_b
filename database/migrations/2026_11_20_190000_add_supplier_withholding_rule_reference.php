<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->string('withholding_legal_reference', 180)->nullable());
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropColumn('withholding_legal_reference'));
    }
};
