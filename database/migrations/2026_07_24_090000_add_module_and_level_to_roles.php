<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache chaque rôle à un module métier et lui donne une icône, pour que la
 * rubrique Utilisateurs se construise seule à partir de la table des rôles
 * (tout nouveau rôle devient assignable sans toucher au code).
 *
 * Ajoute aussi un niveau d'accès sur le pivot : un même utilisateur peut avoir
 * plusieurs modules avec des droits différents (lecture ou lecture/écriture).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Module métier couvert : hebergement, housekeeping, restaurant,
            // boutique, economat, comptabilite, direction…
            $table->string('module', 40)->nullable()->after('slug');
            // Icône Lucide affichée sur la carte de rôle.
            $table->string('icon', 40)->nullable()->after('module');
            $table->unsignedInteger('sort_order')->default(0)->after('icon');
            // Rôles réservés (admin, manager…) : non assignables par un manager.
            $table->boolean('is_assignable')->default(true)->after('sort_order');
        });

        Schema::table('role_user', function (Blueprint $table) {
            // 'read' = consultation seule, 'write' = consultation + actions.
            // Null pour les rattachements existants : traités comme 'write'
            // afin de ne restreindre aucun compte déjà en service.
            $table->string('level', 10)->nullable()->after('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn('level');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['module', 'icon', 'sort_order', 'is_assignable']);
        });
    }
};
