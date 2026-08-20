<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets d'intervention saisis par le personnel de l'établissement depuis le
 * bouton « Suggestion » de la barre supérieure.
 *
 * La table vit dans la base de l'établissement, comme le journal d'audit :
 * l'ERP la lit directement en PDO pour alimenter son kanban, et y réécrit le
 * statut et sa réponse. Il n'y a donc qu'une seule donnée, pas de copie à
 * synchroniser, et un ticket reste lisible même si l'ERP est indisponible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // L'auteur est conservé en clair : un compte désactivé ou supprimé
            // ne doit pas rendre anonyme un ticket déjà traité côté support.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 120);
            $table->string('author_role', 60)->nullable();

            $table->string('type', 20)->default('probleme'); // probleme | suggestion
            $table->string('subject', 160);
            $table->text('message');

            // Page depuis laquelle le ticket a été ouvert : le support gagne le
            // contexte sans avoir à le demander.
            $table->string('context_url', 255)->nullable();

            $table->string('status', 20)->default('nouveau'); // nouveau | en_cours | resolu | rejete

            // Renseignés par l'ERP quand un administrateur traite le ticket.
            $table->text('reply')->nullable();
            $table->string('handled_by', 120)->nullable();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
