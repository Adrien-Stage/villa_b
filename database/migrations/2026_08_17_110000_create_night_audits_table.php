<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clôture journalière (night audit).
 *
 * Trois rôles en une opération : comptabiliser les écritures du jour, figer
 * le chiffre d'affaires constaté, et interdire toute écriture ultérieure sur
 * cette journée. Un mouvement arrivé après la clôture ne se glisse plus dans
 * une journée close — il passe par une écriture datée du jour de sa
 * découverte, ce qui laisse une trace.
 *
 * Les totaux sont figés dans la table plutôt que recalculés : le night audit
 * est un constat daté, il doit rester identique même si une correction
 * ultérieure vient modifier le grand livre.
 *
 * Montants en centimes FCFA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('night_audits', function (Blueprint $table) {
            $table->id();

            // Une seule clôture par journée.
            $table->date('audit_date')->unique();

            $table->timestamp('closed_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // Chiffre d'affaires constaté, par activité.
            $table->unsignedBigInteger('revenue_accommodation')->default(0);
            $table->unsignedBigInteger('revenue_restaurant')->default(0);
            $table->unsignedBigInteger('revenue_shop')->default(0);
            $table->unsignedBigInteger('revenue_total')->default(0);

            // Trésorerie encaissée et écarts de caisse constatés le jour même.
            $table->unsignedBigInteger('cash_collected')->default(0);
            $table->bigInteger('cash_discrepancy')->default(0);
            $table->unsignedSmallInteger('registers_closed')->default(0);
            $table->unsignedSmallInteger('registers_left_open')->default(0);

            // Nombre d'écritures produites par la passe.
            $table->unsignedSmallInteger('entries_posted')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('audit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_audits');
    }
};
