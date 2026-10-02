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
 * La hiérarchie suit celle d'un hôtel :
 *
 *   1  Administrateur — le service informatique : il administre l'application
 *      et consulte tous les services, sans y saisir d'opération.
 *   2  Manager — la direction des opérations : il supervise tous les services
 *      et valide les décisions qui engagent l'établissement.
 *   3  Chefs de service — ils dirigent leur service et font ce que font leurs
 *      membres (« includes »).
 *   4  Membres — ils exécutent le travail de leur service.
 *
 * Hors hiérarchie (niveau nul) : le contrôle — contrôleur de gestion, auditeur
 * qualité —, qui lit sans jamais participer, et le portail client.
 *
 * Le module d'un rôle est le service auquel il appartient.
 *
 * Statut d'un rôle :
 *  - actif : en service, assignable s'il n'est pas privilégié ;
 *  - en préparation : défini, droits compris, mais pas encore proposé — des
 *    écrans demandent encore les rôles par leur nom et ne le connaîtraient
 *    pas, il n'aurait qu'une partie de ses droits ;
 *  - retiré : plus proposé. Qui le porte le garde ; il ne donne aucun droit.
 */
class RoleCatalog
{
    public const ADMIN = 'admin';

    public const MANAGER = 'manager';

    /** Compte technique du support de l'éditeur, ouvert par le mode assistance. */
    public const SUPPORT = 'support';

    public const ACTIF = 'actif';

    public const EN_PREPARATION = 'en_preparation';

    public const RETIRE = 'retire';

