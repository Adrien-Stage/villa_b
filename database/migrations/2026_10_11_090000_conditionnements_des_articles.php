<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les conditionnements des articles de l'économat.
 *
 * Un article reçu en cartons de 200 pièces, rangés en paquets de 10, reste
 * compté dans sa plus petite unité, la pièce : un seul stock, un seul coût
 * moyen. Chaque conditionnement plus grand (paquet, carton) dit combien de
 * pièces il contient, et combien d'unités restent encore fermées dans le
 * magasin. Ce qui n'est dans aucune unité fermée est en vrac.
 *
 * Une sortie prend d'abord le vrac, puis les plus petites unités fermées, et
 * n'ouvre un paquet ou un carton que quand il le faut. Chaque mouvement garde
 * l'état des conditionnements après lui et les ouvertures qu'il a causées.
 *
 * Une demande interne peut se faire en paquets ou en cartons : la ligne garde
 * le conditionnement demandé et la quantité dans ce conditionnement ; sa
 * quantité demandée reste exprimée dans l'unité de l'article.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_item_packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            // Nombre d'unités de l'article contenues dans une unité fermée.
            $table->decimal('factor', 14, 3);
            // Unités de ce conditionnement encore fermées, hors d'une unité plus grande.
            $table->unsignedInteger('closed_count')->default(0);
            $table->timestamps();

            $table->unique(['stock_item_id', 'name']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->json('packaging')->nullable()->after('stock_after');
        });

        Schema::table('stock_requisition_lines', function (Blueprint $table) {
            $table->string('packaging_name', 40)->nullable()->after('quantity_issued');
            $table->decimal('packaging_quantity', 12, 3)->nullable()->after('packaging_name');
        });
    }

    public function down(): void
    {
        Schema::table('stock_requisition_lines', function (Blueprint $table) {
            $table->dropColumn(['packaging_name', 'packaging_quantity']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('packaging');
        });

        Schema::dropIfExists('stock_item_packagings');
    }
};
