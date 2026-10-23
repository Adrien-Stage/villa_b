<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un tarif week-end, long séjour ou dernière minute vaut toute l'année : sa
 * période devient facultative. Sans dates, le tarif s'applique tant qu'il est
 * actif ; une saison ou un événement garde les siennes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_rates', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Une règle sans dates n'a pas d'équivalent dans l'ancien schéma :
        // elle reçoit l'année en cours, la plus proche de « toute l'année ».
        \Illuminate\Support\Facades\DB::table('room_rates')->whereNull('start_date')->update(['start_date' => now()->startOfYear()->toDateString()]);
        \Illuminate\Support\Facades\DB::table('room_rates')->whereNull('end_date')->update(['end_date' => now()->endOfYear()->toDateString()]);

        Schema::table('room_rates', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
