<?php

namespace App\Support;

/**
 * Catalogue des droits : une entrée par couple module.action, et les rôles
 * qui la détiennent.
 *
 * Jusqu'ici les droits vivaient éparpillés dans les middlewares « role: » de
 * routes/web.php, dans @role() au fil des vues, et dans la carte
 * User::$moduleAccess. Trois sources, aucune vue d'ensemble, et rien qui
 * permette de dire « le comptable consulte l'économat mais n'y crée pas
 * d'article » sans toucher au code.
 *
 * Ce fichier ne change rien à l'exécution. Il est l'inventaire fidèle de ce
 * que le code fait aujourd'hui — dérivé de la table de routage elle-même, pas
 * recopié à la main — et PermissionCatalogConformityTest interdit qu'il en
 * diverge. Il devient ainsi la base sûre à partir de laquelle la matrice
 * pourra être éditée depuis l'ERP.
 *
 * Convention de nommage : le nom de route, dont le dernier segment est
 * normalisé en verbe métier (voir / creer / modifier / supprimer). Les actions
 * propres au métier — approve, deliver, receive, adjust, export, import —
 * gardent leur nom : ce sont elles qui portent la séparation des tâches.
 *
 * Les rôles d'un droit sont l'INTERSECTION des middlewares « role: » qui
 * gardaient la route, car ils s'empilaient : 54 routes en portaient deux, un
 * groupe large et une garde interne étroite, et chacun devait passer. Les
 * réunir aurait ouvert aux commis ce que le chef seul pouvait faire.
 */
class PermissionCatalog
{
    /**
     * Verbes de route ramenés à un verbe de droit. Ce qui n'y figure pas est
     * une action métier et se conserve tel quel.
     */
    private const VERBES = [
        'index' => 'voir',   'show'   => 'voir',   'list'  => 'voir',
        'search' => 'voir',  'data'   => 'voir',   'print' => 'voir',
        'create' => 'creer', 'store'  => 'creer',
        'edit'   => 'modifier', 'update' => 'modifier', 'patch' => 'modifier',
        'destroy' => 'supprimer', 'delete' => 'supprimer',
        // « export » et « import » gardent leur nom : extraire une liste de
        // clients n'est pas la consulter, et une importation en masse n'est
        // pas une création. Les fondre dans voir/creer alignerait leurs droits
        // sur les plus larges des deux.
    ];

    /**
     * Droit correspondant à un nom de route.
     *
     * Partagé avec le test de conformité : la règle de correspondance ne doit
     * exister qu'en un seul endroit, sinon le test cesse de prouver quoi que
     * ce soit.
     */
    public static function permissionForRoute(string $routeName): string
    {
        $segments = explode('.', $routeName);

        if (count($segments) === 1) {
            return $segments[0] . '.voir';
        }

        $action = array_pop($segments);

        return implode('.', $segments) . '.' . (self::VERBES[$action] ?? $action);
    }

