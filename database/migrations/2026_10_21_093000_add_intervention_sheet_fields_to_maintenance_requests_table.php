<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->text('diagnostic')->nullable()->after('resolution');
            $table->text('travaux_realises')->nullable()->after('diagnostic');
            $table->string('resultat', 50)->nullable()->default('resolu')->after('travaux_realises');
            $table->text('recommandations')->nullable()->after('resultat');
            $table->foreignId('fiche_soumise_by')->nullable()->after('recommandations')->constrained('users')->nullOnDelete();
            $table->timestamp('fiche_soumise_at')->nullable()->after('fiche_soumise_by');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropForeign(['fiche_soumise_by']);
            $table->dropColumn([
                'diagnostic',
                'travaux_realises',
                'resultat',
                'recommandations',
                'fiche_soumise_by',
                'fiche_soumise_at',
            ]);
        });
    }
};
