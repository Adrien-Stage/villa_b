<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // after('email') : on contrôle l'ordre des colonnes dans la table
            // C'est cosmétique mais utile quand tu inspectes la BD dans pgAdmin
            // Le rôle détermine ce que l'utilisateur peut faire dans l'app
            // On stocke une string, pas un integer : plus lisible dans les logs et le code
            $table->string('role', 30)
                  ->default('reception')
                  ->after('email');

            $table->string('phone', 30)->nullable()->after('role');

            // is_active : désactiver un employé qui quitte sans supprimer son historique
            $table->boolean('is_active')->default(true)->after('phone');

            // last_login_at : utile pour les audits de sécurité
            // "Ce compte ne s'est pas connecté depuis 6 mois → désactiver ?"
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index(['role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);

            // Supprimer les colonnes dans l'ordre inverse de leur ajout
            $table->dropColumn([
                'last_login_at',
                'is_active',
                'phone',
                'role',
                ]);
        });
    }
};