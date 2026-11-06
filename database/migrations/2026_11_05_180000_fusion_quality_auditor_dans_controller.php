<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1 : Fusion du rôle 'quality_auditor' dans 'controller'.
 *
 * Le rôle 'quality_auditor' est un doublon du contrôle. Ses prérogatives
 * (audit hygiène, qualité, conformité, inspection des prestations) sont
 * intégrées au rôle unique de Contrôleur de gestion ('controller').
 *
 * Cette migration réaffecte tout utilisateur détenant 'quality_auditor' vers
 * 'controller' de façon idempotente et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $qualityRole = DB::table('roles')->where('slug', 'quality_auditor')->first();
        $controllerRole = DB::table('roles')->where('slug', 'controller')->first();

        if (! $qualityRole || ! $controllerRole) {
            return;
        }

        $now = now();
        $reaffectes = 0;

        // 1. Réaffectation des utilisateurs dans le pivot role_user
        if (Schema::hasTable('role_user')) {
            $affectationsQuality = DB::table('role_user')
                ->where('role_id', $qualityRole->id)
                ->get();

            foreach ($affectationsQuality as $aff) {
                $dejaController = DB::table('role_user')
                    ->where('user_id', $aff->user_id)
                    ->where('role_id', $controllerRole->id)
                    ->exists();

                if (! $dejaController) {
                    DB::table('role_user')->insert([
                        'user_id'    => $aff->user_id,
                        'role_id'    => $controllerRole->id,
                        'level'      => $aff->level ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $reaffectes++;
                }

                // Détacher l'ancien rôle quality_auditor
                DB::table('role_user')->where('id', $aff->id)->delete();
            }
        }

        // 2. Réalignement de la colonne héritée users.role si elle existe encore
        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->where('role', 'quality_auditor')
                ->update(['role' => 'controller']);
        }

        // 3. Migration des dérogations / surcharges de permissions (permission_grants)
        if (Schema::hasTable('permission_grants')) {
            $grants = DB::table('permission_grants')
                ->where('subject_type', 'role')
                ->where('subject_id', 'quality_auditor')
                ->get();

            foreach ($grants as $grant) {
                $conflit = DB::table('permission_grants')
                    ->where('subject_type', 'role')
                    ->where('subject_id', 'controller')
                    ->where('permission', $grant->permission)
                    ->exists();

                if (! $conflit) {
                    DB::table('permission_grants')
                        ->where('id', $grant->id)
                        ->update(['subject_id' => 'controller', 'updated_at' => $now]);
                } else {
                    DB::table('permission_grants')->where('id', $grant->id)->delete();
                }
            }
        }

        // 4. Trace dans le journal d'audit si des comptes ont été migrés
        if ($reaffectes > 0 && Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'event_type' => 'roles_fusion',
                'action'     => "Lot 1 : Fusion de 'quality_auditor' dans 'controller' ({$reaffectes} utilisateur(s) réaffecté(s))",
                'module'     => 'security',
                'payload'    => json_encode([
                    'from' => 'quality_auditor',
                    'to'   => 'controller',
                    'count' => $reaffectes,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // En cas de rollback, les contrôleurs de gestion conservent leur rôle.
        // On ne recrée pas l'ambiguïté du doublon 'quality_auditor'.
    }
};
