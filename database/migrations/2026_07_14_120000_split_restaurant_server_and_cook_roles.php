<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * La cuisine ne communique plus directement avec la salle : on sépare le rôle
 * « Serveur/Cuisinier » historique en deux métiers distincts.
 *
 *  - restaurant_staff garde son slug mais devient « Serveur (salle) » : il prend
 *    les commandes, reçoit celles du portail et fait la navette avec la cuisine.
 *  - restaurant_cook (nouveau) est la cuisine : elle reçoit les bons de commande
 *    et signale les plats prêts.
 *
 * Passe par une migration (et pas seulement le seeder) pour que les
 * établissements déjà déployés reçoivent le changement au prochain déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'restaurant_staff')
            ->update([
                'name' => 'Serveur (salle)',
                'description' => 'Service en salle : prise de commande, navette avec la cuisine, service des plats',
            ]);

        $now = Carbon::now();

        // Un rôle par établissement : on clone la ligne cuisinier pour chaque
        // tenant qui possède déjà un chef, plus l'éventuel rôle global (tenant_id null).
        $hasTenantColumn = DB::getSchemaBuilder()->hasColumn('roles', 'tenant_id');

        if ($hasTenantColumn) {
            $tenantIds = DB::table('roles')
                ->where('slug', 'restaurant_chief')
                ->pluck('tenant_id')
                ->unique();

            foreach ($tenantIds as $tenantId) {
                $exists = DB::table('roles')
                    ->where('slug', 'restaurant_cook')
                    ->where('tenant_id', $tenantId)
                    ->exists();

                if (!$exists) {
                    DB::table('roles')->insert([
                        'name' => 'Cuisinier (cuisine)',
                        'slug' => 'restaurant_cook',
                        'description' => 'Cuisine : réception des bons de commande et signalement des plats prêts',
                        'tenant_id' => $tenantId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // Filet de sécurité : au moins un rôle cuisinier existe, même sur une base
        // sans colonne tenant_id ou sans chef pré-existant.
        if (!DB::table('roles')->where('slug', 'restaurant_cook')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Cuisinier (cuisine)',
                'slug' => 'restaurant_cook',
                'description' => 'Cuisine : réception des bons de commande et signalement des plats prêts',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'restaurant_cook')->delete();

        DB::table('roles')
            ->where('slug', 'restaurant_staff')
            ->update([
                'name' => 'Serveur/Cuisinier',
                'description' => 'Personnel de restaurant',
            ]);
    }
};
