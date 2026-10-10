<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La découpe d'un article de l'économat vers le garde-manger d'un restaurant.
 *
 * Le chef prend 30 kg de poulet sur les 180 kg reçus et les répartit : 10 kg
 * en quarts, 8 kg en demis, 7 kg entiers, 5 kg de carcasses. Les 30 kg sortent
 * de l'économat au coût moyen ; chaque portion entre au garde-manger avec sa
 * part de la valeur, au poids. On recommence sur ce qui reste, jusqu'à
 * épuisement.
 *
 * Le bon de découpe garde la quantité prise, sa valeur, le restaurant, et
 * chaque portion avec sa quantité, sa valeur et son coût unitaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_cuts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique(); // DEC-AAAA-NNNN
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('point_of_sale_id')->constrained('points_of_sale')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);                    // dans l'unité de l'article
            $table->unsignedBigInteger('unit_cost');               // centimes, coût moyen à la sortie
            $table->unsignedBigInteger('total_value');             // centimes
            $table->text('notes')->nullable();
            $table->foreignId('cut_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cut_at');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_cut_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_cut_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_pantry_item_id')->constrained()->restrictOnDelete();
            $table->string('label', 160);                          // nom de la portion au moment de la découpe
            $table->decimal('quantity', 12, 3);                    // dans l'unité de l'article
            $table->decimal('pantry_quantity', 12, 3);             // dans l'unité du garde-manger
            $table->unsignedBigInteger('value');                   // centimes
            $table->decimal('unit_cost', 14, 4);                   // centimes par unité du garde-manger
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_cut_lines');
        Schema::dropIfExists('stock_cuts');
    }
};
