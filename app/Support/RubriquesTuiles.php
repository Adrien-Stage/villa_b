<?php

namespace App\Support;

use App\Models\PointOfSale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Services\PermissionResolver;
use App\Services\RestaurantContext;

final class RubriquesTuiles
{
    public function __construct(
        private User $utilisateur,
        private PermissionResolver $droits,
    ) {}

    public function pourTuile(string $tuile): array
    {
        $rubriques = match ($tuile) {
            'hotel' => $this->hotel(),
            'restaurant' => $this->restaurant(),
            'articles' => [
                ['economat.items.index', 'Articles', 'boxes'],
                ['economat.categories.index', 'Catégories', 'tags'],
            ],
            'economat' => $this->economat(),
            'achats' => $this->achats(),
            'technique' => $this->technique(),
            'accounting' => $this->comptabilite(),
            'pilotage' => $this->droits->allows($this->utilisateur, 'pilotage.voir') ? [
                ['pilotage.index', 'Indicateurs de gestion', 'gauge'],
                ['pilotage.budget.index', 'Budget', 'target'],
            ] : [],
            'shop' => $this->boutique(),
            'admin' => [
                ['droits.index', 'Rôles & droits', 'shield-check'],
                ['interventions.index', 'Interventions', 'siren'],
                ['audit.index', 'Journal d’audit', 'scroll-text'],
                ['support.sessions.index', 'Sessions support', 'life-buoy'],
            ],
            default => [],
        };

        $liens = [];
        foreach ($rubriques as $rubrique) {
            [$route, $label, $icon] = $rubrique;
            if (($rubrique[3] ?? true) === false || ! $this->routePermise($route)) {
                continue;
            }

            $liens[] = [
                'route' => $route,
                'label' => $label,
                'icon' => $icon,
                'url' => route($route, $rubrique[4] ?? []),
                'active' => Navigation::isRouteActive($route),
                'externe' => $route === 'portal.restaurant.menu',
            ];
        }

        return $liens;
    }

    private function routePermise(string $route): bool
    {
        $droit = PermissionCatalog::permissionForRoute($route);

        return PermissionCatalog::roles($droit) === [] || $this->droits->allows($this->utilisateur, $droit);
    }

    private function aUnDroit(array $droits): bool
    {
        return collect($droits)->contains(fn (string $droit) => $this->droits->allows($this->utilisateur, $droit));
    }

    private function pour(string ...$roles): bool
    {
        return $this->utilisateur->isAdmin() || $this->utilisateur->exerce($roles);
    }

    private function module(string $module): bool
    {
        return TenantModules::has($module) && $this->utilisateur->hasModuleAccess($module);
    }

    private function demandesDans(string $tuile): bool
    {
        return $this->droits->allows($this->utilisateur, 'economat.requisitions.voir')
            && Navigation::groupeDesDemandes($this->utilisateur) === $tuile;
    }

    private function demandesPropres(): bool
    {
        return $this->droits->scopeFor($this->utilisateur, 'economat.requisitions.voir') === PermissionScope::PROPRE;
    }

    private function demandes(string $tuile): array
    {
        $economat = $tuile === 'shop' ? 'l’économat' : "l'économat";
        $label = $this->demandesPropres() ? "Mes demandes à {$economat}" : "Demandes à {$economat}";

        return ['economat.requisitions.index', $label, 'inbox', $this->demandesDans($tuile)];
    }

    private function hotel(): array
    {
        $comptable = $this->utilisateur->exerce(['accountant'])
            && ! $this->utilisateur->exerce([PermissionCatalog::RESPONSABLE_FINANCIER]);

        if ($comptable || ! $this->aUnDroit([
            'rooms.voir', 'bookings.voir', 'agenda.voir', 'housekeeping.voir', 'customers.voir',
        ])) {
            return [];
        }

        $reception = $this->pour('manager', 'reception', 'finance_manager', 'controller');
        $reservations = $reception && $this->utilisateur->hasModuleAccess('reservations');
        $etages = $this->pour('manager', 'housekeeping_leader', 'housekeeping_supervisor', 'housekeeping_staff',
            'housekeeping', 'controller') && $this->module('housekeeping');

        return [
            ['agenda.index', 'Agenda', 'calendar-days', $reservations],
            ['bookings.index', 'Réservations', 'calendar', $reservations],
            ['rooms.index', 'Chambres', 'door', $reception && $this->utilisateur->hasModuleAccess('hebergement')],
            ['rooms.rates.index', 'Tarifs', 'tags'],
            $this->demandes('hotel'),
            ['housekeeping.index', 'Housekeeping', 'sparkles', $etages],
            ['housekeeping.journal.index', 'Suivi des chambres', 'list-checks', $etages],
            ['housekeeping.minibar.index', 'Mini-bars', 'wine', $etages],
            ['rooms.cost_sheets.index', 'Fiches techniques', 'calculator', $this->pour('manager')],
        ];
    }

