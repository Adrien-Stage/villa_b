<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_banquets', fn (Blueprint $table) => $table->timestamp('realized_at')->nullable()->index());
        Schema::create('ledger_analytic_centers', function (Blueprint $table) {
            $table->string('code', 40)->primary();
            $table->string('label', 100);
            $table->timestamps();
        });
        DB::table('accounts')->insertOrIgnore([
            'code' => '925000', 'label' => 'Coûts — autres centres', 'account_class' => 9,
            'is_postable' => true, 'is_active' => true, 'is_collective' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_analytic_centers');
        Schema::table('restaurant_banquets', function (Blueprint $table) {
            $table->dropIndex(['realized_at']);
            $table->dropColumn('realized_at');
        });
    }
};
