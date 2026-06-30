<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');

            // null = guest walk-in sans fiche client existante
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // --- IDENTITÉ ---
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 5)->nullable();

            // --- DOCUMENT (police du tourisme) ---
            $table->string('id_document_type', 30)->nullable();
            $table->string('id_document_number')->nullable();
            $table->date('id_document_expiry')->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            // --- CHECK-IN ---
            $table->timestamp('checked_in_at')->nullable();
            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();

            $table->string('photo_path')->nullable();

            // true = c'est lui qui a signé le registre d'entrée
            $table->boolean('is_primary_guest')->default(false);

            $table->timestamps();

            $table->index(['booking_id']);
            $table->index(['id_document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};