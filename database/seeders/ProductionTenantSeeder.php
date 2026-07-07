<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Exécuté au premier démarrage d'un établissement provisionné par
 * erp_pms (voir docker/entrypoint.sh) — remplace DatabaseSeeder (jeu de
 * données de démo "Villa Boutanga" : tenant, utilisateurs, chambres,
 * clients, réservations…) par les seuls rôles RBAC nécessaires au
 * fonctionnement de l'app, plus le tenant réel de cet établissement,
 * construit à partir des variables d'environnement injectées par
 * TenantProvisioningService::generateDockerCompose().
 *
 * Aucun utilisateur n'est créé ici : le premier manager est créé depuis
 * l'admin (erp_pms), qui écrit directement dans cette même base.
 */
class ProductionTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $settings = json_decode((string) env('TENANT_SETTINGS', '{}'), true) ?: [];

        Tenant::updateOrCreate(
            ['slug' => env('TENANT_SLUG', 'default')],
            [
                'name' => env('APP_NAME', 'Établissement'),
                'currency' => env('TENANT_CURRENCY', 'XAF'),
                'settings' => $settings,
                'is_active' => true,
            ]
        );
    }
}
