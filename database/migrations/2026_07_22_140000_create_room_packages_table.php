<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packs d'hébergement : formules vendues avec la chambre (demi-pension,
 * pension complète, séjour affaires avec blanchisserie…).
 *
 * Un pack regroupe des repas et/ou des prestations du catalogue, facturés
 * forfaitairement à un tarif inférieur à la somme des éléments pris
 * séparément — c'est l'intérêt commercial de la formule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_packages', function (Blueprint $table) {
            $table->id();

            $table->string('name', 140);
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();

            // --- COMPOSITION ---
            // Repas inclus : sous-ensemble de RestaurantMenuItem::MEAL_SERVICES
            // (breakfast, lunch, dinner).
            $table->json('meals')->nullable();
            // Prestations du catalogue incluses (blanchisserie, spa, navette…).
            $table->json('service_item_ids')->nullable();

            // --- TARIFICATION ---
            // 'per_person_night' : par personne et par nuitée (usage hôtelier
            //   courant pour la pension) ; 'per_room_night' : par chambre et
            //   par nuitée ; 'per_stay' : forfait pour tout le séjour.
            $table->string('pricing_mode', 20)->default('per_person_night');
            // Montant facturé au client, en centimes FCFA.
            $table->unsignedBigInteger('price')->default(0);

            // --- REMISE HÉBERGEMENT ASSOCIÉE ---
            // Certaines formules s'accompagnent d'un geste sur la nuitée elle-même.
            // 'none' | 'percent' | 'amount' (montant par nuitée, en centimes).
            $table->string('room_discount_type', 10)->default('none');
            $table->unsignedBigInteger('room_discount_value')->default(0);

            // --- PORTÉE ---
            // Types de chambre éligibles. Null ou vide = toutes les chambres :
            // on évite ainsi de devoir re-cocher chaque type à la création.
            $table->json('room_type_ids')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
            $table->index(['sort_order']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('room_package_id')->nullable()->after('partner_organization_id')
                ->constrained('room_packages')->nullOnDelete();
            // Montant du pack figé au moment de la réservation : le tarif du
            // pack peut évoluer, la facture du séjour ne doit pas bouger.
            $table->unsignedBigInteger('package_amount')->default(0)->after('extras_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_package_id');
            $table->dropColumn('package_amount');
        });

        Schema::dropIfExists('room_packages');
    }
};
