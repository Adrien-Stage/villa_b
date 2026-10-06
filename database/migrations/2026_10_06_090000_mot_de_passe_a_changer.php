<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mot de passe à changer à la prochaine connexion.
 *
 * Quand un responsable réinitialise le mot de passe d'un employé, il lui
 * remet un mot de passe provisoire. Celui-ci ne doit servir qu'une fois :
 * à la connexion suivante, l'employé choisit le sien avant d'aller plus loin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
