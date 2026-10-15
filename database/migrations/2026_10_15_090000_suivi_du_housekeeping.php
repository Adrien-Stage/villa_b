<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi du housekeeping : qui a fait quoi, dans quelle chambre, à quelle heure.
 *
 * L'affectation garde les personnes de chaque étape — qui a commencé, qui a
 * terminé, qui a contrôlé, qui a remis la chambre en vente — et le journal
 * consigne chaque geste, y compris ceux qui ne changent pas le statut de la
 * chambre (recouche d'une chambre occupée, relevé du mini-bar…).
 *
 * Une affectation est désormais de deux sortes : le ménage de départ, qui suit
 * le cycle À nettoyer → Nettoyée → Contrôlée, et la recouche d'une chambre
 * occupée, que le client garde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('housekeeping_assignments', function (Blueprint $table) {
            $table->string('kind', 15)->default('depart')->after('room_id');
            $table->foreignId('started_by')->nullable()->after('started_at')->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
        });

        Schema::create('housekeeping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('housekeeping_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('housekeeping_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('details', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['room_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_logs');

        Schema::table('housekeeping_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('started_by');
            $table->dropConstrainedForeignId('completed_by');
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn(['kind', 'inspected_at', 'released_at']);
        });
    }
};
