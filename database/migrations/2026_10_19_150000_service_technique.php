<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service technique (module « technique ») : les demandes d'intervention de
 * maintenance, leur historique, et les spécialités de chaque technicien.
 *
 * Une demande vient d'un service (ou du housekeeping qui signale un problème
 * en chambre), est affectée à un technicien ou prise par lui, puis terminée
 * avec un compte rendu ; le demandeur peut la rouvrir quelques jours. Les
 * pièces employées sortent du dépôt technique (service_store_movements,
 * source « intervention »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('status', 20)->default('open');
            $table->string('priority', 10)->default('normal');
            $table->string('specialty', 30)->default('autre');

            // Où : une chambre, ou un lieu décrit (hall, cuisine, piscine…).
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->string('title', 150);
            $table->text('description')->nullable();

            // Qui demande, pour quel service, d'où.
            $table->string('department', 30);
            $table->string('source', 20)->default('manual');
            $table->foreignId('housekeeping_assignment_id')->nullable()->constrained('housekeeping_assignments')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            // La chambre a été rendue indisponible pour l'intervention.
            $table->boolean('room_blocked')->default(false);

            // Qui intervient.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();

            // Fin, compte rendu, réouverture, annulation.
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('assigned_to');
            $table->index('specialty');
        });

        Schema::create('maintenance_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['maintenance_request_id', 'created_at']);
        });

        Schema::create('technician_specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('specialty', 30);
            $table->timestamps();

            $table->unique(['user_id', 'specialty']);
        });

        // Le département, pour le planning des quarts et le rattachement des comptes.
        if (Schema::hasTable('departments') && !DB::table('departments')->where('slug', 'technique_maintenance')->exists()) {
            $id = DB::table('departments')->insertGetId([
                'code'        => 'TEC',
                'slug'        => 'technique_maintenance',
                'name'        => 'Technique / Maintenance',
                'description' => 'Interventions de maintenance de l’hôtel : plomberie, électricité courant fort et courant faible, froid, climatisation et ventilation.',
                'icon'        => 'wrench',
                'accent'      => 'orange',
                'sort_order'  => 10,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            if (Schema::hasTable('department_module')) {
                foreach (['technique', 'economat', 'discussions'] as $module) {
                    DB::table('department_module')->insert([
                        'department_id' => $id,
                        'module_key'    => $module,
                        'default_level' => 'write',
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_specialties');
        Schema::dropIfExists('maintenance_request_events');
        Schema::dropIfExists('maintenance_requests');

        if (Schema::hasTable('departments')) {
            DB::table('departments')->where('slug', 'technique_maintenance')->delete();
        }
    }
};
