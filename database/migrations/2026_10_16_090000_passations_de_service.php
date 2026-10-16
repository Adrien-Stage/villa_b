<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passations de service : à la fin de son quart, le réceptionniste remet le
 * tiroir à celui qui lui succède, avec ses consignes. Celui-ci prend
 * connaissance de ce qui a été fait, recompte le tiroir et ouvre sa caisse
 * avec ce montant.
 *
 * Chacun garde sa session de caisse : la passation relie la session qui se
 * termine à celle qui commence. Prévue pour l'hébergement, la table porte un
 * module pour les autres services.
 *
 * Montants en centimes FCFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20)->default('reception');
            $table->string('status', 15)->default('pending');

            // Qui remet, et quoi.
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('from_session_id')->constrained('cash_register_sessions')->restrictOnDelete();
            $table->unsignedBigInteger('handed_amount');
            $table->text('instructions')->nullable();
            $table->timestamp('handed_at');

            // Qui le planning attendait au quart suivant.
            $table->foreignId('planned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('planned_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();

            // Qui a repris, ce qu'il a compté.
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_session_id')->nullable()->constrained('cash_register_sessions')->nullOnDelete();
            $table->unsignedBigInteger('received_amount')->nullable();
            $table->bigInteger('discrepancy_amount')->nullable();
            $table->text('reception_notes')->nullable();
            $table->timestamp('taken_at')->nullable();

            $table->timestamps();

            $table->index(['module', 'status']);
            $table->index('handed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_handovers');
    }
};
