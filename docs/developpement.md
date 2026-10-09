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

### Les rôles sont des affectations

Les rôles d'une personne sont ses lignes de `role_user`, et rien d'autre. L'attribut
`$user->role` donne le rôle principal (la première affectation) et, écrit, affecte ce
rôle. Les droits se demandent au moteur (`@droit`, `PermissionResolver::allows`),
jamais au rôle. Voir le [Guide des rôles et des droits](guide-roles-et-droits.md).

### Toute liste passe par `<x-table>`

Une liste d'enregistrements — réservations, commandes, articles, fournisseurs… — s'écrit
avec le composant de tableau (`resources/views/components/table/`), jamais avec un
`<table>` stylé à la main : toutes les listes ont ainsi le même aspect, la même
pagination et le même menu d'actions.

```blade
<x-table :rows="$commandes" empty="Aucune commande." empty-icon="receipt" caption="Commandes">
    <x-slot:head>
        <x-table.col>Commande</x-table.col>
        <x-table.col hide="lg">Serveur</x-table.col>
        <x-table.col align="right">Total</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($commandes as $commande)
        <x-table.row :href="route('restaurant.orders.show', $commande)">
            <x-table.cell><a href="…">#{{ $commande->id }}</a></x-table.cell>
            <x-table.cell hide="lg">{{ $commande->assignedServer?->name }}</x-table.cell>
            <x-table.cell align="right" nowrap>{{ … }} FCFA</x-table.cell>
            <x-table.actions :label="'Actions pour la commande #'.$commande->id">
                <x-table.action :href="route('restaurant.orders.show', $commande)" icon="eye">Ouvrir</x-table.action>
                <x-table.action :action="route('…destroy', $commande)" method="DELETE" icon="trash-2"
                    tone="danger" confirm="Supprimer cette commande ?">Supprimer</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
```

- `rows` : la page (paginateur) ou la collection. Elle décide de l'état vide et de la
  pagination en pied.
- `hide` (colonne et cellule) masque une colonne secondaire quand le **tableau** est plus
  étroit qu'un palier : sm 672 px, md 768, lg 896, xl 1024, 2xl 1152, 3xl 1280. C'est
  la largeur du tableau qui compte (container query), pas celle de l'écran.
- Les actions s'affichent en boutons à partir du palier `inline` du tableau (xl par
  défaut), dans un menu ⋮ en dessous. La colonne d'actions reste collée à droite quand
  le tableau défile.
- `x-table.action` : `href` (lien), `action` + `method` (formulaire, `fields` pour les
  champs cachés, `form-class`), ou un bouton (`onclick`, `x-on:click`). `tone` : danger,
  success. `confirm` pose la question avant d'agir. Pour une charge utile JSON dans
  `x-on:click`, passez par `Js::from()` : `@json` dans un attribut de composant n'est pas
  compilé.
- Emplacements facultatifs : `toolbar` (titre, filtres), `foot` (totaux),
  `emptyActions` (« effacer les filtres », « créer le premier »).

Les états imprimables, les états comptables (balance, grand livre…) et les grilles de
saisie gardent leurs propres tableaux : ce ne sont pas des listes.

### Tout ce qui s'imprime se retrouve dans les Éditions

La rubrique **Éditions** (`/editions`) réunit les registres, situations et journaux de
l'établissement, la recherche d'une pièce par son numéro, et les liens vers les
documents qui ont déjà leur écran (grand livre, fiches de comptage, PV d'inventaire…).

Une édition est une classe de `app/Editions/Documents`, inscrite dans
[`Catalogue`](../app/Editions/Catalogue.php). Elle déclare :

- sa famille, son titre et sa description ;
- ses **droits** : au moins un de ceux qui ouvrent déjà les mêmes données ailleurs —
  l'édition n'ouvre rien que l'écran d'origine ne montrerait pas ;
- ses **filtres** (`Filtre::periode`, `jour`, `semaine`, `choix`), lus et validés avant
  de lui parvenir ;
- son `document()` : un `Document` que `DocumentExporter` rend à l'écran, à
  l'impression, en PDF, Excel et Word.

Les éditions financières lisent [`Registres`](../app/Editions/Registres.php) : une vente
y est comptée **une fois, là où elle naît** (un repas porté à la chambre est une vente
du restaurant, pas une seconde vente au séjour ; les nuitées se comptent nuit par nuit
pour les séjours effectués) et un encaissement **sur sa pièce** (une consommation portée
à la chambre n'est pas encaissée). `EditionsTest` affiche et imprime chaque édition du
catalogue.

### Un document imprimé ne laisse aucune marge haute ou basse au navigateur

Chrome ajoute son propre en-tête et son propre pied de page (titre et adresse de la
page, date, « 1 sur 3 ») dès que la feuille a une marge haute ou basse. Toute règle
`@page` garde donc ces deux marges à zéro.

Un document de plusieurs pages inclut le réglage commun dans sa balise `<style>` :

```blade
@include('partials.impression', ['haut' => '14mm', 'bas' => '16mm', 'pied' => 'Bon de commande '.$order->number])
```

Il met les marges haute et basse à zéro, refait le blanc en tête et en pied de chaque
page (`box-decoration-break: clone` sur le corps), numérote les pages dans la marge de
droite et répète le texte de `pied` au bas de chaque feuille. Les tickets de caisse
(`size: auto; margin: 0`) n'en ont pas besoin. Le PDF (dompdf) n'est pas concerné :
il n'ajoute rien. `ImpressionSansEnTeteTest` lit toutes les règles `@page` des vues.

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite **en mémoire** (`phpunit.xml`) : ils ne touchent jamais la
base de développement.

### État de la suite

La suite complète passe (plus de mille tests). Elle couvre les domaines sensibles :
disponibilité, tarification des packs, économat, housekeeping, restaurants,
caisses, fiches techniques, imports/exports CSV, rôles et droits, PWA,
notifications, intégrité des vues Blade.

Lancez-la en entier, **sans `--parallel`** : quelques fichiers partagent des
fonctions d'aide déclarées dans un autre fichier de test, et le mode parallèle les
sépare.

### Tests notables

| Fichier | Ce qu'il protège |
|---|---|
| `BladeIntegrityTest` | Toutes les vues compilent — filet contre une erreur de syntaxe Blade |
| `ImpressionSansEnTeteTest` | Aucune page imprimée ne porte l'en-tête et le pied de page du navigateur |
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
| **`BookingController` : 1366 lignes** | Le plus gros contrôleur, candidat au découpage |
| **`GroupBookingController` : 824 lignes** | Duplique une partie de la logique de `BookingController` |
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