    private function restaurant(): array
    {
        if (! $this->pour('manager', 'restaurant_chief', 'restaurant_staff', 'restaurant_cook', 'restaurant_barman',
            'restaurant_manager', 'cashier', 'finance_manager', 'controller') || ! $this->module('restaurant')) {
            return [];
        }

        $contexte = app(RestaurantContext::class);
        $sansRestaurant = $contexte->restaurants()->isEmpty();
        $offre = fn (string $service): bool => $sansRestaurant
            || $contexte->offrant($this->utilisateur, $service)->isNotEmpty();
        $buffet = $contexte->offrant($this->utilisateur, PointOfSale::SERVICE_SALLE)
            ->contains(fn (PointOfSale $restaurant) => $restaurant->sert(PointOfSale::MODE_BUFFET));
        $bar = $offre(PointOfSale::SERVICE_BAR) && ! $contexte->serviceAbsentIci($this->utilisateur, PointOfSale::SERVICE_BAR);
        $stock = $offre(PointOfSale::SERVICE_STOCK);
        $salle = $this->pour('manager', 'restaurant_chief', 'restaurant_staff', 'controller');
        $gestion = $this->pour('manager', 'restaurant_chief', 'finance_manager', 'controller');
        $caisse = $this->pour('manager', 'restaurant_chief', 'cashier', 'finance_manager', 'controller');
        $tenantSlug = $this->utilisateur->tenant?->slug ?? Tenant::first()?->slug;
        $restaurantQr = $tenantSlug && $contexte->plusieurs() ? $contexte->pourCreation($this->utilisateur) : null;

        return [
            ['restaurant.breakfast.index', 'Petits-déjeuners', 'coffee', $salle],
            ['restaurant.orders.index', 'Commandes', 'receipt', $salle && $offre(PointOfSale::SERVICE_SALLE)],
            ['restaurant.kitchen.index', 'Cuisine', 'cooking-pot',
                $offre(PointOfSale::SERVICE_CUISINE) && $this->pour('restaurant_chief', 'restaurant_cook', 'controller')],
            ['restaurant.bar.index', 'Bar', 'glass-water', $bar],
            ['restaurant.buffets.index', 'Buffets', 'salad', $buffet && ($salle || $this->pour('cashier'))],
            ['restaurant.banquets.index', 'Banquets', 'party-popper',
                $this->pour('manager', 'restaurant_chief', 'restaurant_manager', 'cashier', 'finance_manager', 'controller')],
            ['restaurant.menus.index', 'Menus', 'book', $salle || $this->pour('finance_manager')],
            ['restaurant.recipes.index', 'Fiches techniques', 'chef-hat', $gestion],
            ['restaurant.pantry.index', 'Garde-manger', 'warehouse', $gestion && $stock],
            ['restaurant.stock_counts.index', 'Inventaires', 'clipboard-list', $gestion && $stock],
            ['restaurant.waste.index', 'Pertes & Déchets', 'trash-2', $stock && ($gestion || $this->pour('restaurant_cook'))],
            ['restaurant.consumption.index', 'Consommation & Ratios', 'pie-chart', $gestion],
            ['restaurant.billing.index', 'Facturation', 'credit-card', $caisse],
            ['restaurant.cash_register.index', 'Caisse', 'calculator', $caisse],
            ['restaurant.restaurants.index', 'Restaurants', 'store', $this->pour('manager', 'restaurant_manager')],
            $this->demandes('restaurant'),
            ['portal.restaurant.menu', 'Portail (QR)', 'qr', (bool) $tenantSlug,
                array_filter(['tenant' => $tenantSlug, 'restaurant' => $restaurantQr?->slug])],
        ];
    }

