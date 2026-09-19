<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étendue des données attachée à un droit.
 *
 * Le rôle dit ce qu'on peut faire, la portée dit sur quoi. Nulle par défaut :
 * un droit sans portée déclarée porte sur tout l'établissement, comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_grants', function (Blueprint $table) {
            $table->string('scope', 16)->nullable()->after('effect');
        });
    }

    public function down(): void
    {
        Schema::table('permission_grants', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
