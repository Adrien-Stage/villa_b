<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_bookings', function (Blueprint $table) {
            $table->id();
            // Client organisateur du groupe
            $table->unsignedBigInteger('contact_customer_id')->nullable();
            $table->foreign('contact_customer_id')
                  ->references('id')->on('customers')
                  ->nullOnDelete();

            $table->string('group_code')->unique(); // GRP-2025-0001
            $table->string('group_name');
            $table->string('event_type', 30)->nullable(); // 'family', 'corporate', 'wedding'

            $table->date('start_date');
            $table->date('end_date');

            $table->unsignedInteger('total_deposit_required')->default(0);
            $table->unsignedInteger('total_deposit_paid')->default(0);

            $table->string('status', 30)->default('pending');

            $table->boolean('rooming_list_sent')->default(false);
            $table->timestamp('rooming_list_sent_at')->nullable();

            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_bookings');
    }
};