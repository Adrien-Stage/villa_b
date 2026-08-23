<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mémorise à qui le code de check-in doit être adressé.
 *
 * Le choix est fait à la dernière étape de la réservation, mais l'envoi peut
 * avoir lieu plus tard — depuis la fenêtre de confirmation, ou lors d'un renvoi.
 * Sans cette colonne, chaque envoi ultérieur devrait redemander le destinataire,
 * ou repartir au client alors que le dossier est tenu par un mandataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('code_recipient', 16)->nullable()->after('checkin_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('code_recipient');
        });
    }
};
