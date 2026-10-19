<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classeur Excel de l'économat : un magasin (le magasin central ou un dépôt)
 * s'exporte avec ses articles, ses fournisseurs et ses mouvements, se gère
 * dans Excel, puis se réimporte.
 *
 * Chaque import garde son fichier et son analyse ; chaque mouvement qu'il
 * crée renvoie à lui, porte les précisions du fichier (document, bénéficiaire,
 * pointage…) et une empreinte qui empêche de rejouer deux fois la même ligne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_imports', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            // Magasin visé : le magasin central quand le dépôt est vide.
            $table->foreignId('service_store_id')->nullable()->constrained('service_stores')->restrictOnDelete();
            $table->string('status', 15)->default('draft');
            $table->string('file_name');
            $table->string('file_path');
            // Résumé, erreurs, avertissements et lignes de l'aperçu.
            $table->json('analysis')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        foreach (['stock_movements', 'service_store_movements'] as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->foreignId('stock_import_id')->nullable()->constrained('stock_imports')->nullOnDelete();
                // Document, fournisseur, bénéficiaire, pointage : ce que dit le fichier.
                $table->json('details')->nullable();
                // Empreinte de la ligne du classeur : jamais importée deux fois.
                $table->string('import_key', 64)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['stock_movements', 'service_store_movements'] as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dropConstrainedForeignId('stock_import_id');
                $table->dropIndex(['import_key']);
                $table->dropColumn(['details', 'import_key']);
            });
        }

        Schema::dropIfExists('stock_imports');
    }
};
