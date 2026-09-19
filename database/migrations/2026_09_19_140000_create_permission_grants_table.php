<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surcharges de la matrice des droits.
 *
 * Le catalogue (App\Support\PermissionCatalog) donne le gabarit : quels rôles
 * détiennent quel droit par défaut. Cette table porte ce que l'établissement
 * en change — une ligne par écart, jamais la matrice entière. Un catalogue
 * recopié en base se périmerait au premier droit ajouté au code.
 *
 * Deux porteurs, un seul grain :
 *   - un rôle, pour la règle générale de l'établissement ;
 *   - un utilisateur, pour l'exception nominative.
 *
 * Un seul effet : allow ou deny. Le refus explicite l'emporte toujours, y
 * compris sur une autorisation venue d'un autre rôle détenu — c'est ce qui
 * permet d'écrire « le comptable consulte l'économat mais n'y crée pas
 * d'article » alors qu'il cumule un rôle qui, lui, le permettrait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_grants', function (Blueprint $table) {
            $table->id();

            // 'role' ou 'user'. Le sujet est désigné par son slug pour un rôle,
            // par son identifiant pour un utilisateur : une colonne de texte
            // porte les deux sans table de jointure polymorphe.
            $table->string('subject_type', 8);
            $table->string('subject_id', 64);

            $table->string('permission', 128);
            $table->string('effect', 5);          // 'allow' | 'deny'

            // Qui a décidé, et pourquoi. Une matrice de droits qui change sans
            // trace ne se contrôle pas — et la dérogation à la séparation des
            // tâches exige un motif écrit.
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'permission'], 'permission_grants_sujet_droit_unique');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_grants');
    }
};
