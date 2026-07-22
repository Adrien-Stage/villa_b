<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisations partenaires de l'établissement (entreprises, ONG, ambassades,
 * agences de voyage, institutions) et privilèges négociés avec chacune.
 *
 * Un client déclaré membre d'une organisation se voit appliquer ces privilèges
 * automatiquement lors de sa réservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_organizations', function (Blueprint $table) {
            $table->id();

            // --- IDENTITÉ ---
            $table->string('name', 160);
            // Code court saisi par la réception ou figurant sur la convention
            // (ex. « ONU-CM »). Sert de repère rapide, pas de clé technique.
            $table->string('code', 30)->nullable();
            // 'company', 'ngo', 'embassy', 'travel_agency', 'institution', 'other'
            $table->string('type', 30)->default('company');

            // --- CONTACT ---
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();

            // --- VALIDITÉ DU PARTENARIAT ---
            // Une convention a une durée : hors de cette fenêtre, les privilèges
            // ne s'appliquent plus, sans qu'on ait à supprimer l'organisation
            // (on garde l'historique des séjours qui y sont rattachés).
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);

            // --- PRIVILÈGE : REMISE HÉBERGEMENT ---
            // 'none' | 'percent' | 'amount'
            $table->string('room_discount_type', 10)->default('none');
            // Pourcentage (0-100) si 'percent', montant en centimes FCFA par
            // nuitée si 'amount'. Une seule colonne : le type lève l'ambiguïté.
            $table->unsignedBigInteger('room_discount_value')->default(0);

            // --- PRIVILÈGE : REMISES AUTRES PÔLES ---
            $table->unsignedTinyInteger('restaurant_discount_percent')->default(0);
            $table->unsignedTinyInteger('shop_discount_percent')->default(0);

            // --- PRIVILÈGE : PRESTATIONS OFFERTES ---
            // Identifiants du catalogue service_items offerts aux membres.
            // Stocké en JSON : la liste est courte et purement descriptive,
            // une table pivot n'apporterait rien ici.
            $table->json('free_service_item_ids')->nullable();

            // --- PRIVILÈGE : ARRANGEMENTS HORAIRES ---
            $table->boolean('late_checkout')->default(false);
            $table->boolean('early_checkin')->default(false);

            $table->text('notes')->nullable();

            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
            $table->index(['name']);
        });

        // Appartenance mémorisée sur la fiche client : renseignée une fois,
        // l'organisation est reproposée à chaque nouvelle réservation.
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('partner_organization_id')->nullable()->after('country')
                ->constrained('partner_organizations')->nullOnDelete();
        });

        // Organisation réellement retenue pour CE séjour : elle peut différer
        // de celle du client (mission pour un autre organisme, ou séjour privé
        // sans privilège). C'est elle qui justifie la remise accordée.
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('partner_organization_id')->nullable()->after('booker_id')
                ->constrained('partner_organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_organization_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_organization_id');
        });

        Schema::dropIfExists('partner_organizations');
    }
};
