<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            // cascade : si on supprime la facture, ses lignes partent avec

            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1); // decimal pour les demi-journées
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total_price');

            $table->decimal('tax_rate', 5, 2)->default(0); // Ex: 19.25 pour la TVA camerounaise
            $table->unsignedInteger('tax_amount')->default(0);

            // 'room', 'restaurant', 'extra', 'tax', 'discount'
            $table->string('category', 30);

            // Polymorphisme léger : d'où vient cette ligne ?
            $table->string('source_type')->nullable();  // 'App\Models\Booking'
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();

            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};