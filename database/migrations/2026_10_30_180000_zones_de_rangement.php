<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les zones de rangement de l'économat : cave, épicerie, chambres froides,
 * magasin central… Chaque article a la sienne — un seul stock par article,
 * la zone dit où le trouver. Un inventaire peut se limiter à une zone.
 *
 * Les articles existants rejoignent le magasin central.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $central = DB::table('storage_zones')->insertGetId([
            'name' => 'Magasin central', 'sort_order' => 0, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('storage_zone_id')->nullable()->after('stock_category_id')
                ->constrained('storage_zones')->nullOnDelete();
        });

        DB::table('stock_items')->update(['storage_zone_id' => $central]);

        Schema::table('stock_counts', function (Blueprint $table) {
            $table->foreignId('storage_zone_id')->nullable()->after('stock_category_id')
                ->constrained('storage_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_counts', fn (Blueprint $table) => $table->dropConstrainedForeignId('storage_zone_id'));
        Schema::table('stock_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('storage_zone_id'));
        Schema::dropIfExists('storage_zones');
    }
};
