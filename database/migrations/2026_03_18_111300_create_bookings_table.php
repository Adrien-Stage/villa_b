<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->onDelete('restrict');
            $table->foreignId('customer_id')->constrained()->onDelete('restrict');

            // null = réservation individuelle
            $table->foreignId('group_booking_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('booking_number')->unique(); // VB-2025-000001
            $table->string('status', 30)->default('pending');

            // --- DATES ---
            $table->date('check_in');
            $table->date('check_out');
            $table->timestamp('actual_check_in')->nullable();
            $table->timestamp('actual_check_out')->nullable();

            // --- PERSONNES ---
            $table->unsignedSmallInteger('adults_count')->default(1);
            $table->unsignedSmallInteger('children_count')->default(0);

            // --- TARIFICATION ---
            // Tous les montants en centimes FCFA
            $table->unsignedSmallInteger('total_nights');
            $table->unsignedInteger('price_per_night');
            $table->unsignedInteger('total_room_amount');
            $table->unsignedInteger('extras_amount')->default(0);
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('total_amount');

            // --- PAIEMENT ---
            $table->unsignedInteger('deposit_amount')->default(0);
            $table->unsignedInteger('paid_amount')->default(0);
            $table->unsignedInteger('balance_due')->default(0);

            // --- ORIGINE ET NOTES ---
            // 'direct', 'phone', 'email', 'ota_bookingcom', 'walk_in'
            $table->string('source', 30)->default('direct');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // --- TRAÇABILITÉ ---
            // Syntaxe longue car nullable + nullOnDelete sur users
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('checked_in_by')->nullable();
            $table->foreign('checked_in_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('checked_out_by')->nullable();
            $table->foreign('checked_out_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            // Recherches opérationnelles quotidiennes
            $table->index(['status']);
            $table->index(['check_in']);
            $table->index(['check_out']);
            // Vérification de disponibilité d'une chambre sur une période
            $table->index(['room_id', 'check_in', 'check_out']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};