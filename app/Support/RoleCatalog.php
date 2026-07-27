<?php

namespace App\Support;

use App\Models\Role;

/**
 * Référentiel des rôles de l'établissement — source unique de vérité.
 *
 * Ajouter un rôle ici suffit : il est créé en base au prochain démarrage de
 * l'application (commande roles:sync lancée par l'entrypoint après les
 * migrations) et apparaît aussitôt dans la rubrique Utilisateurs, qui lit la
 * table plutôt qu'une liste codée en dur.
 *
 * Chaque rôle porte le module métier qu'il couvre, son icône Lucide et s'il
 * est assignable par un manager (les rôles privilégiés ne le sont pas).
 */
class RoleCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Rôles privilégiés : non assignables depuis la rubrique staff ──
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrateur global - accès complet à tous les hôtels',
                'module' => 'direction', 'icon' => 'shield', 'sort_order' => 1, 'is_assignable' => false,
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Directeur d\'hôtel - gestion complète de l\'établissement',
                'module' => 'direction', 'icon' => 'crown', 'sort_order' => 2, 'is_assignable' => false,
            ],
            [
                'name' => 'Client',
                'slug' => 'customer_guest',
                'description' => 'Accès client au portail client',
                'module' => 'portail', 'icon' => 'user', 'sort_order' => 99, 'is_assignable' => false,
            ],

            // ── Hébergement ──
            [
                'name' => 'Réceptionniste',
                'slug' => 'reception',
                'description' => 'Accueil et gestion des réservations',
                'module' => 'hebergement', 'icon' => 'concierge-bell', 'sort_order' => 10, 'is_assignable' => true,
            ],
            [
                'name' => 'Caissier',
                'slug' => 'cashier',
                'description' => 'Gestion des encaissements et facturation',
                'module' => 'hebergement', 'icon' => 'calculator', 'sort_order' => 11, 'is_assignable' => true,
            ],

            // ── Housekeeping ──
            [
                'name' => 'Chef d\'équipe Housekeeping',
                'slug' => 'housekeeping_leader',
                'description' => 'Superviseur du service ménage',
                'module' => 'housekeeping', 'icon' => 'sparkles', 'sort_order' => 20, 'is_assignable' => true,
            ],
            [
                'name' => 'Équipe Housekeeping',
                'slug' => 'housekeeping_staff',
                'description' => 'Personnel de ménage',
                'module' => 'housekeeping', 'icon' => 'brush-cleaning', 'sort_order' => 21, 'is_assignable' => true,
            ],

            // ── Restaurant ──
            [
                'name' => 'Chef cuisinier',
                'slug' => 'restaurant_chief',
                'description' => 'Responsable de la cuisine et restaurant',
                'module' => 'restaurant', 'icon' => 'chef-hat', 'sort_order' => 30, 'is_assignable' => true,
            ],
            [
                'name' => 'Serveur (salle)',
                'slug' => 'restaurant_staff',
                'description' => 'Service en salle : prise de commande, navette avec la cuisine, service des plats',
                'module' => 'restaurant', 'icon' => 'utensils', 'sort_order' => 31, 'is_assignable' => true,
            ],
            [
                'name' => 'Cuisinier (cuisine)',
                'slug' => 'restaurant_cook',
                'description' => 'Cuisine : réception des bons de commande et signalement des plats prêts',
                'module' => 'restaurant', 'icon' => 'cooking-pot', 'sort_order' => 32, 'is_assignable' => true,
            ],

            // ── Boutique ──
            [
                'name' => 'Gérant Boutique',
                'slug' => 'shop_manager',
                'description' => 'Gestion des articles culturels et stocks boutique',
                'module' => 'boutique', 'icon' => 'shopping-bag', 'sort_order' => 40, 'is_assignable' => true,
            ],
            [
                'name' => 'Caissier Boutique',
                'slug' => 'shop_cashier',
                'description' => 'Ventes et encaissements boutique',
                'module' => 'boutique', 'icon' => 'shopping-cart', 'sort_order' => 41, 'is_assignable' => true,
            ],

            // ── Économat ──
            [
                'name' => 'Économe',
                'slug' => 'econome',
                'description' => 'Gestion du magasin central : stock, fournisseurs, bons de commande et demandes des départements',
                'module' => 'economat', 'icon' => 'warehouse', 'sort_order' => 50, 'is_assignable' => true,
            ],

            // ── Comptabilité ──
            [
                'name' => 'Comptable',
                'slug' => 'accountant',
                'description' => 'Service comptabilité et rapports financiers',
                'module' => 'comptabilite', 'icon' => 'wallet', 'sort_order' => 60, 'is_assignable' => true,
            ],
        ];
    }

    /**
     * Aligne la table des rôles sur le référentiel. Idempotent : peut être
     * relancé à chaque démarrage sans créer de doublon ni écraser les
     * rattachements d'utilisateurs (on ne touche jamais au pivot).
     *
     * @return array{created: int, updated: int}
     */
    public static function sync(): array
    {
        $created = 0;
        $updated = 0;

        foreach (self::all() as $definition) {
            $role = Role::where('slug', $definition['slug'])->first();

            if ($role) {
                $role->fill($definition)->save();
                $updated++;
            } else {
                Role::create($definition);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
