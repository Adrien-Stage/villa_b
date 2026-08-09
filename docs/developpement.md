# Développement

## Stack

| Élément | Version |
|---|---|
| PHP | 8.4 dans l'image (`^8.2` requis par Composer) |
| Laravel | 12 |
| Front | Blade + Alpine.js + Tailwind CSS 4, compilé par Vite 7 |
| Base | PostgreSQL 16 |
| Tests | Pest |
| Style | Laravel Pint |

Paquets notables : `minishlink/web-push` (notifications), `resend/resend-laravel`
(e-mails).

## Le cycle de livraison

C'est la chose la plus importante à comprendre avant de modifier quoi que ce soit.

```
modifier → push sur main → GitHub Actions build et publie l'image
                                    ↓
                    ghcr.io/adrien-stage/villa_b:latest + :sha-<court>
                                    ↓
              la console d'administration met à jour l'établissement
```

> **Modifier ce dépôt ne change rien à un établissement en service.** Chaque
> établissement est épinglé sur un **digest d'image**, pas sur un tag. Tant qu'une
> mise à jour n'est pas explicitement demandée depuis la console, il continue de
> tourner sur la version qu'il a.

Conséquence pratique : **ne pas tenter d'itérer sur un établissement provisionné**.
Le conteneur exécute une image figée ; une modification du code local n'y apparaîtra
jamais. Développer en local, valider, puis livrer par le cycle ci-dessus.

### La CI

[`.github/workflows/build-image.yml`](../.github/workflows/build-image.yml) — sur
chaque push sur `main` :

- build de l'image, poussée sur GHCR ;
- deux tags : `latest` et `sha-<court>`.

Le tag `sha-` est ce qui rend une mise à jour **réversible** : la console peut
réépingler un établissement sur une version antérieure.

L'image est publique — aucune authentification n'est requise pour le pull.

## Ce que fait l'entrypoint (et ce qu'il ne fait pas)

Deux règles gouvernent la façon de livrer une évolution de données.

> **Les seeders d'installation ne sont jamais rejoués.** `ProductionTenantSeeder` ne
> tourne qu'au tout premier démarrage. Ajouter un seeder n'aura **aucun effet** sur un
> établissement existant.

> **`roles:sync` est rejoué à chaque démarrage.** C'est la seule donnée de référence
> qui se propage seule. Un rôle ajouté à
> [`RoleCatalog`](../app/Support/RoleCatalog.php) arrive tout seul, y compris sur un
> établissement en service.

Pour propager une autre donnée de référence, il faut donc :

- l'ajouter à `RoleCatalog` si c'est un rôle ; **ou**
- écrire une **migration de données** — le seul autre mécanisme rejoué au démarrage.

## Conventions

### Toute écriture de stock passe par son service

`StockService` pour l'économat, `RestaurantStockService` pour le garde-manger. Un
contrôleur qui écrirait directement dans `stock_items` ou `restaurant_pantry_items`
casserait silencieusement le coût moyen pondéré et la piste d'audit.

Même logique pour les autres invariants : `CheckOutService` pour le départ,
`RoomAvailabilityService` pour la disponibilité, `Notifier` pour les notifications.

### Les montants sont en centimes

Entiers, en centimes FCFA. Aucun flottant dans les calculs financiers.

### Les commentaires expliquent le pourquoi

Le code documente les arbitrages et les pièges, pas ce que fait la ligne suivante.
Beaucoup de commentaires sont la seule trace d'un bug corrigé — les lire avant de
modifier le bloc qu'ils décrivent.

### Ordre des routes CSV

Les routes d'import/export sont déclarées **avant** les routes à paramètre :

```php
Route::get('/export', …);        // sinon "export" serait lu comme un id
Route::get('/{room}', …);
```

Le motif est répété partout où un binding de modèle pourrait capturer un segment
littéral.

### Les URLs ne se terminent jamais par une extension

Une route Laravel dont l'URL finit par `.png`, `.js` ou `.css` est interceptée par la
règle nginx des fichiers statiques et **n'atteint jamais PHP**. C'est pourquoi
l'icône PWA est servie par `/pwa/icon/{size}` sans extension.

> Ce type de panne est **invisible en test** : les tests Laravel ne passent pas par
> nginx. Le test passe, la production renvoie 404.

