<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folio_items', function (Blueprint $table) {
            $table->string('vat_rate_code', 20)->nullable();
            $table->string('vat_reason', 180)->nullable();
        });
        Schema::table('invoice_items', fn (Blueprint $table) => $table->string('vat_reason', 180)->nullable());
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->date('effective_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('legal_reference', 180)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('folio_items', fn (Blueprint $table) => $table->dropColumn(['vat_rate_code', 'vat_reason']));
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('vat_reason'));
        Schema::table('tax_rates', fn (Blueprint $table) => $table->dropColumn(['effective_on', 'ends_on', 'legal_reference']));
    }
};
