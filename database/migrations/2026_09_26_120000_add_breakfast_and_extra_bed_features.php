<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Colonnes sur room_types pour le petit-déjeuner et lit d'appoint
        Schema::table('room_types', function (Blueprint $table) {
            $table->boolean('includes_breakfast')->default(true)->after('base_price');
            $table->boolean('allows_extra_bed')->default(true)->after('includes_breakfast');
            $table->unsignedInteger('extra_bed_price')->nullable()->after('allows_extra_bed'); // En centimes FCFA
        });

        // 2. Colonnes sur bookings pour lit supplémentaire et prépaiement PDJ enfants
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('has_extra_bed')->default(false)->after('children_count');
            $table->unsignedSmallInteger('extra_bed_count')->default(0)->after('has_extra_bed');
            $table->unsignedInteger('extra_bed_amount')->default(0)->after('extra_bed_count'); // En centimes FCFA
            $table->boolean('prepaid_breakfast_children')->default(false)->after('extra_bed_amount');
            $table->unsignedInteger('prepaid_breakfast_amount')->default(0)->after('prepaid_breakfast_children'); // En centimes FCFA
        });

        // 3. Colonnes sur booking_drafts
        Schema::table('booking_drafts', function (Blueprint $table) {
            $table->boolean('has_extra_bed')->default(false)->after('children_count');
            $table->unsignedSmallInteger('extra_bed_count')->default(0)->after('has_extra_bed');
            $table->unsignedInteger('extra_bed_amount')->default(0)->after('extra_bed_count');
            $table->boolean('prepaid_breakfast_children')->default(false)->after('extra_bed_amount');
            $table->unsignedInteger('prepaid_breakfast_amount')->default(0)->after('prepaid_breakfast_children');
        });

        // 4. Table des droits journaliers de petits-déjeuners (Breakfast Entitlements)
        Schema::create('breakfast_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->date('service_date');
            $table->unsignedSmallInteger('adults_included')->default(1);
            $table->unsignedSmallInteger('children_included')->default(0);
            $table->unsignedSmallInteger('adults_consumed')->default(0);
            $table->unsignedSmallInteger('children_consumed')->default(0);
            $table->string('status', 30)->default('available'); // available, consumed, partially_consumed, cancelled
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['booking_id', 'service_date'], 'uniq_booking_breakfast_service_date');
            $table->index(['service_date', 'status'], 'idx_breakfast_date_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breakfast_entitlements');

        Schema::table('booking_drafts', function (Blueprint $table) {
            $table->dropColumn([
                'has_extra_bed',
                'extra_bed_count',
                'extra_bed_amount',
                'prepaid_breakfast_children',
                'prepaid_breakfast_amount',
            ]);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'has_extra_bed',
                'extra_bed_count',
                'extra_bed_amount',
                'prepaid_breakfast_children',
                'prepaid_breakfast_amount',
            ]);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn([
                'includes_breakfast',
                'allows_extra_bed',
                'extra_bed_price',
            ]);
        });
    }
};
