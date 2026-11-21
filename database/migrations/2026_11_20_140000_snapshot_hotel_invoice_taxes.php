<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bookings', 'invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tourist_tax_amount')->default(0);
            });
        }
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('tax_rate_code', 20)->nullable();
            $table->bigInteger('tax_surtax_amount')->default(0);
        });

        DB::table('tourist_tax_brackets')->where('classification', '3')->where('amount_per_night', 200_000)
            ->update(['amount_per_night' => 300_000]);
        DB::table('tourist_tax_brackets')->where('classification', '4')->where('amount_per_night', 300_000)
            ->update(['amount_per_night' => 400_000]);
        DB::table('tourist_tax_brackets')->insertOrIgnore([
            'classification' => 'hebergement_meuble',
            'label' => 'Logement meublé ou gîte',
            'amount_per_night' => 200_000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn(['tax_rate_code', 'tax_surtax_amount']));
        foreach (['bookings', 'invoices'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('tourist_tax_amount'));
        }
    }
};
