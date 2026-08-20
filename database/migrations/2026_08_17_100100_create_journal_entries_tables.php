<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les écritures comptables et leurs lignes.
 *
 * Une écriture est un fait daté et figé. Elle doit survivre à la
 * modification, voire à la suppression, de l'opération qui l'a produite —
 * c'est toute la différence avec la comptabilité de caisse actuelle, qui
 * recalcule le chiffre d'affaires à la volée.
 *
 * Montants en centimes FCFA. Débit et crédit sont deux colonnes distinctes
 * plutôt qu'un montant signé : c'est la présentation attendue d'un grand
 * livre, et elle évite toute ambiguïté de signe à la lecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();

            $table->date('entry_date');
            $table->string('reference', 60)->nullable();   // n° de pièce
            $table->string('label', 255);

            /**
             * Origine de l'écriture. Le couple (source, schéma) garantit
             * l'idempotence : un check-out rejoué, une commande repayée ou un
             * import relancé ne peuvent pas produire deux fois la même
             * écriture. Sans cette contrainte, une double génération est
             * indétectable a posteriori et ne se corrige que par extourne.
             */
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('schema', 60)->nullable();      // 'checkout', 'shop_sale'…

            // Une écriture non validée reste modifiable ; une fois validée,
            // elle ne se corrige plus que par contre-passation.
            $table->timestamp('posted_at')->nullable();

            // Chaînage de l'extourne, dans les deux sens.
            $table->foreignId('reverses_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'schema'], 'journal_entries_source_unique');
            $table->index(['entry_date', 'journal_id']);
            $table->index('fiscal_period_id');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();

            // Le code plutôt que la clé étrangère : une écriture doit rester
            // lisible même si un compte est désactivé au plan de comptes.
            $table->string('account_code', 20);
            $table->string('label', 255)->nullable();

            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);

            /**
             * Comptabilité auxiliaire : la granularité par tiers se porte ici,
             * pas dans le plan de comptes. Le compte reste 411000 pour tous
             * les clients ; c'est l'auxiliaire qui dit lequel.
             */
            $table->string('auxiliary_type', 120)->nullable();
            $table->unsignedBigInteger('auxiliary_id')->nullable();

            // Centre d'analyse (classe 9) : hébergement, restaurant, boutique…
            $table->string('analytic_center', 60)->nullable();

            // Lettrage : rapproche un règlement de la facture qu'il solde.
            $table->string('reconciliation_code', 20)->nullable();
            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->index(['account_code', 'journal_entry_id']);
            $table->index(['auxiliary_type', 'auxiliary_id']);
            $table->index('reconciliation_code');
            $table->index('analytic_center');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
