<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des séjours offerts.
 *
 * Jusqu'ici, une gratuité n'existait que sous forme de texte libre préfixé
 * « Offerte - Motif : » dans le champ notes, modifiable depuis l'écran de
 * modification du séjour. Impossible d'en tirer un état, et rien n'indiquait
 * qui avait validé l'offre : approve() ne consignait ni l'approbateur, ni la
 * date, ni le manque à gagner.
 *
 * Ces colonnes font de la gratuité un fait comptable : requêtable, valorisé
 * et attribuable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_complimentary')->default(false)->after('status');
            $table->text('complimentary_reason')->nullable()->after('is_complimentary');
            // Valeur du séjour au tarif qui aurait été appliqué : le montant
            // du séjour tombant à zéro, c'est la seule trace du manque à gagner.
            $table->unsignedBigInteger('complimentary_value')->default(0)->after('complimentary_reason');
            $table->foreignId('approved_by')->nullable()->after('checked_out_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index('is_complimentary');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['is_complimentary']);
            $table->dropColumn([
                'is_complimentary',
                'complimentary_reason',
                'complimentary_value',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};
