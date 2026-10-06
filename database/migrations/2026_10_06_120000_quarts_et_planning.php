<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quarts de travail et planning du personnel.
 *
 * L'hôtel tourne vingt-quatre heures sur vingt-quatre : la direction définit
 * ses quarts (jour, nuit…) et leurs heures, pour tout l'établissement ;
 * chaque chef de service y répartit son personnel, semaine par semaine,
 * puis envoie le planning — chacun est prévenu de ses quarts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->time('starts_at');
            // Une fin plus tôt que le début : le quart finit le lendemain (nuit).
            $table->time('ends_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->restrictOnDelete();
            // Le service où la personne est planifiée : celui de son chef.
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            // Jour où le quart commence ; un quart de nuit finit le lendemain.
            $table->date('date');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date', 'work_shift_id'], 'shift_assignments_personne_jour_quart_unique');
            $table->index(['department_id', 'date']);
        });

        Schema::create('shift_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            // Lundi de la semaine.
            $table->date('week_start');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            // Ce que chacun a reçu au dernier envoi : on ne prévient à nouveau
            // que ceux dont les quarts ont changé.
            $table->json('sent')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'week_start']);
        });

        // Les deux quarts d'un hôtel ouvert jour et nuit ; la direction les ajuste.
        DB::table('work_shifts')->insert([
            ['name' => 'Jour', 'starts_at' => '07:00', 'ends_at' => '19:00', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nuit', 'starts_at' => '19:00', 'ends_at' => '07:00', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_weeks');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('work_shifts');
    }
};
