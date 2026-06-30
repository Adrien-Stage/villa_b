<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_pantry_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_pantry_item_id')->constrained('restaurant_pantry_items')->cascadeOnDelete();

            // in, out, adjust
            $table->string('type', 20);
            $table->decimal('quantity', 12, 3);

            // purchase, kitchen, waste, correction, other
            $table->string('reason', 30)->default('other');
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['restaurant_pantry_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_pantry_movements');
    }
};

