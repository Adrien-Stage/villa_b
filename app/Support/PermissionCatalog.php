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
 * Le manager écrit sur l'hébergement, les statistiques et l'administration.
 * Ailleurs — restauration, boutique, économat, comptabilité — il consulte
 * sans saisir : ces services ont leurs responsables, et le directeur d'hôtel
 * qui saisirait à leur place brouillerait la responsabilité de chacun.
 *
 * Une exception : valider ou refuser une demande d'achat. Ce n'est pas une
 * saisie à la place de l'économe mais la décision de dépense, que la
 * direction se réserve. Le comptage des caisses, lui, est contresigné par la
 * comptabilité seule (CashClosurePolicy).
 *
 * Le gabarit n'écrit que les rôles qui reçoivent un droit pour eux-mêmes ;
 * all() le complète de ce que la hiérarchie implique (voir RoleCatalog) : un
 * chef détient les droits de ses membres, et l'administrateur consulte tout.
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
        'summary' => 'voir',
        'create' => 'creer', 'store'  => 'creer',
        'edit'   => 'modifier', 'update' => 'modifier', 'patch' => 'modifier',
        'destroy' => 'supprimer', 'delete' => 'supprimer',
        // « export » et « import » gardent leur nom : extraire une liste de
        // clients n'est pas la consulter, et une importation en masse n'est
        // pas une création. Les fondre dans voir/creer alignerait leurs droits
        // sur les plus larges des deux.
    ];

    /**
     * Droits servis par une route qui écrit — POST, PUT, PATCH ou DELETE.
     *
     * Lire cette qualité dans le nom du droit ne marche pas : « claim »,
     * « produce » ou « periods.lock » écrivent sans le dire, et
     * « accounting.revenue_journal » ne fait que lire sans porter de verbe.
     * La liste est donc dérivée des méthodes HTTP réelles, comme le reste du
     * catalogue l'est de la table de routage, et PermissionCatalogConformityTest
     * interdit qu'elle en diverge.
     *
     * Un droit servi à la fois en GET et en POST compte comme une écriture :
     * c'est le pouvoir le plus large qu'il confère.
     */
    private const ECRITURES = [
        'accounting.cash_reviews.creer',
        'accounting.expenses.creer',
        'accounting.expenses.modifier',
        'accounting.expenses.supprimer',
        'accounting.ledger.analytic.mirror',
        'accounting.ledger.entry.reverse',
        'accounting.ledger.night_audit.run',
        'accounting.ledger.opening.creer',
        'accounting.ledger.periods.lock',
        'accounting.ledger.reconcile',
        'accounting.ledger.reconcile.auto',
        'accounting.ledger.reconcile.undo',
        'accounting.ledger.suppliers.creer',
        'accounting.ledger.years.open',
        'bookings.approve',
        'bookings.cancel',
        'bookings.cash_register.close.creer',
        'bookings.cash_register.disbursements.creer',
        'bookings.cash_register.open.creer',
        'bookings.checkIn',
        'bookings.checkOut',
        'bookings.checkin_code.send',
        'bookings.confirm',
        'bookings.creer',
        'bookings.drafts.save',
        'bookings.drafts.supprimer',
        'bookings.folio.add',
        'bookings.folio.remove',
        'bookings.modifier',
        'bookings.payment.add',
        'customers.creer',
        'customers.import',
        'customers.modifier',
        'economat.categories.creer',
        'economat.categories.modifier',
        'economat.categories.supprimer',
        'economat.control.suggestions.creer',
        'economat.items.adjust',
        'economat.items.creer',
        'economat.items.import',
        'economat.items.modifier',
        'economat.items.opening',
        'economat.items.supprimer',
        'economat.orders.cancel',
        'economat.orders.creer',
        'economat.orders.receive',
        'economat.orders.send',
        'economat.purchase_requests.approve',
        'economat.purchase_requests.cancel',
        'economat.purchase_requests.convert',
        'economat.purchase_requests.creer',
        'economat.purchase_requests.reject',
        'economat.receipts.cancel',
        'economat.receipts.creer',
        'economat.requisitions.approve',
        'economat.requisitions.cancel',
        'economat.requisitions.creer',
        'economat.requisitions.deliver',
        'economat.requisitions.reject',
        'economat.suppliers.creer',
        'economat.suppliers.modifier',
        'economat.suppliers.supprimer',
        'economat.stores.counts.cancel',
        'economat.stores.counts.close',
        'economat.stores.counts.creer',
        'economat.stores.counts.modifier',
        'economat.stores.creer',
        'economat.stores.modifier',
        'economat.stores.supprimer',
        'economat.stock_counts.cancel',
        'economat.stock_counts.close',
        'economat.stock_counts.creer',
        'economat.stock_counts.modifier',
        'groups.addRoom',
        'groups.cancel',
        'groups.checkInAll',
        'groups.checkOutAll',
        'groups.creer',
        'groups.folio.add',
        'groups.modifier',
        'groups.payment.add',
        'groups.removeRoom',
        'housekeeping.assignments.creer',
        'housekeeping.available',
        'housekeeping.clean',
        'housekeeping.inspect',
        'housekeeping.issue',
        'housekeeping.ready',
        'housekeeping.reject',
        'housekeeping.teams.creer',
        'reception.pos.sales.creer',
        'restaurant.billing.paid',
        'restaurant.billing.unpaid',
        'restaurant.breakfast.serve',
        'restaurant.menus.categories.creer',
        'restaurant.menus.categories.modifier',
        'restaurant.menus.categories.supprimer',
        'restaurant.menus.import',
        'restaurant.menus.items.creer',
        'restaurant.menus.items.modifier',
        'restaurant.menus.items.supprimer',
        'restaurant.orders.claim',
        'restaurant.orders.creer',
        'restaurant.orders.preparing',
        'restaurant.orders.ready',
        'restaurant.orders.reassign',
        'restaurant.orders.send_to_kitchen',
        'restaurant.orders.served',
        'restaurant.orders.status',
        'restaurant.pantry.categories.creer',
        'restaurant.pantry.categories.modifier',
        'restaurant.pantry.categories.supprimer',
        'restaurant.pantry.import',
        'restaurant.pantry.items.creer',
        'restaurant.pantry.items.modifier',
        'restaurant.pantry.items.receive',
        'restaurant.pantry.items.supprimer',
        'restaurant.pantry.movements.creer',
        'restaurant.recipes.creer',
        'restaurant.recipes.import',
        'restaurant.recipes.modifier',
        'restaurant.recipes.produce',
        'restaurant.recipes.supprimer',
        'restaurant.shifts.close',
        'restaurant.shifts.open',
        'restaurant.stock_counts.close',
        'restaurant.stock_counts.creer',
        'restaurant.stock_counts.modifier',
        'restaurant.stock_counts.supprimer',
        'restaurant.waste.creer',
        'rooms.cost_sheets.assumptions',
        'rooms.cost_sheets.import',
        'rooms.cost_sheets.items.creer',
        'rooms.cost_sheets.items.modifier',
        'rooms.cost_sheets.items.supprimer',
        'rooms.cost_sheets.starter',
        'rooms.creer',
        'rooms.images.supprimer',
        'rooms.import',
        'rooms.modifier',
        'rooms.supprimer',
        'rooms.types.creer',
        'rooms.types.import',
        'rooms.types.modifier',
        'rooms.types.supprimer',
        'rooms.updateStatus',
        'settings.cancellation_policies.creer',
        'settings.cancellation_policies.default',
        'settings.cancellation_policies.modifier',
        'settings.cancellation_policies.supprimer',
        'settings.import',
        'settings.modifier',
        'settings.packages.creer',
        'settings.packages.import',
        'settings.packages.modifier',
        'settings.packages.supprimer',
        'settings.partners.creer',
        'settings.partners.import',
        'settings.partners.modifier',
        'settings.partners.supprimer',
        'settings.services.creer',
        'settings.services.import',
        'settings.services.modifier',
        'settings.services.supprimer',
        'shop.cash_register.close.creer',
        'shop.cash_register.disbursements.creer',
        'shop.cash_register.open.creer',
        'shop.orders.creer',
        'shop.orders.paid',
        'shop.orders.refund',
        'shop.products.creer',
        'shop.products.import',
        'shop.products.modifier',
        'shop.products.supprimer',
        'users.creer',
        'users.modifier',
        'users.toggleStatus',
        // Administration : rôles et droits de l'hôtel, interventions.
        'droits.apercu',
        'droits.exceptions.creer',
        'droits.exceptions.supprimer',
        'droits.modifier',
        'interventions.creer',
        'interventions.terminer',
    ];

    /** Ce droit laisse-t-il seulement consulter ? */
    public static function estLecture(string $permission): bool
    {
        return !in_array($permission, self::ECRITURES, true);
    }

    /** @return list<string> droits qui écrivent */
    public static function ecritures(): array
    {
        return self::ECRITURES;
    }

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
     * La configuration de l'établissement que l'administrateur règle.
     * Décrire les chambres de l'hôtel n'est pas l'exploiter.
     */
    private const CONFIGURATION = [
        'rooms.creer', 'rooms.modifier', 'rooms.supprimer', 'rooms.images.supprimer', 'rooms.import',
        'rooms.types.creer', 'rooms.types.modifier', 'rooms.types.supprimer', 'rooms.types.import',
    ];

    /**
     * Paramètres de l'établissement que l'administrateur règle : l'onglet
     * Général seulement (SettingsTabs borne chaque onglet). Tarifs,
     * prestations et partenaires restent des décisions de la direction.
     */
    private const PARAMETRES = ['settings.modifier'];

    /**
     * Les comptes du personnel, que l'administrateur crée et tient. Avec la
     * configuration, ce sont ses seules écritures : il administre
     * l'application, il ne tient aucun service.
     */
    private const ADMINISTRATION = [
        'users.creer', 'users.modifier', 'users.toggleStatus',
        'droits.modifier', 'droits.apercu', 'droits.exceptions.creer', 'droits.exceptions.supprimer',
        'interventions.creer', 'interventions.terminer',
    ];

    /**
     * Services que l'auditeur qualité consulte pour ses contrôles : fiches de
     * ventes, produits, prestations et services de l'exploitation. Ni la
     * comptabilité, ni les caisses, ni les fiches de coût : le contrôle
     * financier relève du contrôle de gestion. Ni l'export du fichier clients :
     * ses contrôles portent sur les ventes, pas sur les personnes. Ni les
     * brouillons de réservation, travail en cours de la réception.
     */
    private const AUDIT_QUALITE_SERVICES = [
        'agenda', 'bookings', 'customers', 'economat', 'groups', 'housekeeping',
        'invoices', 'reception', 'restaurant', 'rooms', 'shop',
    ];

    private const AUDIT_QUALITE_EXCLUS = [
        'bookings.cash_register.', 'shop.cash_register.', 'rooms.cost_sheets.', 'customers.export',
        'bookings.drafts.',
    ];

    /** Catalogues de prestations, rangés dans les paramètres. */
    private const AUDIT_QUALITE_CATALOGUES = ['settings.services.export', 'settings.packages.export'];

    /** @var array<string, list<string>>|null */
    private static ?array $complet = null;

    /**
     * @return array<string, list<string>> droit => rôles qui le détiennent,
     *         hiérarchie comprise
     */
    public static function all(): array
    {
        return self::$complet ??= self::completer(self::gabarit());
    }

    /**
     * Le gabarit, complété de ce que la hiérarchie et les fonctions de
     * contrôle impliquent :
     *  - un chef détient les droits de ses membres, en chaîne ;
     *  - l'administrateur consulte tout, règle la configuration et tient
     *    les comptes. Il n'écrit rien de métier : il administre
     *    l'application, il ne tient aucun service ;
     *  - le support de l'éditeur consulte tout, sans rien extraire ;
     *  - l'auditeur qualité consulte les services d'exploitation.
     *
     * @param  array<string, list<string>>  $gabarit
     * @return array<string, list<string>>
     */
    private static function completer(array $gabarit): array
    {
        $complet = [];

        foreach ($gabarit as $droit => $roles) {
            $roles = RoleCatalog::avecCeuxQuiLesIncluent($roles);

            if (self::estLecture($droit) || in_array($droit, self::ecrituresDeLAdministrateur(), true)) {
                $roles[] = RoleCatalog::ADMIN;
            }

            // Le support diagnostique : il consulte, sans extraire de fichier
            // ni écrire.
            if (self::estLecture($droit) && !str_ends_with($droit, '.export')) {
                $roles[] = RoleCatalog::SUPPORT;
            }

            if (self::pourAuditQualite($droit)) {
                $roles[] = DutySegregation::CONTROLE_INDEPENDANT;
            }

            $roles = array_values(array_unique($roles));
            sort($roles);
            $complet[$droit] = $roles;
        }

        return $complet;
    }

    private static function pourAuditQualite(string $droit): bool
    {
        if (in_array($droit, self::AUDIT_QUALITE_CATALOGUES, true)) {
            return true;
        }

        if (!self::estLecture($droit)
            || !in_array(explode('.', $droit)[0], self::AUDIT_QUALITE_SERVICES, true)) {
            return false;
        }

        foreach (self::AUDIT_QUALITE_EXCLUS as $exclu) {
            if (str_starts_with($droit, $exclu)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Service d'exploitation dont relève un droit, pour les restrictions de
     * module que la console pose sur une personne (exclusion, lecture seule).
     *
     * Ce sont les services que gardait le middleware module.access : la
     * restriction s'applique désormais au droit lui-même, partout où la
     * question est posée — route, écran ou service.
     */
    private const SERVICES = [
        'rooms' => 'hebergement', 'bookings' => 'hebergement', 'groups' => 'hebergement',
        'customers' => 'hebergement', 'reception' => 'hebergement', 'agenda' => 'hebergement',
        'housekeeping' => 'housekeeping',
        'restaurant' => 'restaurant',
        'economat' => 'economat',
        'shop' => 'boutique',
        'settings' => 'parametres',
    ];

    public static function serviceDu(string $permission): ?string
    {
        return self::SERVICES[explode('.', $permission)[0]] ?? null;
    }

    /** @return list<string> droits de configuration de l'établissement */
    public static function configuration(): array
    {
        return self::CONFIGURATION;
    }

    /** @return list<string> seules écritures de l'administrateur : configuration et comptes */
    public static function ecrituresDeLAdministrateur(): array
    {
        return [...self::CONFIGURATION, ...self::PARAMETRES, ...self::ADMINISTRATION];
    }

    /**
     * Rôles qui reçoivent chaque droit pour eux-mêmes.
     *
     * @return array<string, list<string>>
     */
    private static function gabarit(): array
    {
        return [
            // ── Restauration ──
            'restaurant.billing.paid' => ['cashier'],
            'restaurant.billing.receipt' => ['cashier', 'controller', 'manager', 'reception'],
            'restaurant.billing.unpaid' => ['cashier'],
            'restaurant.billing.voir' => ['cashier', 'controller', 'manager', 'reception'],
            'restaurant.breakfast.serve' => ['restaurant_staff'],
            'restaurant.breakfast.voir' => ['cashier', 'controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.kitchen.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.menus.categories.creer' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.categories.modifier' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.categories.supprimer' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.export' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_manager', 'restaurant_staff'],
            'restaurant.menus.import' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.items.creer' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.items.modifier' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.items.supprimer' => ['restaurant_chief', 'restaurant_manager'],
            'restaurant.menus.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_manager', 'restaurant_staff'],
            'restaurant.orders.claim' => ['restaurant_staff'],
            'restaurant.orders.creer' => ['restaurant_staff'],
            'restaurant.orders.preparing' => ['restaurant_chief', 'restaurant_cook'],
            'restaurant.orders.ready' => ['restaurant_chief', 'restaurant_cook'],
            'restaurant.orders.reassign' => ['restaurant_manager'],
            'restaurant.orders.send_to_kitchen' => ['restaurant_staff'],
            'restaurant.orders.served' => ['restaurant_staff'],
            'restaurant.orders.status' => ['restaurant_manager'],
            'restaurant.orders.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],
            'restaurant.pantry.categories.creer' => ['restaurant_chief'],
            'restaurant.pantry.categories.modifier' => ['restaurant_chief'],
            'restaurant.pantry.categories.supprimer' => ['restaurant_chief'],
            'restaurant.pantry.export' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.pantry.import' => ['restaurant_chief'],
            'restaurant.pantry.items.creer' => ['restaurant_chief'],
            'restaurant.pantry.items.modifier' => ['restaurant_chief'],
            'restaurant.pantry.items.receive' => ['restaurant_chief'],
            'restaurant.pantry.items.supprimer' => ['restaurant_chief'],
            'restaurant.pantry.movements.creer' => ['restaurant_chief'],
            'restaurant.pantry.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_manager'],
            'restaurant.recipes.creer' => ['restaurant_chief'],
            'restaurant.recipes.export' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook'],
            'restaurant.recipes.import' => ['restaurant_chief'],
            'restaurant.recipes.modifier' => ['restaurant_chief'],
            'restaurant.recipes.produce' => ['restaurant_chief'],
            'restaurant.recipes.supprimer' => ['restaurant_chief'],
            'restaurant.recipes.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_manager'],
            'restaurant.shifts.close' => ['restaurant_staff'],
            'restaurant.shifts.open' => ['restaurant_staff'],
            'restaurant.stock_counts.close' => ['restaurant_chief'],
            'restaurant.stock_counts.creer' => ['restaurant_chief'],
            'restaurant.stock_counts.modifier' => ['restaurant_chief'],
            'restaurant.stock_counts.supprimer' => ['restaurant_chief'],
            'restaurant.stock_counts.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_manager'],
            'restaurant.consumption.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_manager'],
            'restaurant.waste.creer' => ['restaurant_chief', 'restaurant_cook'],
            'restaurant.waste.voir' => ['controller', 'manager', 'restaurant_chief', 'restaurant_cook', 'restaurant_staff'],

            // ── Comptabilité ──
            'accounting.cash' => ['accountant', 'manager'],
            'accounting.cash_reviews' => ['accountant', 'manager'],
            'accounting.cash_reviews.creer' => ['accountant'],
            'accounting.expenses' => ['accountant', 'manager'],
            'accounting.expenses.creer' => ['accountant'],
            'accounting.expenses.modifier' => ['accountant'],
            'accounting.expenses.supprimer' => ['accountant'],
            'accounting.income_statement' => ['accountant', 'manager'],
            'accounting.journal' => ['accountant', 'manager'],
            'accounting.ledger.accounts' => ['accountant', 'manager'],
            'accounting.ledger.aged' => ['accountant', 'manager'],
            'accounting.ledger.analytic' => ['accountant', 'manager'],
            'accounting.ledger.analytic.margins' => ['accountant', 'manager'],
            'accounting.ledger.analytic.mirror' => ['accountant'],
            'accounting.ledger.auxiliary' => ['accountant', 'manager'],
            'accounting.ledger.auxiliary.ledger' => ['accountant', 'manager'],
            'accounting.ledger.balance' => ['accountant', 'manager'],
            'accounting.ledger.entry' => ['accountant', 'manager'],
            'accounting.ledger.entry.reverse' => ['accountant'],
            'accounting.ledger.general' => ['accountant', 'manager'],
            'accounting.ledger.journals' => ['accountant', 'manager'],
            'accounting.ledger.night_audit' => ['accountant', 'manager'],
            'accounting.ledger.night_audit.run' => ['accountant'],
            'accounting.ledger.opening' => ['accountant', 'manager'],
            'accounting.ledger.opening.creer' => ['accountant'],
            'accounting.ledger.periods' => ['accountant', 'manager'],
            'accounting.ledger.periods.lock' => ['accountant'],
            'accounting.ledger.reconcile' => ['accountant'],
            'accounting.ledger.reconcile.auto' => ['accountant'],
            'accounting.ledger.reconcile.undo' => ['accountant'],
            'accounting.ledger.suppliers' => ['accountant', 'manager'],
            'accounting.ledger.suppliers.creer' => ['accountant'],
            'accounting.ledger.suppliers.voir' => ['accountant', 'controller', 'manager'],
            'accounting.ledger.voir' => ['accountant', 'controller', 'manager'],
            'accounting.ledger.withholding' => ['accountant', 'manager'],
            'accounting.ledger.years.open' => ['accountant'],
            'accounting.receivables' => ['accountant', 'manager'],
            // Journal des encaissements : consultation, comme le reste du module.
            'accounting.revenue_journal' => ['accountant', 'controller', 'manager'],
            'accounting.voir' => ['accountant', 'controller', 'manager'],

            // ── Réservations ──
            'bookings.approve' => ['manager'],
            'bookings.cancel' => ['manager', 'reception'],
            'bookings.cancellation_receipt' => ['controller', 'manager', 'reception'],
            'bookings.cash_register.close' => ['manager', 'reception'],
            'bookings.cash_register.close.creer' => ['manager', 'reception'],
            'bookings.cash_register.disbursements.creer' => ['manager', 'reception'],
            'bookings.cash_register.open' => ['manager', 'reception'],
            'bookings.cash_register.open.creer' => ['manager', 'reception'],
            'bookings.cash_register.voir' => ['controller', 'manager', 'reception'],
            'bookings.checkIn' => ['manager', 'reception'],
            'bookings.checkOut' => ['manager', 'reception'],
            'bookings.checkin_code.send' => ['manager', 'reception'],
            'bookings.confirm' => ['manager', 'reception'],
            'bookings.creer' => ['manager', 'reception'],
            'bookings.drafts.continue' => ['manager', 'reception'],
            'bookings.drafts.resume' => ['manager', 'reception'],
            'bookings.drafts.save' => ['manager', 'reception'],
            'bookings.drafts.supprimer' => ['manager', 'reception'],
            'bookings.drafts.voir' => ['controller', 'manager', 'reception'],
            'bookings.folio.add' => ['manager', 'reception'],
            'bookings.folio.remove' => ['manager', 'reception'],
            'bookings.modifier' => ['manager', 'reception'],
            'bookings.payment.add' => ['manager', 'reception'],
            'bookings.voir' => ['controller', 'manager', 'reception'],

            // ── Économat ──
            'economat.categories.creer' => ['econome'],
            'economat.categories.modifier' => ['econome'],
            'economat.categories.supprimer' => ['econome'],
            'economat.categories.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.control.suggestions.creer' => ['econome'],
            'economat.control.suggestions.voir' => ['controller', 'econome', 'manager'],
            'economat.control.variances.voir' => ['controller', 'econome', 'manager'],
            'economat.control.voir' => ['controller', 'econome', 'manager'],
            'economat.items.adjust' => ['econome'],
            'economat.items.creer' => ['econome'],
            'economat.items.export' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.items.import' => ['econome'],
            'economat.items.modifier' => ['econome'],
            'economat.items.opening' => ['econome'],
            'economat.items.supprimer' => ['econome'],
            'economat.items.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.orders.cancel' => ['econome'],
            'economat.orders.creer' => ['econome'],
            'economat.orders.export' => ['controller', 'econome', 'manager'],
            'economat.orders.receive' => ['econome', 'storekeeper'],
            'economat.orders.send' => ['econome'],
            'economat.orders.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.purchase_requests.approve' => ['manager'],
            'economat.purchase_requests.cancel' => ['econome', 'housekeeping_leader', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'economat.purchase_requests.convert' => ['econome'],
            'economat.purchase_requests.creer' => ['econome', 'housekeeping_leader', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'economat.purchase_requests.reject' => ['manager'],
            'economat.purchase_requests.voir' => ['controller', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'economat.receipts.cancel' => ['econome'],
            'economat.receipts.creer' => ['econome', 'storekeeper'],
            'economat.receipts.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.receipts.export' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.requisitions.approve' => ['econome'],
            'economat.requisitions.cancel' => ['econome', 'housekeeping_leader', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'economat.requisitions.creer' => ['accountant', 'econome', 'housekeeping_leader', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'economat.requisitions.deliver' => ['econome', 'storekeeper'],
            'economat.requisitions.reject' => ['econome'],
            'economat.requisitions.voir' => ['accountant', 'controller', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager', 'storekeeper'],
            // Extraire une liste n'est pas la consulter : droit distinct.
            'economat.requisitions.export' => ['controller', 'econome', 'housekeeping_leader', 'manager', 'reception', 'restaurant_chief', 'restaurant_manager', 'shop_manager', 'storekeeper'],
            'economat.suppliers.creer' => ['econome'],
            'economat.suppliers.modifier' => ['econome'],
            'economat.suppliers.supprimer' => ['econome'],
            'economat.suppliers.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.stores.counts.cancel' => ['econome'],
            'economat.stores.counts.close' => ['econome'],
            'economat.stores.counts.creer' => ['econome'],
            'economat.stores.counts.modifier' => ['econome', 'storekeeper'],
            'economat.stores.counts.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.stores.creer' => ['econome'],
            'economat.stores.modifier' => ['econome'],
            'economat.stores.supprimer' => ['econome'],
            'economat.stores.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.stock_counts.cancel' => ['econome'],
            'economat.stock_counts.close' => ['econome'],
            'economat.stock_counts.creer' => ['econome'],
            'economat.stock_counts.modifier' => ['econome', 'storekeeper'],
            'economat.stock_counts.report' => ['controller', 'econome', 'manager'],
            'economat.stock_counts.voir' => ['controller', 'econome', 'manager', 'storekeeper'],
            'economat.voir' => ['controller', 'econome', 'manager', 'storekeeper'],

            // ── Chambres ──
            'rooms.cost_sheets.assumptions' => ['accountant', 'manager'],
            'rooms.cost_sheets.export' => ['accountant', 'controller', 'manager'],
            'rooms.cost_sheets.import' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.creer' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.modifier' => ['accountant', 'manager'],
            'rooms.cost_sheets.items.supprimer' => ['accountant', 'manager'],
            'rooms.cost_sheets.starter' => ['accountant', 'manager'],
            'rooms.cost_sheets.voir' => ['accountant', 'controller', 'manager'],
            'rooms.cost_sheets.document' => ['accountant', 'controller', 'manager'],
            'rooms.creer' => ['manager', 'reception_chief'],
            'rooms.export' => ['controller', 'manager', 'reception'],
            'rooms.images.supprimer' => ['manager', 'reception_chief'],
            'rooms.import' => ['manager', 'reception_chief'],
            'rooms.modifier' => ['manager', 'reception_chief'],
            'rooms.supprimer' => ['manager', 'reception_chief'],
            'rooms.types.creer' => ['manager', 'reception_chief'],
            'rooms.types.export' => ['controller', 'manager', 'reception'],
            'rooms.types.import' => ['manager', 'reception_chief'],
            'rooms.types.modifier' => ['manager', 'reception_chief'],
            'rooms.types.supprimer' => ['manager', 'reception_chief'],
            'rooms.updateStatus' => ['manager', 'reception'],
            'rooms.voir' => ['controller', 'manager', 'reception'],

            // ── Paramètres ──
            'settings.cancellation_policies.creer' => ['manager'],
            'settings.cancellation_policies.default' => ['manager'],
            'settings.cancellation_policies.modifier' => ['manager'],
            'settings.cancellation_policies.supprimer' => ['manager'],
            'settings.export' => ['controller', 'housekeeping_leader', 'manager', 'reception_chief', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'settings.import' => ['housekeeping_leader', 'manager', 'reception_chief', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'settings.modifier' => ['housekeeping_leader', 'manager', 'reception_chief', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],
            'settings.packages.creer' => ['manager'],
            'settings.packages.export' => ['controller', 'manager'],
            'settings.packages.import' => ['manager'],
            'settings.packages.modifier' => ['manager'],
            'settings.packages.supprimer' => ['manager'],
            'settings.partners.creer' => ['manager'],
            'settings.partners.export' => ['controller', 'manager'],
            'settings.partners.import' => ['manager'],
            'settings.partners.modifier' => ['manager'],
            'settings.partners.supprimer' => ['manager'],
            'settings.services.creer' => ['manager'],
            'settings.services.export' => ['controller', 'manager'],
            'settings.services.import' => ['manager'],
            'settings.services.modifier' => ['manager'],
            'settings.services.supprimer' => ['manager'],
            'settings.voir' => ['controller', 'housekeeping_leader', 'manager', 'reception_chief', 'restaurant_chief', 'restaurant_manager', 'shop_manager'],

            // ── Boutique ──
            'shop.cash_register.close' => ['manager', 'shop_cashier', 'shop_manager'],
            'shop.cash_register.close.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.disbursements.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.open' => ['manager', 'shop_cashier', 'shop_manager'],
            'shop.cash_register.open.creer' => ['shop_cashier', 'shop_manager'],
            'shop.cash_register.voir' => ['controller', 'manager', 'shop_manager'],
            'shop.orders.creer' => ['shop_cashier', 'shop_manager'],
            'shop.orders.paid' => ['shop_cashier', 'shop_manager'],
            'shop.orders.receipt' => ['controller', 'manager', 'reception', 'shop_cashier', 'shop_manager'],
            'shop.orders.refund' => ['shop_cashier', 'shop_manager'],
            'shop.orders.voir' => ['controller', 'manager', 'reception', 'shop_cashier', 'shop_manager'],
            'shop.products.creer' => ['shop_manager'],
            'shop.products.export' => ['controller', 'manager', 'shop_manager'],
            'shop.products.import' => ['shop_manager'],
            'shop.products.modifier' => ['shop_manager'],
            'shop.products.supprimer' => ['shop_manager'],
            'shop.products.voir' => ['controller', 'manager', 'shop_manager'],

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
            'groups.voir' => ['controller', 'manager', 'reception'],

            // ── Housekeeping ──
            'housekeeping.assignments.creer' => ['housekeeping_leader', 'manager'],
            'housekeeping.available' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.clean' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.inspect' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.issue' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.ready' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.reject' => ['housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],
            'housekeeping.teams.creer' => ['housekeeping_leader', 'manager'],
            'housekeeping.voir' => ['controller', 'housekeeping', 'housekeeping_leader', 'housekeeping_staff', 'manager'],

            // ── Clients ──
            'customers.creer' => ['manager', 'reception'],
            'customers.export' => ['controller', 'manager', 'reception'],
            'customers.import' => ['manager', 'reception'],
            'customers.modifier' => ['manager', 'reception'],
            'customers.voir' => ['controller', 'manager', 'reception'],

            // ── Réception ──
            'reception.pos.history' => ['manager', 'reception'],
            'reception.pos.receipt' => ['manager', 'reception'],
            'reception.pos.sales.creer' => ['manager', 'reception'],
            'reception.pos.voir' => ['controller', 'manager', 'reception'],

            // ── Utilisateurs ──
            // ── Administration ──
            // L'administrateur (ajouté par completer()) règle la couche de
            // l'hôtel, déclare ses interventions et lit le journal. Le contrôle
            // de gestion consulte droits et journal ; la direction suit les
            // interventions et les sessions du support.
            'droits.voir' => ['controller'],
            'droits.apercu' => [],
            'droits.modifier' => [],
            'droits.exceptions.creer' => [],
            'droits.exceptions.supprimer' => [],
            'interventions.voir' => ['manager'],
            'interventions.creer' => [],
            'interventions.terminer' => [],
            'audit.voir' => ['controller'],
            'support.sessions.voir' => ['manager'],

            'users.creer' => ['manager'],
            'users.modifier' => ['manager'],
            'users.toggleStatus' => ['manager'],
            'users.voir' => ['controller', 'manager'],

            // ── Agenda ──
            'agenda.voir' => ['controller', 'manager', 'reception'],

            // ── Analytique ──
            'analytics.voir' => ['controller', 'manager'],

            // ── Factures ──
            'invoices.voir' => ['controller', 'manager', 'reception'],

            // ── Divers ──
            'test-popup.voir' => ['controller', 'manager'],
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
