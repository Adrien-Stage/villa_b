<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('code');
            $table->string('tax_id', 60)->nullable()->after('category'); // NIF / NIU
            $table->string('rccm', 60)->nullable()->after('tax_id');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('payment_terms', 60)->nullable()->after('city');
            $table->string('payment_method', 60)->nullable()->after('payment_terms');
            $table->unsignedInteger('delivery_lead_time_days')->nullable()->after('payment_method');
            $table->string('bank_details', 255)->nullable()->after('delivery_lead_time_days');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'tax_id',
                'rccm',
                'city',
                'payment_terms',
                'payment_method',
                'delivery_lead_time_days',
                'bank_details',
            ]);
        });
    }
};
