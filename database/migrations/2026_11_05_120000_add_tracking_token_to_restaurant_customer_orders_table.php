<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->index()->after('total_amount');
            $table->timestamp('tracking_token_expires_at')->nullable()->after('tracking_token');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropColumn(['tracking_token', 'tracking_token_expires_at']);
        });
    }
};
