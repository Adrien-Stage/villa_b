<?php

namespace App\Support;

use App\Models\User;
use App\Services\PermissionResolver;
use App\Services\RestaurantContext;
use Illuminate\Support\Facades\Auth;

/**
 * Gestionnaire de la navigation de l'application Wetchah :
 * - Groupes de fonctionnalités pour le lanceur d'applications (style Odoo).
 * - Détermination du groupe actif et du mode de navigation (sidebar, tabs, standalone).
 * - Filtres stricts selon les rôles, permissions et modules activés.
 */
class Navigation
{
    public const TYPE_SIDEBAR    = 'sidebar';
    public const TYPE_TABS       = 'tabs';
    public const TYPE_STANDALONE = 'standalone';

    /**
     * Liste des groupes d'applications autorisés pour l'utilisateur connecté.
     * Alimente la grille du lanceur d'applications (page principale après connexion).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getLauncherGroups(?User $user = null): array
    {
        $user = $user ?? Auth::user();
        if (!$user || !$user->is_active) {
            return [];
        }

        $droits = app(PermissionResolver::class);
        $estComptable = $user->exerce(['accountant']);
        $groups = [];

        // 1. HÔTEL (Chambres, réservations, agenda, housekeeping)
        $peutVoirHotel = !$estComptable && (
            $user->isAdmin() ||
            collect(['rooms.voir', 'bookings.voir', 'agenda.voir', 'housekeeping.voir', 'customers.voir'])
                ->contains(fn ($d) => $droits->allows($user, $d))
        );
        if ($peutVoirHotel) {
            $groups[] = [
                'id'          => 'hotel',
                'title'       => 'Hôtel',
                'subtitle'    => 'Chambres, réservations & housekeeping',
                'icon'        => 'bed-double',
                'color'       => 'indigo',
                'bg_gradient' => 'from-indigo-500 to-blue-600',
                'text_color'  => 'text-indigo-600',
                'bg_soft'     => 'bg-indigo-50',
                'border_color'=> 'border-indigo-100',
                // La tuile ouvre le premier écran que la personne peut voir :
                // le housekeeping n'a pas les Chambres, il arrive à son tableau.
                'url'         => self::premiereRoutePermise($user, [
                    'rooms.index', 'bookings.index', 'agenda.index', 'housekeeping.index', 'reception.pos.index',
                ]) ?? route('rooms.index'),
                'type'        => self::TYPE_SIDEBAR,
            ];
        }

        // 2. RESTAURANT
        $peutVoirResto = TenantModules::has('restaurant') && (
            $user->isAdmin() ||
            $user->exerce(['manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'cashier'])
        );
        if ($peutVoirResto) {
            $groups[] = [
                'id'          => 'restaurant',
                'title'       => 'Restaurant',
                'subtitle'    => 'Commandes, cuisine, bar & caisse',
                'icon'        => 'utensils',
                'color'       => 'amber',
                'bg_gradient' => 'from-amber-500 to-orange-600',
                'text_color'  => 'text-amber-600',
                'bg_soft'     => 'bg-amber-50',
                'border_color'=> 'border-amber-100',
                'url'         => self::premiereRoutePermise($user, [
                    'restaurant.orders.index', 'restaurant.kitchen.index', 'restaurant.bar.index', 'restaurant.billing.index',
                    'restaurant.breakfast.index', 'restaurant.banquets.index', 'restaurant.menus.index', 'restaurant.restaurants.index',
                ]) ?? route('restaurant.orders.index'),
                'type'        => self::TYPE_SIDEBAR,
            ];
        }

        // 3. ÉCONOMAT — à qui tient le magasin. Les services qui ne font qu'y
        // adresser des demandes les font depuis leur propre application.
        if (self::tientLEconomat($user)) {
            $groups[] = [
                'id'          => 'economat',
                'title'       => 'Économat',
                'subtitle'    => 'Stocks, achats & inventaires',
                'icon'        => 'warehouse',
                'color'       => 'emerald',
                'bg_gradient' => 'from-emerald-500 to-teal-600',
                'text_color'  => 'text-emerald-600',
                'bg_soft'     => 'bg-emerald-50',
                'border_color'=> 'border-emerald-100',
                'url'         => self::premiereRoutePermise($user, [
                    'economat.index', 'economat.items.index', 'economat.orders.index', 'economat.receipts.index',
                    'economat.stock_counts.index', 'economat.suppliers.index', 'economat.movements.index',
                ]) ?? route('economat.index'),
                'type'        => self::TYPE_SIDEBAR,
            ];
        }

        // 4. COMPTABILITÉ
        $peutVoirCompta = $user->isAdmin() || collect(['accounting.voir', 'accounting.ledger.voir', 'rooms.cost_sheets.voir'])
            ->contains(fn ($d) => $droits->allows($user, $d));
        if ($peutVoirCompta) {
            $groups[] = [
                'id'          => 'accounting',
                'title'       => 'Comptabilité',
                'subtitle'    => 'Grand livre, écritures & finances',
                'icon'        => 'landmark',
                'color'       => 'purple',
                'bg_gradient' => 'from-purple-500 to-violet-600',
                'text_color'  => 'text-purple-600',
                'bg_soft'     => 'bg-purple-50',
                'border_color'=> 'border-purple-100',
                'url'         => self::premiereRoutePermise($user, [
                    'accounting.index', 'accounting.ledger.index', 'rooms.cost_sheets.index',
                ]) ?? route('accounting.index'),
                'type'        => self::TYPE_SIDEBAR,
            ];
        }

        // 5. GESTION (Clients & Utilisateurs) — Groupe léger (Onglets)
        $peutVoirGestion = $user->isAdmin() || $user->exerce(['manager', 'reception', 'cashier']);
        if ($peutVoirGestion) {
            $urlGestion = (!auth()->check() || $user->hasModuleAccess('clients'))
                ? route('customers.index')
                : route('users.index');

            $groups[] = [
                'id'          => 'gestion',
                'title'       => 'Gestion',
                'subtitle'    => 'Clients & utilisateurs',
                'icon'        => 'users',
                'color'       => 'orange',
                'bg_gradient' => 'from-orange-500 to-amber-600',
                'text_color'  => 'text-orange-600',
                'bg_soft'     => 'bg-orange-50',
                'border_color'=> 'border-orange-100',
                'url'         => $urlGestion,
                'type'        => self::TYPE_TABS,
            ];
        }

        // 6. BOUTIQUE — Groupe léger (Onglets)
        $peutVoirShop = TenantModules::has('shop') && (
            $user->isAdmin() || $user->exerce(['shop_manager', 'shop_cashier', 'manager'])
        );
        if ($peutVoirShop) {
            $groups[] = [
                'id'          => 'shop',
                'title'       => 'Boutique',
                'subtitle'    => 'Articles, commandes & caisse',
                'icon'        => 'shopping-bag',
                'color'       => 'rose',
                'bg_gradient' => 'from-rose-500 to-pink-600',
                'text_color'  => 'text-rose-600',
                'bg_soft'     => 'bg-rose-50',
                'border_color'=> 'border-rose-100',
                'url'         => self::premiereRoutePermise($user, ['shop.products.index', 'shop.orders.index'])
                    ?? route('shop.products.index'),
                'type'        => self::TYPE_TABS,
            ];
        }

        // 7. ÉDITIONS (Impressions & Rapports centraux) — Pleine page
        $catalogueEditions = app(\App\Editions\Catalogue::class);
        if ($catalogueEditions->pour($user)->isNotEmpty()) {
            $groups[] = [
                'id'          => 'editions',
                'title'       => 'Éditions',
                'subtitle'    => 'États imprimables, bilans & exports',
                'icon'        => 'printer',
                'color'       => 'cyan',
                'bg_gradient' => 'from-cyan-500 to-blue-600',
                'text_color'  => 'text-cyan-600',
                'bg_soft'     => 'bg-cyan-50',
                'border_color'=> 'border-cyan-100',
                'url'         => route('editions.index'),
                'type'        => self::TYPE_STANDALONE,
            ];
        }

        // 8. PLANNING DES QUARTS — Pleine page
        $groups[] = [
            'id'          => 'planning',
            'title'       => 'Planning',
            'subtitle'    => 'Quarts de travail & présences',
            'icon'        => 'calendar-clock',
            'color'       => 'sky',
            'bg_gradient' => 'from-sky-500 to-indigo-600',
            'text_color'  => 'text-sky-600',
            'bg_soft'     => 'bg-sky-50',
            'border_color'=> 'border-sky-100',
            'url'         => route('planning.index'),
            'type'        => self::TYPE_STANDALONE,
        ];

        // 9. ANALYTIQUE (Tour de contrôle) — Pleine page
        $peutVoirAnalytics = TenantModules::has('analytics') && (
            $user->isAdmin() || $droits->allows($user, 'analytics.voir')
        );
        if ($peutVoirAnalytics) {
            $groups[] = [
                'id'          => 'analytics',
                'title'       => 'Analytique',
                'subtitle'    => 'Tour de contrôle & indicateurs KPI',
                'icon'        => 'bar-chart-2',
                'color'       => 'teal',
                'bg_gradient' => 'from-teal-500 to-emerald-600',
                'text_color'  => 'text-teal-600',
                'bg_soft'     => 'bg-teal-50',
                'border_color'=> 'border-teal-100',
                'url'         => route('analytics.index'),
                'type'        => self::TYPE_STANDALONE,
            ];
        }

        // 10. ADMINISTRATION — Groupe léger (Onglets)
        $administrationVisible = $user->isAdmin() || collect(['droits.voir', 'interventions.voir', 'audit.voir', 'support.sessions.voir'])
            ->contains(fn ($droit) => $droits->allows($user, $droit));
        if ($administrationVisible) {
            $groups[] = [
                'id'          => 'admin',
                'title'       => 'Administration',
                'subtitle'    => 'Rôles, droits & journal d’audit',
                'icon'        => 'shield-check',
                'color'       => 'slate',
                'bg_gradient' => 'from-slate-700 to-slate-900',
                'text_color'  => 'text-slate-700',
                'bg_soft'     => 'bg-slate-100',
                'border_color'=> 'border-slate-200',
                'url'         => route('droits.index'),
                'type'        => self::TYPE_TABS,
            ];
        }

        // 11. PARAMÈTRES — Pleine page
        // Visible par tous : le contenu s'adapte au rôle et aux accès. La
        // direction et les chefs de service règlent les onglets de
        // l'établissement ; les autres y trouvent leurs paramètres personnels
        // (le contrôleur les mène à leur profil, faute d'onglet à régler).
        $regleLEtablissement = \App\Support\SettingsTabs::parDefaut($user) !== null;
        $groups[] = [
            'id'          => 'settings',
            'title'       => 'Paramètres',
            'subtitle'    => $regleLEtablissement ? 'Configuration de l’établissement' : 'Mon compte & sécurité',
            'icon'        => 'settings',
            'color'       => 'zinc',
            'bg_gradient' => 'from-zinc-500 to-zinc-700',
            'text_color'  => 'text-zinc-600',
            'bg_soft'     => 'bg-zinc-100',
            'border_color'=> 'border-zinc-200',
            'url'         => route('settings.index'),
            'type'        => self::TYPE_STANDALONE,
        ];

        // 12. DISCUSSIONS — Pleine page
        if (TenantModules::has('discussions')) {
            $groups[] = [
                'id'          => 'discussions',
                'title'       => 'Discussions',
                'subtitle'    => 'Messagerie interne d’équipe',
                'icon'        => 'message-circle',
                'color'       => 'emerald',
                'bg_gradient' => 'from-emerald-400 to-teal-500',
                'text_color'  => 'text-emerald-500',
                'bg_soft'     => 'bg-emerald-50',
                'border_color'=> 'border-emerald-100',
                'url'         => route('discussions.index'),
                'type'        => self::TYPE_STANDALONE,
            ];
        }

        // 13. VUE D'ENSEMBLE / ACTIVITÉ (Tableau de bord d'activité des chambres & arrivées)
        $groups[] = [
            'id'          => 'overview',
            'title'       => 'Activité & KPI',
            'subtitle'    => 'Arrivées, départs, chambres & caisse',
            'icon'        => 'layout-dashboard',
            'color'       => 'amber',
            'bg_gradient' => 'from-amber-600 to-yellow-600',
            'text_color'  => 'text-amber-700',
            'bg_soft'     => 'bg-amber-50',
            'border_color'=> 'border-amber-200',
            'url'         => route('dashboard.overview'),
            'type'        => self::TYPE_STANDALONE,
        ];

        return $groups;
    }

    /**
     * Voit-il le magasin ? L'économe, le magasinier, la direction, le
     * contrôle, et qui en a reçu une entrée par la matrice des droits. Les
     * demandes (réquisitions, demandes d'achat) n'en sont pas : les autres
     * services les adressent depuis leur propre application.
     */
    public static function tientLEconomat(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        $droits = app(PermissionResolver::class);

        foreach (PermissionCatalog::droitsDuModule('economat') as $droit) {
            if (str_starts_with($droit, 'economat.requisitions.') || str_starts_with($droit, 'economat.purchase_requests.')
                || !PermissionCatalog::estLecture($droit)) {
                continue;
            }
            if ($droits->allows($user, $droit)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Application où vivent les demandes à l'économat de cette personne :
     * l'Économat pour qui le tient, sinon celle de son service.
     */
    public static function groupeDesDemandes(?User $user): string
    {
        if ($user === null || self::tientLEconomat($user)) {
            return 'economat';
        }

        return match (true) {
            app(PermissionResolver::class)->allows($user, 'accounting.voir') => 'accounting',
            $user->exerce(['housekeeping_staff', 'housekeeping_supervisor', 'reception']) => 'hotel',
            $user->exerce(['restaurant_staff', 'restaurant_cook', 'cashier']) => 'restaurant',
            $user->exerce(['shop_cashier']) => 'shop',
            default => 'economat',
        };
    }

    /**
     * Première de ces routes que la personne peut ouvrir — droit et module —,
     * ou null. Une tuile mène là, plutôt qu'à un écran qui la refuserait.
     *
     * @param  list<string>  $routes
     */
    public static function premiereRoutePermise(User $user, array $routes): ?string
    {
        $droits = app(PermissionResolver::class);
        $modules = ['housekeeping.' => 'housekeeping', 'restaurant.' => 'restaurant', 'shop.' => 'shop'];

        foreach ($routes as $route) {
            if (!\Illuminate\Support\Facades\Route::has($route)) {
                continue;
            }
            foreach ($modules as $prefixe => $module) {
                if (str_starts_with($route, $prefixe) && !TenantModules::has($module)) {
                    continue 2;
                }
            }

            $droit = PermissionCatalog::permissionForRoute($route);
            if (PermissionCatalog::roles($droit) === [] || $droits->allows($user, $droit)) {
                return route($route);
            }
        }

        return null;
    }

    /**
     * Détermine le groupe actif selon la route courante.
     *
     * @return array<string, mixed>|null
     */
    public static function getActiveGroup(?string $routeName = null): ?array
    {
        $routeName = $routeName ?? request()->route()?->getName();
        if (!$routeName) {
            return null;
        }

        $user = Auth::user();
        $estComptable = $user && $user->exerce(['accountant']);

        // Groupes avec Sidebar
        if (str_starts_with($routeName, 'economat.')) {
            // Les demandes à l'économat de qui ne le tient pas vivent dans
            // l'application de son service.
            if (str_starts_with($routeName, 'economat.requisitions.') || str_starts_with($routeName, 'economat.purchase_requests.')) {
                return self::groupInfo(self::groupeDesDemandes($user));
            }
            return self::groupInfo('economat');
        }

        if (str_starts_with($routeName, 'restaurant.') || str_starts_with($routeName, 'portal.restaurant.')) {
            return self::groupInfo('restaurant');
        }

        if (
            str_starts_with($routeName, 'rooms.') ||
            str_starts_with($routeName, 'bookings.') ||
            str_starts_with($routeName, 'invoices.') ||
            str_starts_with($routeName, 'agenda.') ||
            str_starts_with($routeName, 'reception.pos.') ||
            str_starts_with($routeName, 'groups.') ||
            str_starts_with($routeName, 'housekeeping.')
        ) {
            if ($estComptable && $routeName === 'rooms.cost_sheets.index') {
                return self::groupInfo('accounting');
            }
            return self::groupInfo('hotel');
        }

        if (str_starts_with($routeName, 'accounting.')) {
            return self::groupInfo('accounting');
        }

        // Groupes avec Onglets (Tabs)
        if (str_starts_with($routeName, 'customers.') || str_starts_with($routeName, 'users.')) {
            return self::groupInfo('gestion');
        }

        if (str_starts_with($routeName, 'shop.')) {
            return self::groupInfo('shop');
        }

        if (
            str_starts_with($routeName, 'droits.') ||
            str_starts_with($routeName, 'interventions.') ||
            str_starts_with($routeName, 'audit.') ||
            str_starts_with($routeName, 'support.sessions.')
        ) {
            return self::groupInfo('admin');
        }

        // Groupes Standalone (Pleine page)
        if (str_starts_with($routeName, 'editions.')) {
            return self::groupInfo('editions');
        }

        if (str_starts_with($routeName, 'planning.')) {
            return self::groupInfo('planning');
        }

        if (str_starts_with($routeName, 'analytics.')) {
            return self::groupInfo('analytics');
        }

        if (str_starts_with($routeName, 'settings.')) {
            return self::groupInfo('settings');
        }

        if (str_starts_with($routeName, 'discussions.')) {
            return self::groupInfo('discussions');
        }

        if ($routeName === 'dashboard.overview') {
            return self::groupInfo('overview');
        }

        return null;
    }

    /**
     * Métadonnées d'un groupe selon son ID.
     */
    public static function groupInfo(string $groupId): array
    {
        $meta = [
            'hotel' => [
                'id'       => 'hotel',
                'title'    => 'Hôtel',
                'icon'     => 'bed-double',
                'type'     => self::TYPE_SIDEBAR,
            ],
            'restaurant' => [
                'id'       => 'restaurant',
                'title'    => 'Restaurant',
                'icon'     => 'utensils',
                'type'     => self::TYPE_SIDEBAR,
            ],
            'economat' => [
                'id'       => 'economat',
                'title'    => 'Économat',
                'icon'     => 'warehouse',
                'type'     => self::TYPE_SIDEBAR,
            ],
            'accounting' => [
                'id'       => 'accounting',
                'title'    => 'Comptabilité',
                'icon'     => 'landmark',
                'type'     => self::TYPE_SIDEBAR,
            ],
            'gestion' => [
                'id'       => 'gestion',
                'title'    => 'Gestion',
                'icon'     => 'users',
                'type'     => self::TYPE_TABS,
            ],
            'shop' => [
                'id'       => 'shop',
                'title'    => 'Boutique',
                'icon'     => 'shopping-bag',
                'type'     => self::TYPE_TABS,
            ],
            'admin' => [
                'id'       => 'admin',
                'title'    => 'Administration',
                'icon'     => 'shield-check',
                'type'     => self::TYPE_TABS,
            ],
            'editions' => [
                'id'       => 'editions',
                'title'    => 'Éditions',
                'icon'     => 'printer',
                'type'     => self::TYPE_STANDALONE,
            ],
            'planning' => [
                'id'       => 'planning',
                'title'    => 'Planning',
                'icon'     => 'calendar-clock',
                'type'     => self::TYPE_STANDALONE,
            ],
            'analytics' => [
                'id'       => 'analytics',
                'title'    => 'Analytique',
                'icon'     => 'bar-chart-2',
                'type'     => self::TYPE_STANDALONE,
            ],
            'settings' => [
                'id'       => 'settings',
                'title'    => 'Paramètres',
                'icon'     => 'settings',
                'type'     => self::TYPE_STANDALONE,
            ],
            'discussions' => [
                'id'       => 'discussions',
                'title'    => 'Discussions',
                'icon'     => 'message-circle',
                'type'     => self::TYPE_STANDALONE,
            ],
            'overview' => [
                'id'       => 'overview',
                'title'    => 'Activité & KPI',
                'icon'     => 'layout-dashboard',
                'type'     => self::TYPE_STANDALONE,
            ],
        ];

        return $meta[$groupId] ?? [
            'id'    => $groupId,
            'title' => ucfirst($groupId),
            'icon'  => 'grid',
            'type'  => self::TYPE_STANDALONE,
        ];
    }

    /**
     * Liste des onglets horizontaux pour les groupes légers (Gestion, Boutique, Administration).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getGroupTabs(string $groupId, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return [];
        }

        $tabs = [];

        if ($groupId === 'gestion') {
            if (!auth()->check() || $user->hasModuleAccess('clients')) {
                $tabs[] = [
                    'route'  => 'customers.index',
                    'label'  => 'Clients',
                    'icon'   => 'users',
                    'active' => request()->routeIs('customers.*'),
                ];
            }
            if ($user->isAdmin() || $user->exerce(['manager'])) {
                if (!auth()->check() || $user->hasModuleAccess('utilisateurs')) {
                    $tabs[] = [
                        'route'  => 'users.index',
                        'label'  => 'Utilisateurs',
                        'icon'   => 'user-cog',
                        'active' => request()->routeIs('users.*'),
                    ];
                }
            }
        } elseif ($groupId === 'shop') {
            if ($user->isAdmin() || $user->exerce(['shop_manager', 'manager'])) {
                $tabs[] = [
                    'route'  => 'shop.products.index',
                    'label'  => 'Articles',
                    'icon'   => 'package',
                    'active' => request()->routeIs('shop.products.*'),
                ];
            }
            $tabs[] = [
                'route'  => 'shop.orders.index',
                'label'  => 'Commandes',
                'icon'   => 'shopping-cart',
                'active' => request()->routeIs('shop.orders.*'),
            ];
            if ($user->isAdmin() || $user->exerce(['shop_manager', 'manager'])) {
                $tabs[] = [
                    'route'  => 'shop.cash_register.index',
                    'label'  => 'Compta Boutique',
                    'icon'   => 'calculator',
                    'active' => request()->routeIs('shop.cash_register.*'),
                ];
            }
            // La boutique adresse ses demandes à l'économat depuis chez elle.
            if (self::groupeDesDemandes($user) === 'shop') {
                $droits = app(PermissionResolver::class);
                if ($droits->allows($user, 'economat.requisitions.voir')) {
                    $tabs[] = [
                        'route'  => 'economat.requisitions.index',
                        'label'  => $droits->scopeFor($user, 'economat.requisitions.voir') === PermissionScope::PROPRE
                            ? 'Mes demandes à l’économat'
                            : 'Demandes à l’économat',
                        'icon'   => 'inbox',
                        'active' => request()->routeIs('economat.requisitions.*'),
                    ];
                }
                if ($droits->allows($user, 'economat.purchase_requests.voir')) {
                    $tabs[] = [
                        'route'  => 'economat.purchase_requests.index',
                        'label'  => 'Demandes d’achat',
                        'icon'   => 'file-question',
                        'active' => request()->routeIs('economat.purchase_requests.*'),
                    ];
                }
            }
        } elseif ($groupId === 'admin') {
            $tabs[] = [
                'route'  => 'droits.index',
                'label'  => 'Rôles & droits',
                'icon'   => 'shield-check',
                'active' => request()->routeIs('droits.*'),
            ];
            $tabs[] = [
                'route'  => 'interventions.index',
                'label'  => 'Interventions',
                'icon'   => 'siren',
                'active' => request()->routeIs('interventions.*'),
            ];
            $tabs[] = [
                'route'  => 'audit.index',
                'label'  => 'Journal d’audit',
                'icon'   => 'scroll-text',
                'active' => request()->routeIs('audit.*'),
            ];
            $tabs[] = [
                'route'  => 'support.sessions.index',
                'label'  => 'Sessions support',
                'icon'   => 'life-buoy',
                'active' => request()->routeIs('support.sessions.*'),
            ];
        }

        return $tabs;
    }
}
