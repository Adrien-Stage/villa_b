# Architecture

## Un établissement, une instance

L'application est **mono-établissement par déploiement**. Chaque client reçoit son
propre conteneur et sa propre base PostgreSQL, provisionnés par la console
d'administration.

Il existe bien une table `tenants` et des colonnes `tenant_id`, mais **elles ne
contiennent qu'une seule ligne utile** : celle de l'établissement courant, créée au
premier démarrage à partir des variables d'environnement injectées.

> Ces colonnes sont un héritage de la conception initiale (un ERP mutualisé), pas un
> mécanisme d'isolation actif. L'isolation est **physique** : deux établissements ne
> partagent ni conteneur, ni base, ni disque. Voir
> [Développement — dette connue](developpement.md#dette-technique-connue).

## Comment l'application arrive chez le client

Elle n'est jamais déployée à la main.

```
push sur main
     ↓
GitHub Actions build l'image → ghcr.io/adrien-stage/villa_b (latest + sha-court)
     ↓
la console d'administration tire l'image et l'épingle sur un digest exact
     ↓
docker compose up → l'entrypoint migre, initialise, synchronise les rôles
     ↓
l'établissement tourne
```

Chaque établissement est **épinglé sur un digest**. Un nouveau build n'impacte
personne tant qu'une mise à jour n'est pas explicitement demandée depuis la console.

Conséquence pratique pour le développement : **modifier ce dépôt ne change rien à un
établissement en service**. Voir [Développement](developpement.md).

## Ce que le conteneur reçoit

Le `docker-compose` généré par la console injecte tout le contexte de
l'établissement :

| Variable | Contenu |
|---|---|
| `APP_NAME`, `APP_KEY`, `APP_URL` | Identité et URL publique |
| `DB_*` | Base PostgreSQL dédiée, sur le réseau Docker interne |
| `TENANT_SLUG`, `TENANT_CURRENCY` | Identifiant et devise |
| `TENANT_SETTINGS` | JSON — pays, ville, thème de couleurs |
| `TENANT_MODULES` | JSON — modules actifs, ex. `["restaurant","shop"]` |
| `ASSISTANCE_SECRET` | Vérification des jetons du mode assistance |
| `REPORTING_SECRET` | Protection de l'API de reporting |
| `VAPID_*` | Notifications Web Push |

## L'entrypoint

[`docker/entrypoint.sh`](../docker/entrypoint.sh) s'exécute à **chaque** démarrage,
dans cet ordre :

1. **Attendre PostgreSQL** — `pg_isready`, 30 tentatives à 3 s.
2. **Générer `APP_KEY`** si elle est absente.
3. **Mettre en cache** config, routes et vues.
4. **Migrer** — `migrate --force`.
5. **Initialiser** — `ProductionTenantSeeder`, **uniquement si aucun tenant n'existe**.
6. **Synchroniser les rôles** — `roles:sync`, **à chaque démarrage**.
7. **Lier le storage** et ajuster les permissions.

Deux points méritent l'attention, car ils déterminent comment livrer une évolution :

> **Les seeders d'installation ne sont jamais rejoués.** `ProductionTenantSeeder` ne
> tourne qu'à la toute première mise en service. Sur un établissement existant, un
> nouveau seeder **n'aura aucun effet**. Toute donnée de référence à propager doit
> passer par `roles:sync` ou par une migration de données.

> **`roles:sync` est l'exception, volontairement.** C'est ce qui permet à un rôle
> ajouté dans [`RoleCatalog`](../app/Support/RoleCatalog.php) d'arriver sur un
> établissement déjà en service. L'opération est idempotente et ne touche jamais au
> pivot `role_user`.

Le seeder de production ne crée **aucun utilisateur** : le premier manager est créé
depuis la console d'administration, qui écrit directement dans cette même base.

## Modules

Les modules optionnels sont lus depuis `TENANT_MODULES` par
[`TenantModules`](../app/Support/TenantModules.php) :

`restaurant` · `shop` · `housekeeping` · `accounting` · `analytics` ·
`discussions` · `ai` · `api` · `website`

Les **modules cœur** — chambres, réservations, clients, utilisateurs, paramètres —
sont toujours actifs et ne figurent pas dans cette liste.

Deux middlewares les font respecter :

| Middleware | Alias | Rôle |
|---|---|---|
| [`EnsureModuleEnabled`](../app/Http/Middleware/EnsureModuleEnabled.php) | `module:restaurant` | Bloque l'accès par URL à un module désactivé — pas seulement le lien dans le menu |
| [`EnsureModuleWriteAccess`](../app/Http/Middleware/EnsureModuleWriteAccess.php) | `module.access:restaurant` | Distingue lecture et écriture selon le niveau de l'utilisateur |

> Masquer un lien dans la barre latérale ne protège rien. `module:` existe pour que
> l'accès direct à `/restaurant/menus` échoue aussi quand le module est coupé.

## La couche services

Le cœur métier vit dans `app/Services/`, pas dans les contrôleurs. Treize services,
chacun propriétaire d'un invariant :

| Service | Responsabilité |
|---|---|
| [`RoomAvailabilityService`](../app/Services/RoomAvailabilityService.php) | Ce qu'on affiche comme disponible, et ce qu'on accepte de réserver |
| [`CheckOutService`](../app/Services/CheckOutService.php) | Orchestration complète du départ, en une transaction |
| [`LoyaltyService`](../app/Services/LoyaltyService.php) | Points et niveaux de fidélité |
| [`RoomCostingService`](../app/Services/RoomCostingService.php) | Coût par nuitée occupée et marge de contribution |
| [`RestaurantStockService`](../app/Services/RestaurantStockService.php) | Le garde-manger : stock théorique, coût moyen pondéré, piste d'audit |
| [`RestaurantAssignmentService`](../app/Services/RestaurantAssignmentService.php) | Répartition des commandes du portail entre serveurs |
| [`StockService`](../app/Services/StockService.php) | Moteur unique des mouvements de l'économat |
| [`StockRequisitionService`](../app/Services/StockRequisitionService.php) | Cycle des demandes des départements |
| [`PurchaseOrderService`](../app/Services/PurchaseOrderService.php) | Cycle des bons de commande |
| [`AccountingService`](../app/Services/AccountingService.php) | Comptabilité de caisse, anti-double-comptage |
| [`BusinessReportingService`](../app/Services/BusinessReportingService.php) | Données de l'API de reporting |
| [`Notifier`](../app/Services/Notifier.php) | Point d'envoi unique des notifications |
| [`AiToolsService`](../app/Services/AiToolsService.php) | Outils de l'assistant IA |

Le motif est constant et vaut d'être respecté :

> **Toute écriture de stock passe par son service.** C'est la seule façon de garantir
> que le stock, le coût moyen pondéré et la piste d'audit restent cohérents entre
> eux. Un contrôleur qui écrirait directement dans `stock_items` casserait
> silencieusement la valorisation.

## Modèle de données

57 modèles, 85 migrations. Regroupés par domaine :

### Hébergement

```
RoomType ──1──n── Room ──1──n── RoomImage
   │                 │
   │                 └──1──n── RoomStatusHistory
   ├──1──n── RoomRate
   └──1──1── RoomCostSheet ──1──n── RoomCostItem

Booking ──n──1── Room, Customer, GroupBooking
   ├──1──n── FolioItem      ← toutes les prestations du séjour
   ├──1──n── Payment
   └──1──1── Invoice ──1──n── InvoiceItem

RoomPackage · ServiceItem · PartnerOrganization · Guest · LoyaltyTransaction
```

### Restaurant

```
RestaurantMenuCategory ──1──n── RestaurantMenuItem
                                    └──1──1── RestaurantRecipe ──1──n── RestaurantRecipeLine
                                                                              │
RestaurantPantryCategory ──1──n── RestaurantPantryItem ───────────────────────┘
                                       └──1──n── RestaurantPantryMovement

RestaurantCustomerOrder ──1──n── RestaurantCustomerOrderItem
RestaurantShift · RestaurantStockCount ──1──n── RestaurantStockCountLine
```

### Économat, boutique, ménage

```
StockCategory ──1──n── StockItem ──1──n── StockMovement
Supplier ──1──n── PurchaseOrder ──1──n── PurchaseOrderLine
StockRequisition ──1──n── StockRequisitionLine

ShopCategory ──1──n── ShopProduct ;  ShopOrder ──1──n── ShopOrderItem

HousekeepingTeam ──n──n── User ;  HousekeepingAssignment
```

### Transverse

```
Tenant · User ──n──n── Role (pivot porte le niveau read/write)
CashRegisterSession ──1──n── CashRegisterDisbursement
Expense · AuditLog · PushSubscription
DiscussionConversation ──1──n── DiscussionMessage
```

## Conventions

### Les montants sont en centimes

Tous les montants monétaires sont stockés en **centimes FCFA**, en entier. Aucun
flottant ne circule dans les calculs financiers.

### Deux systèmes de rôles cohabitent

L'application utilise une table pivot `role_user` **et** une colonne `role` héritée.
`hasRole()` et `hasAnyRole()` interrogent d'abord la relation, puis retombent sur la
colonne.

> Ne pas « nettoyer » l'un des deux sans traiter l'autre : `Notifier` résout ses
> destinataires en tenant compte des deux, et des comptes anciens n'existent que dans
> la colonne.

### Le folio est le registre du séjour

`FolioItem` enregistre **toutes** les prestations d'un séjour — hébergement,
restaurant, boutique, spa, minibar, remises — payantes ou offertes. C'est le point
de jonction entre les modules : une commande restaurant réglée « à la chambre »
devient une ligne de folio.

### Import/export CSV

Le trait [`HandlesCsv`](../app/Http/Controllers/Concerns/HandlesCsv.php) uniformise
les échanges : UTF-8 avec BOM, délimiteur `;` (Excel français), parseur tolérant au
BOM et à la virgule.

> **Un export produit exactement la structure attendue par l'import.** L'aller-retour
> est sans perte — c'est ce qui permet à un client de modifier son catalogue dans
> Excel et de le réinjecter.

[`CsvSanitizer`](../app/Support/CsvSanitizer.php) neutralise l'injection de formules
dans les cellules exportées.

## Pour aller plus loin

- [Rôles et accès](roles-et-acces.md) — RBAC détaillé
- [Configuration](configuration.md) — variables et paramètres
- [Développement](developpement.md) — cycle de livraison et dette
