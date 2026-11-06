<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['stock_counts', 'service_store_counts', 'restaurant_stock_counts'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn($tableName, 'submitted_by')) {
                    $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete()->after('submitted_at');
                }
                if (!Schema::hasColumn($tableName, 'validated_at')) {
                    $table->timestamp('validated_at')->nullable()->after('closed_at');
                }
                if (!Schema::hasColumn($tableName, 'validated_by')) {
                    $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete()->after('validated_at');
                }
                if (!Schema::hasColumn($tableName, 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('validated_by');
                }
                if (!Schema::hasColumn($tableName, 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete()->after('rejected_at');
                }
                if (!Schema::hasColumn($tableName, 'rejection_reason')) {
                    $table->string('rejection_reason', 500)->nullable()->after('rejected_by');
                }
                if (!Schema::hasColumn($tableName, 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('rejection_reason');
                }
                if (!Schema::hasColumn($tableName, 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
                }
                if (!Schema::hasColumn($tableName, 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['stock_counts', 'service_store_counts', 'restaurant_stock_counts'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'cancellation_reason',
                    'cancelled_by',
                    'cancelled_at',
                    'rejection_reason',
                    'rejected_by',
                    'rejected_at',
                    'validated_by',
                    'validated_at',
                    'submitted_by',
                    'submitted_at',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
