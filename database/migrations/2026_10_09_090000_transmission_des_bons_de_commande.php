<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comment un bon de commande est parvenu au fournisseur.
 *
 * Jusqu'ici un bon ne quittait le brouillon que par email : un fournisseur
 * sans adresse (marché, vendeur joint par téléphone) bloquait toute la
 * chaîne, puisqu'on ne réceptionne qu'un bon transmis. Le bon garde
 * désormais la trace du moyen : email, remis en main propre, téléphone,
 * WhatsApp… ou « régularisation » quand il est établi après une réception
 * directe, la marchandise étant arrivée sans commande préalable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('transmission', 20)->nullable()->after('sent_to_email');
        });

        DB::table('purchase_orders')->whereNotNull('sent_to_email')->update(['transmission' => 'email']);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('transmission');
        });
    }
};
