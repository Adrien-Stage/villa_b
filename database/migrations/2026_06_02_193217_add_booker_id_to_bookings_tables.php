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
        Schema::table('bookings', function (Blueprint $table) {
            // Le "booker" (mandataire)
            $table->unsignedBigInteger('booker_id')->nullable()->after('customer_id');
            $table->foreign('booker_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::table('group_bookings', function (Blueprint $table) {
            // Le "booker" (mandataire) pour le groupe, distinct du "contact" du groupe si nécessaire
            $table->unsignedBigInteger('booker_id')->nullable()->after('contact_customer_id');
            $table->foreign('booker_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['booker_id']);
            $table->dropColumn('booker_id');
        });

        Schema::table('group_bookings', function (Blueprint $table) {
            $table->dropForeign(['booker_id']);
            $table->dropColumn('booker_id');
        });
    }
};
