<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            // Le serveur responsable de la commande : il transmet le bon à la
            // cuisine puis apporte le plat à la table. Affecté automatiquement pour
            // les commandes du portail, ou par le serveur qui saisit la commande.
            $table->foreignId('assigned_server_id')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            // Jalons du parcours salle ↔ cuisine, pour les notifications et les délais.
            $table->timestamp('assigned_at')->nullable()->after('placed_at');
            $table->timestamp('sent_to_kitchen_at')->nullable()->after('assigned_at');
            $table->timestamp('ready_at')->nullable()->after('sent_to_kitchen_at');
            $table->timestamp('served_at')->nullable()->after('ready_at');

            $table->index(['assigned_server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_customer_orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_server_id']);
            $table->dropIndex(['assigned_server_id', 'status']);
            $table->dropColumn([
                'assigned_server_id',
                'assigned_at',
                'sent_to_kitchen_at',
                'ready_at',
                'served_at',
            ]);
        });
    }
};
