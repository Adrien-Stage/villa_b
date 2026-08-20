<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Socle de la comptabilité générale : plan de comptes, journaux, exercices.
 *
 * Ces trois tables ne portent aucune écriture — elles définissent le cadre
 * dans lequel les écritures viendront s'inscrire (migration suivante).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Plan de comptes ─────────────────────────────────────────────────
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique();
            $table->string('label', 180);

            // Classe SYSCOHADA : 1 à 9. Le référentiel révisé ne comporte pas
            // de classe 10 — la structure des capitaux propres tient en classe 1.
            $table->unsignedTinyInteger('account_class');

            /**
             * Un compte collectif centralise une catégorie de tiers (411000
             * Clients, 401000 Fournisseurs, 421000 Personnel). Le détail par
             * tiers n'est JAMAIS un compte : il vit sur la ligne d'écriture,
             * via son auxiliaire. Créer 411001, 411002… rendrait la balance
             * générale illisible et l'audit impraticable.
             */
            $table->boolean('is_collective')->default(false);

            // Un compte de regroupement ne reçoit pas d'écriture directe :
            // il totalise ses sous-comptes dans la balance.
            $table->boolean('is_postable')->default(true);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_class', 'code']);
        });

        // ── Journaux ────────────────────────────────────────────────────────
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            $table->string('code', 10)->unique();   // VT, AC, BQ, CA, OD
            $table->string('label', 120);

            // Contrepartie par défaut : la banque pour BQ, la caisse pour CA.
            $table->string('default_account', 20)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Exercices et périodes ───────────────────────────────────────────
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();

            $table->string('label', 60);            // « Exercice 2026 »
            $table->date('starts_on');              // 1er janvier
            $table->date('ends_on');                // 31 décembre

            // Un exercice clos ne reçoit plus rien, même par extourne.
            $table->timestamp('closed_at')->nullable();

            // Les à-nouveaux ont-ils été repris ? Une reprise ne se fait
            // qu'une fois : le drapeau empêche de doubler les soldes d'ouverture.
            $table->timestamp('opening_posted_at')->nullable();

            $table->timestamps();
            $table->unique(['starts_on', 'ends_on']);
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');

            /**
             * Article 22 de l'Acte Uniforme : les écritures doivent devenir
             * irréversibles. Une période verrouillée n'accepte plus aucune
             * écriture — la correction passe obligatoirement par une
             * contre-passation datée d'une période ouverte.
             */
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique(['fiscal_year_id', 'starts_on']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};
