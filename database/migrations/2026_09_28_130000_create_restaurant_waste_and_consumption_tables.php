<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration Phase 4 : Restaurant & Garde-Manger
 * - Fiches de gaspillage / pertes / déchets / offerts / repas personnel (restaurant_waste_logs)
 * - Traçabilité du type de commande (standard, breakfast_included, complimentary, staff_meal)
 * - Liaison entre les mouvements de stock et les déclarations de pertes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('restaurant_waste_logs')) {
            Schema::create('restaurant_waste_logs', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 50)->unique();
                $table->foreignId('restaurant_pantry_item_id')
                    ->constrained('restaurant_pantry_items')
                    ->cascadeOnDelete();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_cost', 12, 4)->default(0); // en centimes FCFA
                $table->unsignedBigInteger('total_cost')->default(0); // en centimes FCFA
                // Motif normalisé : spoilage, burnt, breakage, staff_meal, complimentary, buffet_surplus, internal_consumption, return, other
                $table->string('reason', 40)->default('other');
                $table->string('department', 50)->default('cuisine');
                $table->string('responsible_person', 120)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->constrained('tenants')
                    ->nullOnDelete();
                $table->timestamp('occurred_at')->useCurrent();
                $table->timestamps();

                $table->index(['occurred_at']);
                $table->index(['reason', 'occurred_at']);
                $table->index(['restaurant_pantry_item_id', 'occurred_at']);
                $table->index(['department', 'occurred_at']);
            });
        }

        if (Schema::hasTable('restaurant_pantry_movements') && !Schema::hasColumn('restaurant_pantry_movements', 'restaurant_waste_log_id')) {
            Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
                $table->foreignId('restaurant_waste_log_id')
                    ->nullable()
                    ->after('stock_requisition_id')
                    ->constrained('restaurant_waste_logs')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('restaurant_customer_orders')) {
            Schema::table('restaurant_customer_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('restaurant_customer_orders', 'order_type')) {
                    $table->string('order_type', 40)->default('standard')->after('table_number');
                    $table->index(['order_type', 'placed_at']);
                }
                if (!Schema::hasColumn('restaurant_customer_orders', 'is_complimentary')) {
                    $table->boolean('is_complimentary')->default(false)->after('order_type');
                    $table->index(['is_complimentary']);
                }
                if (!Schema::hasColumn('restaurant_customer_orders', 'complimentary_reason')) {
                    $table->string('complimentary_reason', 255)->nullable()->after('is_complimentary');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurant_customer_orders')) {
            Schema::table('restaurant_customer_orders', function (Blueprint $table) {
                if (Schema::hasColumn('restaurant_customer_orders', 'order_type')) {
                    $table->dropIndex(['order_type', 'placed_at']);
                    $table->dropColumn('order_type');
                }
                if (Schema::hasColumn('restaurant_customer_orders', 'is_complimentary')) {
                    $table->dropIndex(['is_complimentary']);
                    $table->dropColumn('is_complimentary');
                }
                if (Schema::hasColumn('restaurant_customer_orders', 'complimentary_reason')) {
                    $table->dropColumn('complimentary_reason');
                }
            });
        }

        if (Schema::hasTable('restaurant_pantry_movements') && Schema::hasColumn('restaurant_pantry_movements', 'restaurant_waste_log_id')) {
            Schema::table('restaurant_pantry_movements', function (Blueprint $table) {
                $table->dropConstrainedForeignId('restaurant_waste_log_id');
            });
        }

        Schema::dropIfExists('restaurant_waste_logs');
    }
};