    /**
     * @return array<string, list<string>> droit => rôles qui le détiennent
     */
    public static function all(): array
    {
        return [
            // ── Restauration ──
            'restaurant.billing.paid' => ['cashier', 'restaurant_chief'],
            'restaurant.billing.receipt' => ['cashier', 'manager', 'restaurant_chief'],
            'restaurant.billing.unpaid' => ['cashier', 'restaurant_chief'],
            'restaurant.billing.voir' => ['cashier', 'manager', 'restaurant_chief'],
            'restaurant.kitchen.voir' => ['manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.menus.categories.creer' => ['restaurant_chief'],
            'restaurant.menus.categories.modifier' => ['restaurant_chief'],
            'restaurant.menus.categories.supprimer' => ['restaurant_chief'],
            'restaurant.menus.export' => ['manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.menus.import' => ['restaurant_chief'],
            'restaurant.menus.items.creer' => ['restaurant_chief'],
            'restaurant.menus.items.modifier' => ['restaurant_chief'],
            'restaurant.menus.items.supprimer' => ['restaurant_chief'],
            'restaurant.menus.voir' => ['manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.orders.claim' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.creer' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.preparing' => ['restaurant_chief', 'restaurant_cook'],
            'restaurant.orders.ready' => ['restaurant_chief', 'restaurant_cook'],
            'restaurant.orders.reassign' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.send_to_kitchen' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.served' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.status' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.orders.voir' => ['manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.pantry.categories.creer' => ['restaurant_chief'],
            'restaurant.pantry.categories.modifier' => ['restaurant_chief'],
            'restaurant.pantry.categories.supprimer' => ['restaurant_chief'],
            'restaurant.pantry.export' => ['manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.pantry.import' => ['restaurant_chief'],
            'restaurant.pantry.items.creer' => ['restaurant_chief'],
            'restaurant.pantry.items.modifier' => ['restaurant_chief'],
            'restaurant.pantry.items.receive' => ['restaurant_chief'],
            'restaurant.pantry.items.supprimer' => ['restaurant_chief'],
            'restaurant.pantry.movements.creer' => ['restaurant_chief'],
            'restaurant.pantry.voir' => ['manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.recipes.creer' => ['restaurant_chief'],
            'restaurant.recipes.export' => ['manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.recipes.import' => ['restaurant_chief'],
            'restaurant.recipes.modifier' => ['restaurant_chief'],
            'restaurant.recipes.produce' => ['restaurant_chief'],
            'restaurant.recipes.supprimer' => ['restaurant_chief'],
            'restaurant.recipes.voir' => ['manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.shifts.close' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.shifts.open' => ['restaurant_chief', 'restaurant_staff'],
            'restaurant.stock_counts.close' => ['restaurant_chief'],
            'restaurant.stock_counts.creer' => ['restaurant_chief'],
            'restaurant.stock_counts.modifier' => ['restaurant_chief'],
            'restaurant.stock_counts.supprimer' => ['restaurant_chief'],
            'restaurant.stock_counts.voir' => ['manager', 'restaurant_chief', 'restaurant_cook'],

            // ── Comptabilité ──
            'accounting.cash' => ['accountant', 'admin', 'manager'],
            'accounting.cash_reviews' => ['accountant', 'admin', 'manager'],
            'accounting.cash_reviews.creer' => ['accountant', 'admin', 'manager'],
            'accounting.expenses' => ['accountant', 'admin', 'manager'],
            'accounting.expenses.creer' => ['accountant', 'admin', 'manager'],
            'accounting.expenses.modifier' => ['accountant', 'admin', 'manager'],
            'accounting.expenses.supprimer' => ['accountant', 'admin', 'manager'],
            'accounting.income_statement' => ['accountant', 'admin', 'manager'],
            'accounting.journal' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.accounts' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.aged' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.analytic' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.analytic.margins' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.analytic.mirror' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.auxiliary' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.auxiliary.ledger' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.balance' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.entry' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.entry.reverse' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.general' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.journals' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.night_audit' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.night_audit.run' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.opening' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.opening.creer' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.periods' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.periods.lock' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.reconcile' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.reconcile.auto' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.reconcile.undo' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.suppliers' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.suppliers.creer' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.suppliers.voir' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.voir' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.withholding' => ['accountant', 'admin', 'manager'],
            'accounting.ledger.years.open' => ['accountant', 'admin', 'manager'],
            'accounting.receivables' => ['accountant', 'admin', 'manager'],
            'accounting.voir' => ['accountant', 'admin', 'manager'],

            // ── Réservations ──
            'bookings.approve' => ['manager', 'reception'],
            'bookings.cancel' => ['manager', 'reception'],
            'bookings.cash_register.close' => ['manager', 'reception'],
            'bookings.cash_register.close.creer' => ['manager', 'reception'],
            'bookings.cash_register.disbursements.creer' => ['manager', 'reception'],
            'bookings.cash_register.open' => ['manager', 'reception'],
            'bookings.cash_register.open.creer' => ['manager', 'reception'],
            'bookings.cash_register.voir' => ['manager', 'reception'],
            'bookings.checkIn' => ['manager', 'reception'],
            'bookings.checkOut' => ['manager', 'reception'],
            'bookings.checkin_code.send' => ['manager', 'reception'],
            'bookings.confirm' => ['manager', 'reception'],
            'bookings.creer' => ['manager', 'reception'],
            'bookings.drafts.continue' => ['manager', 'reception'],
            'bookings.drafts.resume' => ['manager', 'reception'],
            'bookings.drafts.save' => ['manager', 'reception'],
            'bookings.drafts.supprimer' => ['manager', 'reception'],
            'bookings.drafts.voir' => ['manager', 'reception'],
            'bookings.folio.add' => ['manager', 'reception'],
            'bookings.folio.remove' => ['manager', 'reception'],
            'bookings.modifier' => ['manager', 'reception'],
            'bookings.payment.add' => ['manager', 'reception'],
            'bookings.voir' => ['manager', 'reception'],

            // ── Économat ──
            'economat.items.adjust' => ['admin', 'econome', 'manager'],
            'economat.items.creer' => ['admin', 'econome', 'manager'],
            'economat.items.export' => ['admin', 'econome', 'manager'],
            'economat.items.import' => ['admin', 'econome', 'manager'],
            'economat.items.modifier' => ['admin', 'econome', 'manager'],
            'economat.items.supprimer' => ['admin', 'econome', 'manager'],
            'economat.items.voir' => ['admin', 'econome', 'manager'],
            'economat.orders.cancel' => ['admin', 'econome', 'manager'],
            'economat.orders.creer' => ['admin', 'econome', 'manager'],
            'economat.orders.receive' => ['admin', 'econome', 'manager'],
            'economat.orders.send' => ['admin', 'econome', 'manager'],
            'economat.orders.voir' => ['admin', 'econome', 'manager'],
            'economat.requisitions.approve' => ['admin', 'econome', 'manager'],
            'economat.requisitions.cancel' => ['admin', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'economat.requisitions.creer' => ['admin', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'economat.requisitions.deliver' => ['admin', 'econome', 'manager'],
            'economat.requisitions.reject' => ['admin', 'econome', 'manager'],
            'economat.requisitions.voir' => ['admin', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'economat.suppliers.creer' => ['admin', 'econome', 'manager'],
            'economat.suppliers.modifier' => ['admin', 'econome', 'manager'],
            'economat.suppliers.supprimer' => ['admin', 'econome', 'manager'],
            'economat.suppliers.voir' => ['admin', 'econome', 'manager'],
            'economat.voir' => ['admin', 'econome', 'manager'],

            // ── Chambres ──
            'rooms.cost_sheets.assumptions' => ['accountant', 'manager'],
            'rooms.cost_sheets.export' => ['accountant', 'manager'],
            'rooms.cost_sheets.import' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.creer' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.modifier' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.supprimer' => ['accountant', 'manager'],
            'rooms.cost_sheets.starter' => ['accountant', 'manager'],
            'rooms.cost_sheets.voir' => ['accountant', 'manager'],
            'rooms.creer' => ['manager', 'reception'],
            'rooms.export' => ['manager', 'reception'],
            'rooms.images.supprimer' => ['manager', 'reception'],
            'rooms.import' => ['manager', 'reception'],
            'rooms.modifier' => ['manager', 'reception'],
            'rooms.supprimer' => ['manager', 'reception'],
            'rooms.types.creer' => ['manager', 'reception'],
            'rooms.types.export' => ['manager', 'reception'],
            'rooms.types.import' => ['manager', 'reception'],
            'rooms.types.modifier' => ['manager', 'reception'],
            'rooms.types.supprimer' => ['manager', 'reception'],
            'rooms.updateStatus' => ['manager', 'reception'],
            'rooms.voir' => ['manager', 'reception'],

            // ── Paramètres ──
            'settings.export' => ['housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'settings.import' => ['housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'settings.modifier' => ['housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],
            'settings.packages.creer' => ['manager'],
            'settings.packages.export' => ['manager'],
            'settings.packages.import' => ['manager'],
            'settings.packages.modifier' => ['manager'],
            'settings.packages.supprimer' => ['manager'],
            'settings.partners.creer' => ['manager'],
            'settings.partners.export' => ['manager'],
            'settings.partners.import' => ['manager'],
            'settings.partners.modifier' => ['manager'],
            'settings.partners.supprimer' => ['manager'],
            'settings.services.creer' => ['manager'],
            'settings.services.export' => ['manager'],
            'settings.services.import' => ['manager'],
            'settings.services.modifier' => ['manager'],
            'settings.services.supprimer' => ['manager'],
            'settings.voir' => ['housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'shop_manager'],

            // ── Boutique ──
            'shop.cash_register.close' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.close.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.disbursements.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.open' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.open.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.voir' => ['manager', 'shop_manager'],
            'shop.orders.creer' => ['shop_cashier', 'shop_manager'],
            'shop.orders.paid' => ['shop_cashier', 'shop_manager'],
            'shop.orders.receipt' => ['manager', 'shop_cashier', 'shop_manager'],
            'shop.orders.refund' => ['shop_cashier', 'shop_manager'],
            'shop.orders.voir' => ['manager', 'shop_cashier', 'shop_manager'],
            'shop.products.creer' => ['shop_manager'],
            'shop.products.export' => ['manager', 'shop_manager'],
            'shop.products.import' => ['shop_manager'],
            'shop.products.modifier' => ['shop_manager'],
            'shop.products.supprimer' => ['shop_manager'],
            'shop.products.voir' => ['manager', 'shop_manager'],

            // ── Groupes ──
            'groups.addRoom' => ['manager'],
            'groups.cancel' => ['manager'],
            'groups.checkInAll' => ['manager', 'reception'],
            'groups.checkOutAll' => ['manager', 'reception'],
            'groups.creer' => ['manager'],
            'groups.folio.add' => ['manager', 'reception'],
            'groups.invoice' => ['manager', 'reception'],
            'groups.modifier' => ['manager'],
            'groups.payment.add' => ['manager', 'reception'],
            'groups.removeRoom' => ['manager'],
            'groups.voir' => ['manager', 'reception'],

            // ── Housekeeping ──
            'housekeeping.assignments.creer' => ['housekeeping_leader', 'manager'],
            'housekeeping.available' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.clean' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.inspect' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.issue' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.ready' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.reject' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.teams.creer' => ['housekeeping_leader', 'manager'],
            'housekeeping.voir' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],

            // ── Clients ──
            'customers.creer' => ['manager', 'reception'],
            'customers.export' => ['manager', 'reception'],
            'customers.import' => ['manager', 'reception'],
            'customers.modifier' => ['manager', 'reception'],
            'customers.voir' => ['cashier', 'manager', 'reception'],

            // ── Réception ──
            'reception.pos.history' => ['manager', 'reception'],
            'reception.pos.receipt' => ['manager', 'reception'],
            'reception.pos.sales.creer' => ['manager', 'reception'],
            'reception.pos.voir' => ['manager', 'reception'],

            // ── Utilisateurs ──
            'users.creer' => ['manager'],
            'users.modifier' => ['manager'],
            'users.toggleStatus' => ['manager'],
            'users.voir' => ['manager'],

            // ── Agenda ──
            'agenda.voir' => ['manager', 'reception'],

            // ── Analytique ──
            'analytics.voir' => ['manager'],

            // ── Factures ──
            'invoices.voir' => ['cashier', 'manager', 'reception'],

            // ── Divers ──
            'test-popup.voir' => ['admin'],
        ];
    }

    /** Modules déclarés, dans l'ordre alphabétique. */
    public static function modules(): array
    {
        $modules = array_map(
            static fn (string $droit): string => explode('.', $droit)[0],
            array_keys(self::all())
        );

        $modules = array_values(array_unique($modules));
        sort($modules);

        return $modules;
    }

    /** Droits détenus aujourd'hui par un rôle. */
    public static function forRole(string $role): array
    {
        return array_keys(array_filter(
            self::all(),
            static fn (array $roles): bool => in_array($role, $roles, true)
        ));
    }

    /** Rôles détenant un droit. Tableau vide si le droit n'existe pas. */
    public static function roles(string $permission): array
    {
        return self::all()[$permission] ?? [];
    }
}
