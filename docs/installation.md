# Installation

Deux scénarios très différents, à ne pas confondre.

| Scénario | Comment |
|---|---|
| **Production** | L'application n'est **jamais installée à la main**. Elle est provisionnée par la console d'administration à partir d'une image Docker |
| **Développement** | Installation locale classique, avec un jeu de données de démonstration |

---

## Production — comment l'application arrive chez le client

Il n'y a rien à installer sur le serveur du client au-delà de la console
d'administration. Le cycle complet :

```
push sur main
     ↓
GitHub Actions build l'image → ghcr.io/adrien-stage/villa_b
     ↓
la console tire l'image, l'épingle sur un digest, génère un docker-compose
     ↓
docker compose up → l'entrypoint fait le reste
     ↓
l'établissement est opérationnel
```

L'entrypoint attend PostgreSQL, migre, initialise l'établissement au premier
démarrage et synchronise les rôles à chaque démarrage — voir
[Architecture](architecture.md#lentrypoint).

Le premier compte manager est créé **depuis la console**, qui écrit directement dans
cette même base. L'application ne crée aucun utilisateur en production.

> Pour la marche à suivre côté console — création d'un établissement, choix des
> modules, ports, mise à jour de version — se référer à la documentation du dépôt
> `wetchah_erp`.

---

## Développement local

### Prérequis

| Élément | Version |
|---|---|
| PHP | 8.4 (`^8.2` accepté) |
| Composer | 2 |
| Node | 22 |
| PostgreSQL | 16 |

Extensions PHP requises : `pdo_pgsql`, `zip`, `gd`, `mbstring`, `bcmath`, `gmp`.

> `bcmath` et `gmp` ne sont pas optionnelles : la cryptographie sur courbe elliptique
> des notifications Web Push (VAPID) en dépend. Sans elles, l'envoi des push échoue.

### 1. Dépendances

```bash
composer install && npm install
```

### 2. Environnement

```bash
cp .env.example .env && php artisan key:generate
```

Puis renseigner la base et le contexte de l'établissement :

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=wetchah
DB_USERNAME=postgres
DB_PASSWORD=postgres

APP_NAME="Wetchah App"
TENANT_SLUG=wetchah-app
TENANT_CURRENCY=XAF
TENANT_MODULES=["restaurant","shop","housekeeping","accounting","analytics","discussions"]
```

`TENANT_MODULES` détermine les modules visibles. C'est le levier le plus utile en
développement : le réduire permet de tester l'application telle que la verrait un
établissement sans restaurant.

### 3. Base de données

```bash
php artisan migrate --seed
```

`DatabaseSeeder` installe un jeu de démonstration **neutre** : rôles, tenant,
utilisateurs, types et chambres, équipes de ménage, clients, réservations, boutique.

> Ce seeder est **réservé au développement**. En production, c'est
> `ProductionTenantSeeder` qui tourne, et il ne crée ni données de démo ni
> utilisateurs.

### 4. Comptes de démonstration

Mot de passe `password` pour tous, sauf le premier (`admin`) :

| Compte | Rôle |
|---|---|
| `admin@wetchah-app.test` | `admin` *(mot de passe : `admin`)* |
| `manager@wetchah-app.test` | `manager` |
| `reception@wetchah-app.test` | `reception` |
| `housekeeping.leader@wetchah-app.test` | `housekeeping_leader` |
| `restaurant.chief@wetchah-app.test` | `restaurant_chief` |
| `restaurant.staff@wetchah-app.test` | `restaurant_staff` |
| `restaurant.cook@wetchah-app.test` | `restaurant_cook` |
| `restaurant.cashier@wetchah-app.test` | `cashier` |
| `shop.manager@wetchah-app.test` | `shop_manager` |
| `shop.cashier@wetchah-app.test` | `shop_cashier` |

Se connecter avec plusieurs profils est le meilleur moyen de comprendre le
cloisonnement des rôles — chacun voit une application sensiblement différente.

### 5. Lancer

```bash
composer dev
```

Démarre en parallèle le serveur PHP, l'écoute de file d'attente et Vite.

L'application répond sur <http://localhost:8000>.

---

## Vérifier l'installation

### Le verrou de caisse

Se connecter en `reception` et tenter une action sur une réservation : elle est
refusée tant que la caisse n'est pas ouverte. C'est le comportement attendu, pas un
bug — voir [Comptabilité](comptabilite.md).

### Les modules

Retirer `restaurant` de `TENANT_MODULES`, vider la configuration
(`php artisan config:clear`), puis ouvrir `/restaurant/menus` : la réponse doit être
un 403, pas seulement un lien disparu du menu.

### Les rôles

```bash
php artisan roles:sync
```

Doit être idempotent : relancé deux fois, il ne crée aucun doublon.

---

## Problèmes courants

| Symptôme | Cause |
|---|---|
| Erreur `bcmath`/`gmp` au démarrage | Extensions PHP manquantes — requises par le Web Push |
| Un module reste invisible | `TENANT_MODULES` mal formé (JSON) ou config non vidée |
| Les push ne partent pas | Clés VAPID absentes. L'action métier réussit quand même, par conception |
| Un nouveau rôle n'apparaît pas | Lancer `php artisan roles:sync` |
| Un nouveau seeder n'a aucun effet | Normal : les seeders d'installation ne sont jamais rejoués. Passer par une migration de données |
| Une route en `.png` / `.js` renvoie 404 en conteneur | nginx l'intercepte avant Laravel — voir [APIs](apis-et-integrations.md#le-piège-de-lextension) |

---

## Pour aller plus loin

- [Configuration](configuration.md) — toutes les variables
- [Développement](developpement.md) — cycle de livraison et tests
- [Architecture](architecture.md) — l'entrypoint
