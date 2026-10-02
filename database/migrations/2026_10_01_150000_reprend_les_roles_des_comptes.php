<?php

use App\Support\RepriseDesRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprise des comptes dans le référentiel hiérarchique des rôles.
 *
 * Les affectations (role_user) font désormais seules foi ; la colonne héritée
 * users.role ne compte plus que pour un compte sans affectation. Avant de
 * l'ignorer ailleurs, on rend explicite ce qu'elle accordait, et on préserve
 * ce que faisaient les chefs de cuisine avant que la salle ne passe au
 * responsable de restaurant. Voir RepriseDesRoles.
 */
return new class extends Migration
{
    public function up(): void
    {
        $bilan = RepriseDesRoles::executer();

        if ($bilan['affectes'] + $bilan['realignes'] + $bilan['dedoubles'] === 0 && $bilan['ignores'] === []) {
            return;
        }

        // Trace dans le journal d'audit de l'établissement : la reprise a
        // changé des affectations sans qu'un utilisateur ne l'ait demandé.
        DB::table('audit_logs')->insert([
            'event_type' => 'roles_reprise',
            'action'     => "Reprise des rôles : {$bilan['affectes']} affectation(s) créée(s) depuis le rôle hérité, "
                . "{$bilan['realignes']} colonne(s) réalignée(s), {$bilan['dedoubles']} chef(s) de cuisine "
                . "doublé(s) du rôle de responsable de restaurant.",
            'module'     => 'security',
            'payload'    => json_encode($bilan, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Rien à défaire : les affectations créées rendent explicite ce que
        // la colonne accordait déjà, et le cumul des chefs de cuisine préserve
        // leurs droits d'avant. Une version antérieure de l'application les
        // lit sans changement de comportement.
    }
};
