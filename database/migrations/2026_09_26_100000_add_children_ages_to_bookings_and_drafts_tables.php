<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookings') && !Schema::hasColumn('bookings', 'children_ages')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->json('children_ages')->nullable()->after('children_count');
            });
        }

        if (Schema::hasTable('booking_drafts') && !Schema::hasColumn('booking_drafts', 'children_ages')) {
            Schema::table('booking_drafts', function (Blueprint $table) {
                $table->json('children_ages')->nullable()->after('children');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'children_ages')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('children_ages');
            });
        }

        if (Schema::hasTable('booking_drafts') && Schema::hasColumn('booking_drafts', 'children_ages')) {
            Schema::table('booking_drafts', function (Blueprint $table) {
                $table->dropColumn('children_ages');
            });
        }
    }
};
