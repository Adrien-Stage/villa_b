<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les sorties de matériel hors de l'établissement.
 *
 * L'économe laisse parfois sortir du magasin un matériel qui ne sert pas
 * l'hôtel : prêt, envoi en réparation, don, cession, transfert vers un autre
 * établissement, restitution à son propriétaire. Ce n'est pas une demande
 * d'un service : c'est une personne de l'extérieur qui vient le chercher.
 *
 * Le bon de sortie garde qui l'a emporté (nom, structure, téléphone, pièce
 * d'identité) et signe en son nom, comme le demandeur d'un bon interne. La
 * sortie déstocke l'économat au coût moyen, et chaque ligne garde ce coût.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_issues', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique(); // BSE-AAAA-NNNN
            $table->string('reason', 30);
            $table->string('status', 20)->default('validated');
            $table->timestamp('issued_at');

            // La personne qui vient chercher le matériel.
            $table->string('beneficiary_name', 160);
            $table->string('beneficiary_organisation', 160)->nullable();
            $table->string('beneficiary_phone', 40)->nullable();
            $table->string('beneficiary_id_document', 80)->nullable();
            $table->string('beneficiary_signature', 80)->nullable();

            $table->date('expected_return_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('total_value')->default(0); // centimes FCFA, au coût moyen

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuer_signature', 80)->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('issued_at');
            $table->index('status');
        });

        Schema::create('external_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_cost')->default(0);  // centimes FCFA, coût moyen à la sortie
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_issue_lines');
        Schema::dropIfExists('external_issues');
    }
};
