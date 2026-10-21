<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minibar_movements', function (Blueprint $table) {
            $table->string('notes', 500)->nullable()->after('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('minibar_movements', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
