<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Régimes horaires de travail configurables :
 * - Horaire fixe (ex. Administration 08:00–17:00 sur jours ouvrés choisis)
 * - Système de quarts / Jour-Nuit (ex. 08:00–15:00 / 15:00–22:00, ou Jour/Nuit)
 *
 * Chaque régime porte ses propres plages horaires (`work_shifts`), se rattache
 * à un ou plusieurs services (`departments.work_regime_id`), et peut être
 * surchargé individuellement sur un collaborateur (`users.work_regime_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_regimes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('mode', 20)->default('shifts'); // 'fixed' | 'shifts'
            $table->json('working_days')->nullable();       // ISO weekdays [1..7] pour mode fixed
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->foreignId('work_regime_id')
                ->nullable()
                ->after('id')
                ->constrained('work_regimes')
                ->cascadeOnDelete();

            $table->index(['work_regime_id', 'is_active']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('work_regime_id')
                ->nullable()
                ->after('is_active')
                ->constrained('work_regimes')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('work_regime_id')
                ->nullable()
                ->after('department_id')
                ->constrained('work_regimes')
                ->nullOnDelete();
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('assigned_by');
        });

        Schema::table('shift_weeks', function (Blueprint $table) {
            $table->json('autofilled_users')->nullable()->after('sent');
            $table->timestamp('autofilled_at')->nullable()->after('autofilled_users');
        });

        $this->seedInitialRegimes();
    }

    private function seedInitialRegimes(): void
    {
        if (DB::table('work_regimes')->exists()) {
            return;
        }

        $now = now();

        // 1. Régime par défaut historique : Système Jour / Nuit (07:00–19:00 / 19:00–07:00)
        $jourNuitId = DB::table('work_regimes')->insertGetId([
            'name'         => 'Système Jour / Nuit',
            'mode'         => 'shifts',
            'working_days' => null,
            'is_default'   => true,
            'is_active'    => true,
            'sort_order'   => 1,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // Rattacher les quarts historiques existants (Jour, Nuit…) au régime par défaut.
        DB::table('work_shifts')
            ->whereNull('work_regime_id')
            ->update(['work_regime_id' => $jourNuitId, 'updated_at' => $now]);

        // 2. Régime horaire fixe : Administration (08:00–17:00, lundi à vendredi)
        $adminId = DB::table('work_regimes')->insertGetId([
            'name'         => 'Horaire fixe — Administration',
            'mode'         => 'fixed',
            'working_days' => json_encode([1, 2, 3, 4, 5]),
            'is_default'   => false,
            'is_active'    => true,
            'sort_order'   => 2,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        DB::table('work_shifts')->insert([
            'work_regime_id' => $adminId,
            'name'           => 'Journée',
            'starts_at'      => '08:00',
            'ends_at'        => '17:00',
            'sort_order'     => 10,
            'is_active'      => true,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        // 3. Régime par quarts de journée (08:00–15:00 / 15:00–22:00), prêt à personnaliser
        $quartsJourneeId = DB::table('work_regimes')->insertGetId([
            'name'         => 'Système de quarts (08h–15h / 15h–22h)',
            'mode'         => 'shifts',
            'working_days' => null,
            'is_default'   => false,
            'is_active'    => true,
            'sort_order'   => 3,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        DB::table('work_shifts')->insert([
            [
                'work_regime_id' => $quartsJourneeId,
                'name'           => 'Quart Matin',
                'starts_at'      => '08:00',
                'ends_at'        => '15:00',
                'sort_order'     => 20,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'work_regime_id' => $quartsJourneeId,
                'name'           => 'Quart Après-midi',
                'starts_at'      => '15:00',
                'ends_at'        => '22:00',
                'sort_order'     => 21,
                'is_active'      => true,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ]);

        // Rattacher les départements connus à leur régime naturel s'ils existent déjà.
        if (Schema::hasTable('departments')) {
            DB::table('departments')
                ->whereNull('work_regime_id')
                ->whereIn('code', ['DIR', 'FIN', 'RH', 'IT', 'QLT'])
                ->update(['work_regime_id' => $adminId]);

            DB::table('departments')
                ->whereNull('work_regime_id')
                ->whereIn('code', ['REC', 'HSK', 'FNB', 'BTQ', 'TEC', 'ECO'])
                ->update(['work_regime_id' => $jourNuitId]);
        }
    }

    public function down(): void
    {
        Schema::table('shift_weeks', function (Blueprint $table) {
            $table->dropColumn(['autofilled_users', 'autofilled_at']);
        });

        Schema::table('shift_assignments', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_regime_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_regime_id');
        });

        // Nettoyer les plages ajoutées par cette migration si aucune affectation ne s'y attache
        if (Schema::hasTable('work_shifts') && Schema::hasTable('work_regimes')) {
            $regimesSupprimes = DB::table('work_regimes')
                ->whereIn('name', ['Horaire fixe — Administration', 'Système de quarts (08h–15h / 15h–22h)'])
                ->pluck('id');

            if ($regimesSupprimes->isNotEmpty()) {
                DB::table('work_shifts')
                    ->whereIn('work_regime_id', $regimesSupprimes)
                    ->delete();
            }
        }

        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropIndex(['work_regime_id', 'is_active']);
            $table->dropConstrainedForeignId('work_regime_id');
        });

        Schema::dropIfExists('work_regimes');
    }
};
