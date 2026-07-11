<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes référencées depuis longtemps par le modèle RoomType ($fillable,
 * $casts) et exposées par l'API publique du site vitrine (RoomTypeResource :
 * bed_configuration, photos), mais jamais créées en base — l'API renvoyait
 * silencieusement null. Découvert lors de l'ajout de l'import CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('bed_configuration')->nullable()->after('size_sqm');
            $table->json('photos')->nullable()->after('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['bed_configuration', 'photos']);
        });
    }
};
