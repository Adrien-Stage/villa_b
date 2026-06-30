<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            // Rôles globaux (tenant_id = null)
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrateur global - accès complet à tous les hôtels',
                ],

            // Rôles par hôtel (tenant-specific)
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Directeur d\'hôtel - gestion complète de l\'établissement',
                // Sera créé pour chaque tenant
            ],
            [
                'name' => 'Réceptionniste',
                'slug' => 'reception',
                'description' => 'Accueil et gestion des réservations',
                ],
            [
                'name' => 'Chef d\'équipe Housekeeping',
                'slug' => 'housekeeping_leader',
                'description' => 'Superviseur du service ménage',
                ],
            [
                'name' => 'Équipe Housekeeping',
                'slug' => 'housekeeping_staff',
                'description' => 'Personnel de ménage',
                ],
            [
                'name' => 'Chef cuisinier',
                'slug' => 'restaurant_chief',
                'description' => 'Responsable de la cuisine et restaurant',
                ],
            [
                'name' => 'Serveur/Cuisinier',
                'slug' => 'restaurant_staff',
                'description' => 'Personnel de restaurant',
                ],
            [
                'name' => 'Caissier',
                'slug' => 'cashier',
                'description' => 'Gestion des encaissements et facturation',
                ],
            [
                'name' => 'Comptable',
                'slug' => 'accountant',
                'description' => 'Service comptabilité et rapports financiers',
                ],
            [
                'name' => 'Client',
                'slug' => 'customer_guest',
                'description' => 'Accès client au portail client',
                ],
            [
                'name' => 'Gérant Boutique',
                'slug' => 'shop_manager',
                'description' => 'Gestion des articles culturels et stocks boutique',
                ],
            [
                'name' => 'Caissier Boutique',
                'slug' => 'shop_cashier',
                'description' => 'Ventes et encaissements boutique',
                ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }
    }
}
