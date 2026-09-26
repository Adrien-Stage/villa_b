<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des politiques d'annulation
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('code')->index();
            $table->string('name');
            $table->text('description')->nullable();
            // penalty_type: free, first_night, percentage, fixed_amount, non_refundable
            $table->string('penalty_type')->default('first_night');
            // Pourcentage (ex: 50) ou montant fixe en centimes (ex: 2000000)
            $table->integer('penalty_value')->nullable();
            $table->integer('free_cancel_days_before')->default(2);
            $table->string('free_cancel_time', 5)->default('18:00');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // 2. Colonnes sur bookings pour snapshot & date limite
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('cancellation_policy_id')
                ->nullable()
                ->constrained('cancellation_policies')
                ->nullOnDelete();
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->dateTime('free_cancel_until')->nullable()->index();
        });

        // 3. Table d'audit des annulations de réservation
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();
            $table->string('cancellation_number')->unique();
            $table->dateTime('cancelled_at')->index();
            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Motifs standardisés
            $table->string('reason_code')->default('guest_request');
            $table->text('reason_description')->nullable();

            // Ventilation financière (en centimes FCFA)
            $table->integer('penalty_amount')->default(0);
            $table->boolean('penalty_waived')->default(false);
            $table->foreignId('waived_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('waive_reason')->nullable();

            $table->integer('deposit_paid')->default(0);
            $table->integer('deposit_retained')->default(0);
            $table->integer('refund_amount')->default(0);

            // Modalité de restitution
            $table->string('refund_method')->nullable(); // cash, orange_money, mtn_momo, bank_transfer, credit_note
            $table->string('refund_status')->default('none'); // none, completed, pending, credit_issued
            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->timestamps();
        });

        // 4. Initialisation des politiques standards de référence
        $now = now();
        DB::table('cancellation_policies')->insert([
            [
                'code'                     => 'FLEX_48H',
                'name'                     => 'Flexible (jusqu\'à 48h avant l\'arrivée)',
                'description'              => 'Annulation gratuite jusqu\'à 2 jours avant l\'arrivée à 18h00. Au-delà, la première nuitée est facturée.',
                'penalty_type'             => 'first_night',
                'penalty_value'            => null,
                'free_cancel_days_before'  => 2,
                'free_cancel_time'         => '18:00',
                'is_default'               => true,
                'is_active'                => true,
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'code'                     => 'NON_REF',
                'name'                     => 'Non remboursable (100% de frais)',
                'description'              => 'Le montant total du séjour est dû dès la réservation. Aucun remboursement en cas d\'annulation ou de non-présentation.',
                'penalty_type'             => 'non_refundable',
                'penalty_value'            => null,
                'free_cancel_days_before'  => 0,
                'free_cancel_time'         => '18:00',
                'is_default'               => false,
                'is_active'                => true,
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'code'                     => 'FLEX_SAME_DAY',
                'name'                     => 'Ultra Flexible (jour même jusqu\'à 14h00)',
                'description'              => 'Annulation sans frais possible jusqu\'à 14h00 le jour de l\'arrivée. Au-delà, la première nuitée est due.',
                'penalty_type'             => 'first_night',
                'penalty_value'            => null,
                'free_cancel_days_before'  => 0,
                'free_cancel_time'         => '14:00',
                'is_default'               => false,
                'is_active'                => true,
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
            [
                'code'                     => 'MODERATE_7D',
                'name'                     => 'Modérée (7 jours - 50% de pénalité)',
                'description'              => 'Annulation gratuite jusqu\'à 7 jours avant l\'arrivée à 18h00. Au-delà, 50% du montant du séjour est facturé.',
                'penalty_type'             => 'percentage',
                'penalty_value'            => 50,
                'free_cancel_days_before'  => 7,
                'free_cancel_time'         => '18:00',
                'is_default'               => false,
                'is_active'                => true,
                'created_at'               => $now,
                'updated_at'               => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_cancellations');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['cancellation_policy_id']);
            $table->dropColumn([
                'cancellation_policy_id',
                'cancellation_policy_snapshot',
                'free_cancel_until',
            ]);
        });

        Schema::dropIfExists('cancellation_policies');
    }
};
