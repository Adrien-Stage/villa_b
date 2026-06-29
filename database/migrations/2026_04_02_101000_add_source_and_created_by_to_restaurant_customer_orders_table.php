<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            // portal, staff
            $table->string('source', 20)->default('portal')->after('tenant_id');

            // User ID (serveur) si commande creee depuis l'espace staff
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();

            $table->index(['source', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropIndex(['source', 'placed_at']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['source', 'created_by']);
        });
    }
};