    /** Colonnes de la table roles : la synchronisation n'écrit que celles-là. */
    private const COLONNES = ['name', 'slug', 'description', 'module', 'icon', 'sort_order', 'is_assignable'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ── Niveau 1 : administration ──
            // Créé depuis la console d'orchestration uniquement : personne,
            // dans l'établissement, n'accorde un niveau égal au sien.
            [
                'name' => 'Administrateur',
                'slug' => self::ADMIN,
                'description' => 'Service informatique : comptes, rôles, droits et paramètres ; consulte tous les services',
                'module' => 'it', 'icon' => 'monitor-cog', 'sort_order' => 1, 'is_assignable' => false,
                'level' => 1, 'statut' => self::ACTIF,
            ],

            // ── Niveau 2 : direction ──
            [
                'name' => 'Manager',
                'slug' => self::MANAGER,
                'description' => 'Direction des opérations : supervise tous les services et valide les décisions',
                'module' => 'direction', 'icon' => 'crown', 'sort_order' => 2, 'is_assignable' => false,
                'level' => 2, 'statut' => self::ACTIF,
            ],

            // ── Hébergement ──
            // Il n'y a pas de caissier à l'hébergement : le réceptionniste encaisse.
            [
                'name' => 'Chef de réception',
                'slug' => 'reception_chief',
                'description' => 'Encadre la réception : réservations, séjours et caisse de la réception',
                'module' => 'hebergement', 'icon' => 'bell-ring', 'sort_order' => 9, 'is_assignable' => true,
                'level' => 3, 'includes' => ['reception'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Réceptionniste',
                'slug' => 'reception',
                'description' => 'Accueil, réservations, séjours et encaissements de la réception',
                'module' => 'hebergement', 'icon' => 'concierge-bell', 'sort_order' => 10, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Housekeeping ──
            [
                'name' => 'Gouvernant(e) général(e)',
                'slug' => 'housekeeping_leader',
                'description' => 'Dirige les étages : affectation des chambres, contrôle de la propreté, incidents',
                'module' => 'housekeeping', 'icon' => 'sparkles', 'sort_order' => 20, 'is_assignable' => true,
                'level' => 3, 'includes' => ['housekeeping_staff'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Valet / Femme de chambre',
                'slug' => 'housekeeping_staff',
                'description' => 'Nettoyage et remise en état des chambres',
                'module' => 'housekeeping', 'icon' => 'brush-cleaning', 'sort_order' => 21, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Restaurant ──
            [
                'name' => 'Responsable de restaurant',
                'slug' => 'restaurant_manager',
                'description' => 'Dirige la salle : service, carte et encaissements si nécessaire',
                'module' => 'restaurant', 'icon' => 'store', 'sort_order' => 29, 'is_assignable' => true,
                'level' => 3, 'includes' => ['restaurant_staff', 'cashier'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Chef de cuisine',
                'slug' => 'restaurant_chief',
                'description' => 'Dirige la cuisine : carte, fiches techniques, garde-manger, inventaires et production',
                'module' => 'restaurant', 'icon' => 'chef-hat', 'sort_order' => 30, 'is_assignable' => true,
                'level' => 3, 'includes' => ['restaurant_cook'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Serveur (salle)',
                'slug' => 'restaurant_staff',
                'description' => 'Service en salle : prise de commande, navette avec la cuisine, service des plats',
                'module' => 'restaurant', 'icon' => 'utensils', 'sort_order' => 31, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Cuisinier (cuisine)',
                'slug' => 'restaurant_cook',
                'description' => 'Cuisine : réception des bons de commande et signalement des plats prêts',
                'module' => 'restaurant', 'icon' => 'cooking-pot', 'sort_order' => 32, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],
            // Le slug historique « cashier » désigne désormais le caissier du
            // restaurant, que ses droits décrivaient déjà : le garder évite de
            // reprendre les comptes qui le portent.
            [
                'name' => 'Caissier restaurant',
                'slug' => 'cashier',
                'description' => 'Encaissement des additions du restaurant',
                'module' => 'restaurant', 'icon' => 'calculator', 'sort_order' => 33, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Boutique ──
            [
                'name' => 'Responsable boutique',
                'slug' => 'shop_manager',
                'description' => 'Gestion des articles culturels et stocks boutique',
                'module' => 'boutique', 'icon' => 'shopping-bag', 'sort_order' => 40, 'is_assignable' => true,
                'level' => 3, 'includes' => ['shop_cashier'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Vendeur-caissier',
                'slug' => 'shop_cashier',
                'description' => 'Ventes et encaissements boutique',
                'module' => 'boutique', 'icon' => 'shopping-cart', 'sort_order' => 41, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Économat ──
            [
                'name' => 'Chef économe',
                'slug' => 'econome',
                'description' => 'Gestion du magasin central : stock, fournisseurs, bons de commande et demandes des départements',
                'module' => 'economat', 'icon' => 'warehouse', 'sort_order' => 50, 'is_assignable' => true,
                'level' => 3, 'includes' => ['storekeeper'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Magasinier',
                'slug' => 'storekeeper',
                'description' => 'Réception des livraisons, sorties et comptages du magasin central',
                'module' => 'economat', 'icon' => 'package-check', 'sort_order' => 51, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Comptabilité & finances ──
            [
                'name' => 'Responsable administratif et financier',
                'slug' => 'finance_manager',
                'description' => 'Dirige la comptabilité et les finances : écritures, clôtures, contrôle des caisses',
                'module' => 'comptabilite', 'icon' => 'landmark', 'sort_order' => 59, 'is_assignable' => true,
                'level' => 3, 'includes' => ['accountant'], 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Comptable',
                'slug' => 'accountant',
                'description' => 'Écritures, clôtures et contrôle des comptages de caisse',
                'module' => 'comptabilite', 'icon' => 'wallet', 'sort_order' => 60, 'is_assignable' => true,
                'level' => 4, 'statut' => self::ACTIF,
            ],

            // ── Contrôle : hors hiérarchie, lecture seule ──
            [
                'name' => 'Contrôleur de gestion',
                'slug' => 'controller',
                'description' => 'Contrôle et audit interne — vue sur tous les services, aucune écriture',
                'module' => 'comptabilite', 'icon' => 'shield-check', 'sort_order' => 41, 'is_assignable' => true,
                'level' => null, 'statut' => self::ACTIF,
            ],
            [
                'name' => 'Contrôleur Qualité & Audit',
                'slug' => 'quality_auditor',
                'description' => 'Audit des normes d’hygiène, qualité et conformité',
                'module' => 'qualite', 'icon' => 'award', 'sort_order' => 90, 'is_assignable' => true,
                'level' => null, 'statut' => self::ACTIF,
            ],

            // ── Portail client ──
            [
                'name' => 'Client',
                'slug' => 'customer_guest',
                'description' => 'Accès client au portail client',
                'module' => 'portail', 'icon' => 'user', 'sort_order' => 99, 'is_assignable' => false,
                'level' => null, 'statut' => self::ACTIF,
            ],

            // ── Support Wetchah ──
            // Compte technique, un par établissement, que seul le mode
            // assistance de la console ouvre — jamais un mot de passe. Il
            // consulte pour diagnostiquer et n'écrit rien ; ses sessions sont
            // visibles par l'hôtel. Le support n'entre plus sous le compte de
            // l'administrateur.
            [
                'name' => 'Support Wetchah',
                'slug' => self::SUPPORT,
                'description' => "Support technique de l'éditeur : consulte pour diagnostiquer, n'écrit rien ; ouvert par le mode assistance",
                'module' => 'it', 'icon' => 'life-buoy', 'sort_order' => 98, 'is_assignable' => false,
                'level' => null, 'statut' => self::ACTIF,
            ],

            // ── Retirés ──
            // Les ressources humaines deviennent une plateforme sœur de
            // l'application ; l'administrateur appartient déjà au service
            // informatique. Ni l'un ni l'autre ne portait de droit.
            [
                'name' => 'Responsable RH',
                'slug' => 'rh_manager',
                'description' => 'Retiré : les ressources humaines relèvent d\'une plateforme dédiée',
                'module' => 'rh', 'icon' => 'users', 'sort_order' => 70, 'is_assignable' => false,
                'level' => null, 'statut' => self::RETIRE,
            ],
            [
                'name' => 'Technicien IT',
                'slug' => 'it_support',
                'description' => 'Retiré : le service informatique administre l\'application sous le rôle Administrateur',
                'module' => 'it', 'icon' => 'laptop', 'sort_order' => 80, 'is_assignable' => false,
                'level' => null, 'statut' => self::RETIRE,
            ],
        ];
    }

    /**
     * Ligne de la table roles pour un rôle du référentiel — ses seules
     * colonnes en base —, ou null s'il n'y figure pas.
     *
     * @return array<string, mixed>|null
     */
    public static function enregistrement(string $slug): ?array
    {
        $definition = self::find($slug);

        return $definition === null ? null : array_intersect_key($definition, array_flip(self::COLONNES));
    }

    /** Définition d'un rôle, ou null s'il n'est pas au référentiel. */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $definition) {
            if ($definition['slug'] === $slug) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Ces rôles et tous ceux qu'ils incluent, en suivant les inclusions en
     * chaîne : ce qu'une personne fait réellement avec ces rôles.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public static function developper(array $slugs): array
    {
        $inclusions = self::inclusions();
        $resultat = [];
        $aVisiter = array_values($slugs);

        while ($aVisiter !== []) {
            $slug = array_pop($aVisiter);

            if (in_array($slug, $resultat, true)) {
                continue;
            }

            $resultat[] = $slug;
            array_push($aVisiter, ...($inclusions[$slug] ?? []));
        }

        return $resultat;
    }

    /**
     * Ces rôles et tous ceux qui les incluent, en chaîne : les détenteurs
     * d'un droit accordé à ces rôles, chefs compris.
     *
     * @param  list<string>  $slugs
     * @return list<string>
     */
    public static function avecCeuxQuiLesIncluent(array $slugs): array
    {
        $resultat = array_values(array_unique($slugs));

        foreach (array_keys(self::inclusions()) as $chef) {
            if (! in_array($chef, $resultat, true)
                && array_intersect(self::developper([$chef]), $slugs) !== []) {
                $resultat[] = $chef;
            }
        }

        return $resultat;
    }

    /** @return array<string, list<string>> rôle => rôles qu'il inclut directement */
    private static function inclusions(): array
    {
        $inclusions = [];

        foreach (self::all() as $definition) {
            if (! empty($definition['includes'])) {
                $inclusions[$definition['slug']] = $definition['includes'];
            }
        }

        return $inclusions;
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
            // Niveau, inclusions et statut vivent dans le code : la table ne
            // porte que ce que les écrans et la console lisent en base.
            $colonnes = self::enregistrement($definition['slug']);
            $role = Role::where('slug', $definition['slug'])->first();

            if ($role) {
                $role->fill($colonnes)->save();
                $updated++;
            } else {
                Role::create($colonnes);
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
