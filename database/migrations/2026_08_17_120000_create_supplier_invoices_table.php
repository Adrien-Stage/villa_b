<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facture fournisseur.
 *
 * Distincte du bon de commande : un bon engage, une facture constate la dette
 * et ouvre le droit à déduction de TVA. Les deux peuvent diverger — livraison
 * partielle, prix révisé — donc la facture porte ses propres montants et se
 * contente de référencer le bon dont elle est issue.
 *
 * La retenue à la source y est stockée telle qu'appliquée : le taux peut
 * changer par arrêté, une facture ancienne doit rester relisible avec le taux
 * qui lui était opposable.
 *
 * Montants en centimes FCFA, taux en points de base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // Le bon de commande est facultatif : toutes les factures ne
            // naissent pas d'un bon (honoraires, prestations ponctuelles).
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();

            // Référence portée par le document du fournisseur, pas par nous.
            $table->string('number', 60);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Nature de la charge : détermine le compte de classe 6 débité.
            $table->string('charge_account', 20);
            $table->string('label');

            // Décomposition TVA — la base est arrondie, la taxe déduite par
            // différence, comme partout ailleurs (TaxationService).
            $table->unsignedBigInteger('amount_ttc')->default(0);
            $table->unsignedBigInteger('amount_ht')->default(0);
            $table->unsignedBigInteger('amount_vat')->default(0);
            $table->string('tax_rate_code', 30)->nullable();

            // Retenue à la source : figée au taux appliqué le jour de la facture.
            $table->string('withholding_type', 30)->nullable();
            $table->unsignedInteger('withholding_basis_points')->default(0);
            $table->unsignedBigInteger('withholding_amount')->default(0);

            // Ce que le fournisseur touchera réellement : TTC moins la retenue.
            $table->unsignedBigInteger('net_payable')->default(0);

            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();

            $table->timestamps();

            // Un fournisseur ne présente pas deux fois la même référence :
            // c'est la garde la plus efficace contre la double saisie.
            $table->unique(['supplier_id', 'number']);
            $table->index('invoice_date');
            $table->index('withholding_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