### Deux systèmes de rôles

Table pivot `role_user` **et** colonne `role` héritée. `hasRole()` interroge les deux.
Ne pas en supprimer un sans traiter l'autre.

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite **en mémoire** (`phpunit.xml`) : ils ne touchent jamais la
base de développement.

### État de la suite

**228 tests passent, 6 échouent** — 33 fichiers de test, 961 assertions.

La suite est globalement saine et couvre les domaines sensibles : disponibilité,
tarification des packs, économat, housekeeping, fiches techniques, imports/exports
CSV, synchronisation des rôles, PWA, notifications, intégrité des vues Blade.

Les 6 échecs sont de **vraies assertions en échec**, pas des classes manquantes :

| Test | Symptôme |
|---|---|
| `AuditLogTest > admin can toggle user status and reset password` | Chaîne attendue différente |
| `BookingCalendarTest > calendar view … filters only confirmed bookings` | Le calendrier retourne une réservation qu'il ne devrait pas |
| `BookingTaxAndBookerTest > shop cashier can create a shop order with 0% VAT` | 403 au lieu d'une redirection — droit refusé |
| `ReceptionCashRegisterTest > only manager can access close form` | Attente de droit non satisfaite |
| `ReceptionCashRegisterTest > manager can approve pending complimentary booking` | Idem |
| `ReceptionCashRegisterTest > complimentary bookings trigger in-app notifications` | Notification attendue non émise |

> Ces échecs se répartissent entre deux causes plausibles : des **régressions
> réelles** (notamment sur les droits de caisse et les réservations offertes) et des
> **attentes de test devenues obsolètes** après une évolution des règles d'accès.
> Chacun demande à être tranché individuellement — ils ne sont pas interchangeables.

Au moment de la rédaction, l'arbre de travail contient des modifications non
committées sur `BookingController.php` et `bookings/index.blade.php` ; l'échec du
calendrier pourrait leur être lié.

### Tests notables

| Fichier | Ce qu'il protège |
|---|---|
| `BladeIntegrityTest` | Toutes les vues compilent — filet contre une erreur de syntaxe Blade |
| `RoleCatalogSyncTest` | `roles:sync` est bien idempotent |
| `RoomAvailabilityDelayTest` | La règle du délai de remise en état |
| `CsvImportExportTest`, `SeedCsvFilesTest` | L'aller-retour import/export sans perte |
| `PwaTest` | Manifeste et icônes |
| `NotificationsWiringTest` | Les notifications partent aux bons destinataires |

## Style de code

```bash
./vendor/bin/pint
```

```bash
./vendor/bin/pint --test
```

## Dette technique connue

| Sujet | Détail |
|---|---|
| **`tenant_id` vestigial** | Table `tenants` et colonnes `tenant_id` héritées d'une conception mutualisée. Une seule ligne utile ; l'isolation réelle est physique (un conteneur, une base) |
| **Double système de rôles** | Pivot + colonne héritée, les deux consultés partout |
| **`BookingController` : 1366 lignes** | Le plus gros contrôleur, candidat au découpage |
| **`GroupBookingController` : 824 lignes** | Duplique une partie de la logique de `BookingController` |
| **6 tests en échec** | Voir ci-dessus — à trancher un par un |
| **Nommage `MEKA ERP`** | Subsiste dans le `Dockerfile` et l'entrypoint (messages de démarrage). Cosmétique, sans effet |

Le nommage Docker en `meka-erp-*` côté console est en revanche **volontaire** et ne
doit pas être « corrigé » : il préserve les déploiements existants.

## Travailler avec les autres dépôts

| Besoin | Où |
|---|---|
| Créer, superviser, mettre à jour un établissement | `wetchah_erp` |
| Le site vitrine public | `wetchah_site` |
| Le contenu marketing du site | `wetchah_erp`, espace éditeur |

L'API publique de ce dépôt est le contrat entre l'application et le site vitrine :
la modifier impose de vérifier `wetchah_site`. Voir
[APIs et intégrations](apis-et-integrations.md).

## Pour aller plus loin

- [Architecture](architecture.md) — services, modèle de données, conventions
- [Installation](installation.md) — environnement local
- [Configuration](configuration.md) — variables et paramètres
