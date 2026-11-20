<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('night_audits', function (Blueprint $table) {
            $table->json('daily_report')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('night_audits', function (Blueprint $table) {
            $table->dropColumn('daily_report');
        });
    }
};
