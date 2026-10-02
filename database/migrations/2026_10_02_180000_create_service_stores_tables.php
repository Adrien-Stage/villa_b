<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dépôts de service : le stock que détient un service hors du magasin
 * central — étages, mini-bar, bar, pâtisserie…
 *
 * L'économat les alimente par ses livraisons. Ce qui y entre reste du stock
 * (même compte de classe 3) : la charge ne naît qu'à la consommation, que
 * l'inventaire du dépôt révèle. La cuisine garde son garde-manger, qui a ses
 * propres règles (fiches techniques, production).
 *
 * Montants en centimes FCFA, quantités en décimal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_stores', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            // Service demandeur (clé de StockRequisition::DEPARTMENTS) : il dit
            // qui peut demander pour ce dépôt et quel centre porte sa consommation.
            $table->string('department', 30);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });

        Schema::create('service_store_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock', 12, 3)->default(0);
            // Coût moyen pondéré du dépôt : chaque livraison entre au CUMP de
            // l'économat du moment, et la consommation sort à ce coût.
            $table->unsignedBigInteger('average_cost')->default(0);
            $table->timestamps();

            $table->unique(['service_store_id', 'stock_item_id']);
        });

        Schema::create('service_store_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            // in | out | adjustment
            $table->string('type', 15);
            // Positif pour une entrée, négatif pour une sortie.
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_after', 12, 3)->default(0);
            $table->unsignedBigInteger('unit_cost')->default(0);
            // Compte de stock de l'article au moment du mouvement.
            $table->string('stock_account', 10)->nullable();
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['service_store_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::table('stock_requisitions', function (Blueprint $table) {
            $table->foreignId('service_store_id')->nullable()->after('department')
                ->constrained('service_stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_store_id');
        });

        Schema::dropIfExists('service_store_movements');
        Schema::dropIfExists('service_store_stocks');
        Schema::dropIfExists('service_stores');
    }
};
