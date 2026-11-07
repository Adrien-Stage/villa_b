<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Breadcrumbs
{
    /**
     * Dictionnaire de correspondance des segments et ressources en français.
     */
    private const LABELS = [
        // Économat & Achats
        'economat.index'                   => "Vue d'ensemble",
        'economat.items'                   => 'Articles',
        'economat.purchase_requests'       => "Demandes d'achat",
        'economat.orders'                  => 'Bons de commande',
        'economat.receipts'                => 'Bordereaux de réception',
        'economat.requisitions'            => 'Bons de réquisition',
        'economat.transfers'               => 'Bons de transfert',
        'economat.external_issues'         => 'Sorties hors établissement',
        'economat.movements'               => 'Mouvements de stock',
        'economat.stock_counts'            => 'Inventaires',
        'economat.count_sheets'            => 'Fiches de comptage',
        'economat.suppliers'               => 'Fournisseurs',
        'economat.stores'                  => 'Dépôts de service',
        'economat.categories'              => 'Catégories',
        'economat.classeur'                => 'Classeur de stock',
        'economat.control'                 => 'Contrôle & Écarts',
        'economat.control.discrepancies'   => 'Écarts d\'inventaire',
        'achats.index'                     => 'Tableau des achats',

        // Hôtel & Hébergement
        'rooms'                            => 'Chambres',
        'rooms.rates'                      => 'Grille tarifaire',
        'rooms.cost_sheets'                => 'Fiches de coûts',
        'bookings'                         => 'Réservations',
        'groups'                           => 'Réservations de groupe',
        'agenda'                           => 'Agenda des séjours',
        'reception.pos'                    => 'POS Réception',
        'housekeeping'                     => 'Gouvernance & Ménage',
        'housekeeping.journal'             => 'Journal d\'entretien',
        'housekeeping.minibar'             => 'Minibar',

        // Restaurant & F&B
        'restaurant.orders'                => 'Commandes',
        'restaurant.kitchen'               => 'Cuisine (KDS)',
        'restaurant.bar'                   => 'Bar',
        'restaurant.menus'                 => 'Carte & Menus',
        'restaurant.recipes'               => 'Fiches recettes',
        'restaurant.breakfast'             => 'Petits-déjeuners',
        'restaurant.billing'               => 'Facturation',
        'restaurant.cash_register'         => 'Caisse restaurant',
        'restaurant.consumption'           => 'Consommations',
        'restaurant.banquets'              => 'Banquets & Événements',
        'restaurant.restaurants'           => 'Points de vente',
        'restaurant.stock_counts'          => 'Inventaires restaurant',

        // Comptabilité & Finance
        'accounting'                       => 'Comptabilité',
        'partners.wallet'                  => 'Comptes dépôt partenaires',
        'accounting.ledger'                => 'Grand livre',
        'accounting.journal'               => 'Journal général',
        'accounting.revenue_journal'       => 'Journal des encaissements',
        'accounting.expenses'              => 'Dépenses',
        'accounting.cash'                  => 'Suivi des caisses',
        'accounting.cash_reviews'          => 'Contrôles de caisse',
        'accounting.receivables'           => 'Créances clients',
        'accounting.income_statement'      => 'Compte de résultat',

        // Technique & Maintenance
        'technique.index'                  => 'Tableau technique',
        'technique.demandes'               => 'Demandes d\'intervention',
        'technique.depot'                  => 'Dépôt technique',
        'technique.equipe'                 => 'Équipe technique',

        // Pilotage
        'pilotage.index'                   => 'Indicateurs de gestion',
        'pilotage.budget'                  => 'Budget prévisionnel',

        // Gestion & Administration
        'customers'                        => 'Clients',
        'users'                            => 'Utilisateurs & Équipes',
        'droits'                           => 'Rôles & Droits',
        'interventions'                    => 'Interventions',
        'audit'                            => 'Journal d\'audit',
        'support.sessions'                 => 'Sessions du support',
        'settings'                         => 'Paramètres',
        'planning'                         => 'Planning des quarts',
        'editions'                         => 'Éditions & Impressions',
        'analytics'                        => 'Tour de contrôle',
    ];

    /**
     * Actions standard de fin de route
     */
    private const ACTIONS = [
        'create'   => 'Nouveau',
        'edit'     => 'Modifier',
        'show'     => 'Détails',
        'print'    => 'Impression',
        'import'   => 'Import',
        'export'   => 'Export',
    ];

    /**
     * Détermine si le widget de fil d'Ariane doit être affiché.
     */
    public static function shouldShow(): bool
    {
        $routeName = request()->route()?->getName() ?? Route::currentRouteName();

        if (! $routeName) {
            return false;
        }

        // Ne pas afficher sur le lanceur d'applications ni sur les pages publiques/auth
        if ($routeName === 'dashboard' && request('view') !== 'kpi') {
            return false;
        }

        if (in_array($routeName, ['login', 'logout', 'pwa.offline'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Retourne l'URL racine d'un groupe applicatif pour la navigation.
     */
    public static function groupRootUrl(string $groupId): ?string
    {
        $urls = [
            'economat'   => Route::has('economat.index') ? route('economat.index') : null,
            'hotel'      => Route::has('rooms.index') ? route('rooms.index') : (Route::has('bookings.index') ? route('bookings.index') : null),
            'restaurant' => Route::has('restaurant.orders.index') ? route('restaurant.orders.index') : null,
            'achats'     => Route::has('achats.index') ? route('achats.index') : (Route::has('economat.purchase_requests.index') ? route('economat.purchase_requests.index') : null),
            'accounting' => Route::has('accounting.index') ? route('accounting.index') : null,
            'technique'  => Route::has('technique.index') ? route('technique.index') : null,
            'pilotage'   => Route::has('pilotage.index') ? route('pilotage.index') : null,
            'gestion'    => Route::has('customers.index') ? route('customers.index') : null,
            'shop'       => Route::has('shop.products.index') ? route('shop.products.index') : null,
            'admin'      => Route::has('droits.index') ? route('droits.index') : null,
            'editions'   => Route::has('editions.index') ? route('editions.index') : null,
            'planning'   => Route::has('planning.index') ? route('planning.index') : null,
            'analytics'  => Route::has('analytics.index') ? route('analytics.index') : null,
            'settings'   => Route::has('settings') ? route('settings') : null,
        ];

        return $urls[$groupId] ?? null;
    }

    /**
     * Génère la chaîne d'éléments du fil d'Ariane.
     *
     * @param  array<int, array{label: string, url: ?string, icon?: ?string}>|null  $custom
     * @return array<int, array{label: string, url: ?string, icon?: ?string}>
     */
    public static function generate(?array $custom = null): array
    {
        if ($custom !== null) {
            return $custom;
        }

        $currentRoute = request()->route()?->getName() ?? Route::currentRouteName() ?? '';
        $currentUrl   = request()->url();
        $items        = [];

        // 1. Racine : Accueil (Lanceur d'applications)
        $items[] = [
            'label' => 'Accueil',
            'url'   => route('dashboard'),
            'icon'  => 'home',
        ];

        // 2. Groupe / Module applicatif actif (Hôtel, Économat, Restaurant, etc.)
        $group = Navigation::getActiveGroup($currentRoute);
        if ($group && ! in_array($group['id'], ['dashboard'], true)) {
            $groupUrl = $group['url'] ?? self::groupRootUrl($group['id']);
            $items[] = [
                'label' => $group['title'],
                'url'   => ($groupUrl && $groupUrl !== $currentUrl) ? $groupUrl : null,
                'icon'  => $group['icon'] ?? null,
            ];
        }

        // 3. Décomposition de la route courante
        $routeParts = explode('.', $currentRoute);
        $action = end($routeParts);

        // Retrouver la ressource de base (ex: economat.items, rooms.rates, etc.)
        $matchedResource = null;
        $matchedLabel = null;

        // Cherche le préfixe le plus long correspondant dans le dictionnaire
        for ($i = count($routeParts); $i >= 1; $i--) {
            $candidate = implode('.', array_slice($routeParts, 0, $i));
            if (isset(self::LABELS[$candidate])) {
                $matchedResource = $candidate;
                $matchedLabel = self::LABELS[$candidate];
                break;
            }
        }

        if ($matchedResource && $matchedLabel) {
            // Déterminer l'URL de la liste de cette ressource si elle existe
            $indexRoute = Route::has($matchedResource . '.index') ? route($matchedResource . '.index') : (Route::has($matchedResource) ? route($matchedResource) : null);

            // Si le label correspond déjà au nom du groupe, on n'ajoute pas de redondance
            $isSameAsGroup = $group && mb_strtolower($group['title']) === mb_strtolower($matchedLabel);

            if (! $isSameAsGroup) {
                $items[] = [
                    'label' => $matchedLabel,
                    'url'   => ($indexRoute && $indexRoute !== $currentUrl) ? $indexRoute : null,
                ];
            }

            // Si c'est une action spécifique (show, create, edit, etc.)
            if ($action && isset(self::ACTIONS[$action])) {
                $specificLabel = self::resolveSpecificLabel($action, $currentRoute);
                if ($specificLabel && (! empty($items) && $specificLabel !== end($items)['label'])) {
                    $items[] = [
                        'label' => $specificLabel,
                        'url'   => null,
                    ];
                }
            }
        } elseif ($currentRoute && ! in_array($currentRoute, ['dashboard'], true)) {
            // Fallback générique basé sur le nom de route
            $label = Str::headline(end($routeParts));
            $items[] = [
                'label' => $label,
                'url'   => null,
            ];
        }

        // Nettoyer les doublons consécutifs
        $cleaned = [];
        foreach ($items as $item) {
            if (empty($cleaned) || end($cleaned)['label'] !== $item['label']) {
                $cleaned[] = $item;
            }
        }

        // Le dernier élément est la page active : son URL doit être nulle
        if (! empty($cleaned)) {
            $lastIdx = count($cleaned) - 1;
            $cleaned[$lastIdx]['url'] = null;
        }

        return $cleaned;
    }

    /**
     * Tente de donner un libellé clair pour l'entité courante (ex: numéro DA, nom article, etc.)
     */
    private static function resolveSpecificLabel(string $action, string $currentRoute): string
    {
        // 1. Examiner les paramètres de la route active
        $route = request()->route();
        if ($route) {
            $params = $route->parameters();
            foreach ($params as $param) {
                if (is_object($param)) {
                    $lbl = self::labelForModel($param);
                    if ($lbl !== '') {
                        return $lbl;
                    }
                } elseif (is_numeric($param)) {
                    $modelClass = self::guessModelForRoute($currentRoute);
                    if ($modelClass && class_exists($modelClass)) {
                        $found = $modelClass::find((int) $param);
                        if ($found) {
                            $lbl = self::labelForModel($found);
                            if ($lbl !== '') {
                                return $lbl;
                            }
                        }
                    }
                    if ($action === 'show') {
                        return '#' . $param;
                    }
                }
            }
        }

        // 2. Action standard (Nouveau, Modifier, etc.)
        return self::ACTIONS[$action] ?? Str::headline($action);
    }

    private static function guessModelForRoute(string $route): ?string
    {
        if (str_starts_with($route, 'economat.items.')) return \App\Models\StockItem::class;
        if (str_starts_with($route, 'economat.purchase_requests.')) return \App\Models\PurchaseRequest::class;
        if (str_starts_with($route, 'economat.orders.')) return \App\Models\PurchaseOrder::class;
        if (str_starts_with($route, 'economat.receipts.')) return \App\Models\GoodsReceipt::class;
        if (str_starts_with($route, 'economat.requisitions.')) return \App\Models\StockRequisition::class;
        if (str_starts_with($route, 'economat.transfers.')) return \App\Models\StockTransfer::class;
        if (str_starts_with($route, 'economat.suppliers.')) return \App\Models\Supplier::class;
        if (str_starts_with($route, 'economat.stores.')) return \App\Models\ServiceStore::class;
        if (str_starts_with($route, 'rooms.')) return \App\Models\Room::class;
        if (str_starts_with($route, 'bookings.')) return \App\Models\Booking::class;
        if (str_starts_with($route, 'customers.')) return \App\Models\Customer::class;
        if (str_starts_with($route, 'users.')) return \App\Models\User::class;
        if (str_starts_with($route, 'technique.demandes.')) return \App\Models\MaintenanceRequest::class;

        return null;
    }

    private static function labelForModel(object $model): string
    {
        if (! empty($model->number)) {
            return (string) $model->number;
        }
        if (! empty($model->name)) {
            return Str::limit((string) $model->name, 35);
        }
        if (! empty($model->booking_reference)) {
            return 'Réf. ' . (string) $model->booking_reference;
        }
        if (! empty($model->room_number)) {
            return 'Chambre ' . (string) $model->room_number;
        }
        if (! empty($model->reference)) {
            return (string) $model->reference;
        }
        if (! empty($model->title)) {
            return Str::limit((string) $model->title, 35);
        }

        return '';
    }
}
