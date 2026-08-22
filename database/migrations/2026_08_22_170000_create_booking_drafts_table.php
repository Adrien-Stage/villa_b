<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de sauvegarde des sessions de réservation en cours.
 *
 * Stocke les données partielles du wizard de réservation étape par étape.
 * Un brouillon est une session non encore validée. Une fois la réservation
 * finalisée, le brouillon est supprimé (ou marqué 'completed').
 *
 * Avantages de cette table séparée de bookings :
 *  - Les contraintes FK (room_id, customer_id nullable / non-null) ne s'appliquent pas.
 *  - La table bookings reste propre, sans réservations fantômes.
 *  - Les index de disponibilité de Room::availableBetween() ne sont pas polués.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // Token URL-safe pour reprendre sans auth
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();

            // Étape courante du wizard (1-4)
            $table->unsignedTinyInteger('current_step')->default(1);

            // ── Étape 1 : Client ──────────────────────────────────────────────
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->unsignedBigInteger('booker_id')->nullable();
            $table->foreign('booker_id')->references('id')->on('customers')->nullOnDelete();

            // ── Étape 2 : Dates, heure, personnes, source ────────────────────
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->string('check_in_time', 10)->nullable();
            $table->unsignedSmallInteger('adults')->nullable();
            $table->unsignedSmallInteger('children')->nullable()->default(0);
            $table->string('source', 30)->nullable()->default('direct');

            // ── Étape 3 : Chambre sélectionnée ───────────────────────────────
            $table->unsignedBigInteger('room_id')->nullable();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();

            // ── Notes libres saisies à n'importe quelle étape ────────────────
            $table->text('notes')->nullable();

            // ── Expiration automatique ────────────────────────────────────────
            $table->timestamp('expires_at')->nullable(); // null = pas d'expiration
            $table->timestamp('last_activity_at')->nullable();

            // 'active' | 'completed' | 'abandoned'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['created_by', 'status']);
            $table->index(['token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_drafts');
    }
};
