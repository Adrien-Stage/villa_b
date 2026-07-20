<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dépenses / charges décaissées de l'établissement (comptabilité de caisse).
        // Les recettes ne sont PAS stockées ici : elles sont lues à la volée depuis
        // les paiements / commandes existants. Seules les sorties d'argent saisies à
        // la main (électricité, eau, achats, loyer…) vivent dans cette table.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->timestamp('occurred_at');

            // electricity, water, purchase, rent, maintenance, transport, other
            $table->string('category', 30);

            $table->string('label', 180);

            // Montant décaissé, en centimes FCFA
            $table->unsignedBigInteger('amount');

            // cash, bank_transfer, orange_money, mtn_momo, check, other
            $table->string('payment_method', 30)->nullable();

            // Pièce justificative (reçu / facture) — chemin de fichier, optionnel
            $table->string('receipt_path')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['category', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
