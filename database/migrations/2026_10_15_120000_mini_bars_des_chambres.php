<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mini-bars des chambres.
 *
 * Un article du mini-bar est un article de l'économat vendu au client à un prix
 * fixé. Chaque type de chambre a sa dotation standard (2 eaux, 1 chocolat…),
 * que chaque chambre peut ajuster. Le contenu de chaque mini-bar est un petit
 * stock : il entre depuis le dépôt du mini-bar (réapprovisionnement par la
 * gouvernante, sans charge : le stock change de lieu) et sort quand l'équipe
 * relève une consommation, qui part au folio du client.
 *
 * Le relevé est obligatoire : à chaque recouche pendant le séjour, au départ
 * (quand la réception le demande avant de clore la note) et, si le client est
 * parti sans attendre, au ménage qui suit son départ.
 *
 * Montants en centimes FCFA, quantités en unités entières.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minibar_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->unique()->constrained()->restrictOnDelete();
            $table->string('name', 120);
            // Prix de vente au client, TTC.
            $table->unsignedBigInteger('price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Dotation standard d'un type de chambre.
        Schema::create('minibar_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minibar_article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['room_type_id', 'minibar_article_id']);
        });

        // Ce que chaque mini-bar doit contenir et ce qu'il contient.
        Schema::create('room_minibar_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minibar_article_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('standard_quantity')->default(0);
            $table->unsignedInteger('quantity')->default(0);
            // Coût moyen des unités présentes dans la chambre.
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->timestamps();

            $table->unique(['room_id', 'minibar_article_id']);
        });

        Schema::create('minibar_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('housekeeping_assignment_id')->nullable()->constrained()->nullOnDelete();
            // sejour | depart | apres_depart
            $table->string('occasion', 15);
            // folio | a_facturer | aucune
            $table->string('billing', 15);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['room_id', 'checked_at']);
            $table->index('checked_at');
        });

        Schema::create('minibar_check_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minibar_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minibar_article_id')->constrained()->restrictOnDelete();
            $table->string('label', 120);
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('consumed');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total_price');
            $table->unsignedBigInteger('unit_cost');
            $table->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('minibar_restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_store_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            // depart | vide | mise_en_place
            $table->string('reason', 15);
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('restocked_at');
            $table->timestamps();
        });

        // Journal du stock des mini-bars : source de vérité de leur contenu.
        Schema::create('minibar_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minibar_article_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            // restock | consumption
            $table->string('type', 15);
            // Positif pour une entrée, négatif pour une sortie.
            $table->integer('quantity');
            $table->unsignedInteger('quantity_after');
            $table->unsignedBigInteger('unit_cost')->default(0);
            $table->string('stock_account', 10)->nullable();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['room_id', 'occurred_at']);
        });

        // Contrôle du mini-bar demandé par la réception au départ du client.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('minibar_check_requested_at')->nullable();
            $table->foreignId('minibar_check_requested_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Client parti sans relevé de départ : le relevé reste dû, et la
        // chambre ne peut pas être marquée nettoyée avant.
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('minibar_due_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('minibar_due_booking_id');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('minibar_check_requested_by');
            $table->dropColumn('minibar_check_requested_at');
        });

        Schema::dropIfExists('minibar_movements');
        Schema::dropIfExists('minibar_restocks');
        Schema::dropIfExists('minibar_check_lines');
        Schema::dropIfExists('minibar_checks');
        Schema::dropIfExists('room_minibar_lines');
        Schema::dropIfExists('minibar_standards');
        Schema::dropIfExists('minibar_articles');
    }
};
