<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Origine et échéance des écarts au catalogue des droits.
 *
 * Deux autorités posent des écarts : la console d'orchestration, pour tous
 * ses établissements, et l'établissement lui-même. Les distinguer permet à
 * la console de remplacer les siens sans effacer ceux de l'hôtel. L'échéance
 * borne une exception dans le temps : un remplacement de congés ne doit pas
 * survivre au retour du titulaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_grants', function (Blueprint $table) {
            $table->string('origin', 16)->default('erp')->after('scope');
            $table->timestamp('expires_at')->nullable()->after('reason');
        });

        // Un même droit peut être posé sur un même sujet par chacune des deux
        // autorités : l'unicité se tient désormais par couche. Sans cela, la
        // console ne pourrait plus envoyer un écart que l'hôtel a déjà posé.
        Schema::table('permission_grants', function (Blueprint $table) {
            $table->dropUnique('permission_grants_sujet_droit_unique');
            $table->unique(['subject_type', 'subject_id', 'permission', 'origin'], 'permission_grants_sujet_droit_origine_unique');
        });

        // Les écarts nominatifs n'ont jamais été posés par la console : ce
        // sont les dérogations de l'établissement.
        DB::table('permission_grants')->where('subject_type', 'user')->update(['origin' => 'etablissement']);
    }

    public function down(): void
    {
        // Un même droit posé dans les deux couches violerait l'ancienne
        // unicité : la couche de l'établissement cède la place à la console.
        $doublons = DB::table('permission_grants as e')
            ->join('permission_grants as c', function ($join) {
                $join->on('c.subject_type', '=', 'e.subject_type')
                    ->on('c.subject_id', '=', 'e.subject_id')
                    ->on('c.permission', '=', 'e.permission');
            })
            ->where('e.origin', 'etablissement')
            ->where('c.origin', 'erp')
            ->pluck('e.id');
        DB::table('permission_grants')->whereIn('id', $doublons)->delete();

        Schema::table('permission_grants', function (Blueprint $table) {
            $table->dropUnique('permission_grants_sujet_droit_origine_unique');
            $table->unique(['subject_type', 'subject_id', 'permission'], 'permission_grants_sujet_droit_unique');
            $table->dropColumn(['origin', 'expires_at']);
        });
    }
};
