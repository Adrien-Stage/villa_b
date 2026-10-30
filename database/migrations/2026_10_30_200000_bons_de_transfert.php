<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les bons de transfert : l'économat approvisionne lui-même un dépôt qui lui
 * appartient (une réserve, une cave annexe…), sans demande d'un service.
 *
 * La marchandise sort du magasin central au coût moyen et entre au dépôt au
 * même coût : elle reste du stock de l'hôtel, sous le même compte. Rien ne
 * passe en charge au transfert ; la consommation naîtra à l'inventaire du
 * dépôt, comme pour une livraison sur demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique(); // BT-AAAA-NNNN
            $table->foreignId('service_store_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('validated');
            $table->timestamp('transferred_at');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('total_value')->default(0); // centimes FCFA, au coût moyen

            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuer_signature', 80)->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('transferred_at');
            $table->index('status');
        });

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('packaging_name', 40)->nullable();
            $table->unsignedBigInteger('unit_cost')->default(0);  // centimes FCFA, coût moyen à la sortie
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
