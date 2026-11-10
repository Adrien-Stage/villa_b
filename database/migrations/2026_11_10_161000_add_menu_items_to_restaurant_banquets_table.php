<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_banquets')) {
            return;
        }

        Schema::table('restaurant_banquets', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_banquets', 'menu_items')) {
                $table->json('menu_items')->nullable()->after('menu');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurant_banquets')) {
            return;
        }

        Schema::table('restaurant_banquets', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_banquets', 'menu_items')) {
                $table->dropColumn('menu_items');
            }
        });
    }
};
