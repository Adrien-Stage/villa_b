<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corbeille de l'établissement et bordereaux de livraison.
 *
 *  - trash_entries : ce qu'efface un bouton « Supprimer » y est photographié
 *    — la fiche, ce qui part avec elle, les liens qu'elle tenait, ses
 *    fichiers — avant d'être effacé. Personne dans l'hôtel ne la voit :
 *    l'administrateur TECH de la console la consulte, restaure ou purge.
 *  - goods_receipt_attachments : les bordereaux de livraison joints à un
 *    bon de réception, plusieurs par livraison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trash_entries', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 80);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('type', 80);
            $table->string('module', 40);
            $table->string('label', 255);
            $table->json('payload');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name', 120)->nullable();
            $table->timestamp('deleted_at');
            $table->timestamp('restored_at')->nullable();
            $table->string('restored_by', 120)->nullable();
            $table->timestamps();

            $table->index('table_name');
            $table->index('module');
            $table->index('deleted_at');
            $table->index('restored_at');
        });

        Schema::create('goods_receipt_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_attachments');
        Schema::dropIfExists('trash_entries');
    }
};
