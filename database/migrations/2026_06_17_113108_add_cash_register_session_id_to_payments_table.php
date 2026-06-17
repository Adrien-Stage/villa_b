<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->constrained('cash_register_sessions')->nullOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['cash_register_session_id']);
            $table->dropColumn('cash_register_session_id');
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
        });

        Schema::table('folio_items', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
        });
    }
};
