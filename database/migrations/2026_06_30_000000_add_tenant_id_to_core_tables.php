<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users', 'room_types', 'rooms', 'bookings', 'customers',
            'group_bookings', 'roles', 'cash_register_sessions',
            'shop_products', 'housekeeping_teams', 'invoices', 'payments',
        ];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            });

            if ($table !== 'users') {
                try {
                    Schema::table($table, function (Blueprint $t) {
                        $t->index('tenant_id');
                    });
                } catch (\Exception) {
                    // l'index existe peut-etre deja
                }
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'payments', 'invoices', 'housekeeping_teams', 'shop_products',
            'cash_register_sessions', 'roles', 'group_bookings', 'customers',
            'bookings', 'rooms', 'room_types', 'users',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};
