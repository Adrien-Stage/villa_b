<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le motif « Autre » d'une sortie hors établissement se précise : l'économe
 * écrit pourquoi le matériel part, et le bon l'imprime à la place de « Autre ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_issues', function (Blueprint $table) {
            $table->string('reason_other', 160)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('external_issues', function (Blueprint $table) {
            $table->dropColumn('reason_other');
        });
    }
};
