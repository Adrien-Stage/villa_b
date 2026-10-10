<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les inventaires de l'économat ouverts jusqu'ici ne portaient pas
 * d'établissement. Le contrôle des stocks ne lit que les écarts de
 * l'établissement : ces inventaires clôturés n'apparaissaient donc ni dans
 * l'audit des écarts, ni dans le tableau de bord du contrôle.
 *
 * Une base sert un seul établissement : quand il n'y en a qu'un, les
 * inventaires et demandes d'achat sans établissement lui reviennent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $etablissements = DB::table('tenants')->pluck('id');
        if ($etablissements->count() !== 1) {
            return;
        }

        foreach (['stock_counts', 'purchase_requests'] as $table) {
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $etablissements->first()]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : l'établissement reste renseigné.
    }
};