    private function economat(): array
    {
        if (! Navigation::tientLEconomat($this->utilisateur)) {
            return [];
        }

        $achatsDansEconomat = ! Navigation::tientLesAchats($this->utilisateur);

        return [
            ['economat.index', "Vue d'ensemble", 'warehouse'],
            ['economat.movements.index', 'Mouvements de stock', 'arrow-left-right'],
            ['economat.classeur.index', 'Classeur Excel', 'file-spreadsheet'],
            ['economat.stores.index', 'Dépôts de service', 'warehouse'],
            ['economat.suppliers.index', 'Fournisseurs', 'truck', $achatsDansEconomat],
            ['economat.purchase_requests.index', "Demandes d'achat", 'file-question', $achatsDansEconomat],
            ['economat.orders.index', 'Bons de commande', 'clipboard-list', $achatsDansEconomat],
            ['economat.receipts.index', "Bons d'entrée (BR)", 'package-check'],
            ['economat.stock_counts.index', 'Inventaires', 'clipboard-check'],
            ['economat.count_sheets.index', 'Fiches de comptage', 'printer'],
            ['economat.requisitions.index', $this->demandesPropres() ? 'Mes demandes' : 'Bons de réquisition', 'inbox'],
            ['economat.external_issues.index', 'Sorties hors établissement', 'log-out'],
            ['economat.control.index', 'Contrôle & Ratios', 'pie-chart'],
            ['economat.control.suggestions.index', "Propositions d'achat", 'shopping-cart', $achatsDansEconomat],
            ['economat.control.variances.index', "Écarts d'inventaire", 'git-compare'],
        ];
    }

    private function achats(): array
    {
        if (! Navigation::tientLesAchats($this->utilisateur)) {
            return [];
        }

        return [
            ['achats.index', 'Tableau des achats', 'shopping-cart'],
            ['economat.purchase_requests.index', "Demandes d'achat", 'file-question'],
            ['economat.orders.index', 'Bons de commande', 'clipboard-list'],
            ['economat.suppliers.index', 'Fournisseurs', 'truck'],
            ['economat.control.suggestions.index', 'Alertes & réapprovisionnement', 'bell-ring'],
            ['economat.receipts.index', 'Livraisons reçues', 'package-check', ! Navigation::tientLEconomat($this->utilisateur)],
        ];
    }

    private function technique(): array
    {
        if (! $this->module('technique') || ! $this->aUnDroit([
            'technique.voir', 'technique.demandes.voir',
        ])) {
            return [];
        }

        $intervention = ! $this->utilisateur->exerce(MaintenanceService::TECHNICIENS)
            || $this->pour('manager', ...MaintenanceService::RESPONSABLES);
        $demandes = $this->droits->allows($this->utilisateur, 'technique.voir')
            ? "Demandes d'intervention" : "Mes demandes d'intervention";

        return [
            ['technique.index', 'Tableau technique', 'wrench'],
            ['technique.demandes.index', $demandes, 'clipboard-list'],
            ['technique.demandes.create', 'Demander une intervention', 'circle-plus', $intervention],
            ['technique.equipe.index', 'Équipe & spécialités', 'users'],
            ['technique.depot.index', 'Dépôt technique', 'package'],
            $this->demandes('technique'),
        ];
    }

    private function comptabilite(): array
    {
        if (! $this->aUnDroit(['accounting.voir', 'accounting.ledger.voir', 'rooms.cost_sheets.voir'])) {
            return [];
        }

        return [
            ['accounting.index', 'Comptabilité', 'wallet', $this->utilisateur->hasModuleAccess('comptabilite')],
            ['accounting.ledger.index', 'Grand livre', 'book-open', $this->module('ledger')],
            ['economat.requisitions.index', 'Mes demandes', 'inbox', $this->demandesDans('accounting')],
            ['rooms.cost_sheets.index', 'Fiches techniques', 'calculator', $this->pour('accountant', 'admin', 'controller')],
        ];
    }

    private function boutique(): array
    {
        if (! $this->module('shop')) {
            return [];
        }

        $gestion = $this->pour('shop_manager', 'manager', PermissionCatalog::RESPONSABLE_FINANCIER,
            DutySegregation::CONTROLE_DE_GESTION);

        return [
            ['shop.products.index', 'Articles', 'package', $gestion],
            ['shop.orders.index', 'Commandes', 'shopping-cart'],
            ['shop.cash_register.index', 'Compta Boutique', 'calculator', $gestion],
            $this->demandes('shop'),
        ];
    }
}
