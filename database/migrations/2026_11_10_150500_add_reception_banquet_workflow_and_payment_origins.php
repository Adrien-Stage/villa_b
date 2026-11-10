<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow interne de réservation de banquet depuis la réception et traçabilité
 * explicite de l'origine de chaque encaissement (acompte, check-in, séjour,
 * check-out, facture complémentaire, groupe, POS, banquet, dépôt partenaire…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_banquets', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_banquets', 'booking_id')) {
                $table->foreignId('booking_id')->nullable()->after('customer_id')->constrained('bookings')->nullOnDelete();
            }
            if (! Schema::hasColumn('restaurant_banquets', 'source')) {
                $table->string('source', 30)->default('restaurant')->after('status');
                $table->index('source');
            }
        });

        Schema::table('restaurant_banquet_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurant_banquet_payments', 'reference')) {
                $table->string('reference', 40)->nullable()->after('restaurant_banquet_id');
                $table->index('reference');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'payment_origin')) {
                $table->string('payment_origin', 40)->nullable()->after('purpose');
                $table->index('payment_origin');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'payment_origin')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['payment_origin']);
                $table->dropColumn('payment_origin');
            });
        }

        if (Schema::hasColumn('restaurant_banquet_payments', 'reference')) {
            Schema::table('restaurant_banquet_payments', function (Blueprint $table) {
                $table->dropIndex(['reference']);
                $table->dropColumn('reference');
            });
        }

        Schema::table('restaurant_banquets', function (Blueprint $table) {
            if (Schema::hasColumn('restaurant_banquets', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('restaurant_banquets', 'booking_id')) {
                $table->dropConstrainedForeignId('booking_id');
            }
        });
    }
};
