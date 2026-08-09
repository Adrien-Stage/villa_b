# Configuration

La configuration se joue à **deux niveaux**, et la distinction est essentielle :

| Niveau | Support | Qui décide | Modifiable en service |
|---|---|---|---|
| **Environnement** | Variables `.env` / conteneur | La console d'administration | Non — impose de recréer le conteneur |
| **Paramètres** | `Tenant::settings` (JSON) | Le personnel de l'établissement | Oui, depuis l'interface |

---

## Niveau 1 — Variables d'environnement

En production, ces variables sont **injectées par le `docker-compose` généré** par la
console. Personne ne les édite à la main sur le serveur.

### Identité de l'établissement

| Variable | Rôle |
|---|---|
| `TENANT_SLUG` | Identifiant de l'établissement — utilisé par le portail QR et le CMS |
| `TENANT_CURRENCY` | Devise, `XAF` par défaut |
| `TENANT_SETTINGS` | JSON — pays, ville, thème de couleurs |
| `TENANT_MODULES` | JSON — modules actifs, ex. `["restaurant","shop","housekeeping"]` |
| `APP_NAME` | Nom affiché, y compris dans la PWA installée |

`TENANT_SETTINGS` et `TENANT_MODULES` sont consommés au **premier démarrage** par
`ProductionTenantSeeder`, qui crée la ligne `tenants`.

> **Conséquence importante** : modifier `TENANT_SETTINGS` sur un établissement déjà
> initialisé **n'a aucun effet** — le seeder ne se rejoue jamais. Les paramètres
> évoluent ensuite par l'interface (niveau 2). `TENANT_MODULES` fait exception : il
> est relu à chaque requête par
> [`TenantModules`](../app/Support/TenantModules.php).

### Base de données

| Variable | Valeur en production |
|---|---|
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | Nom du conteneur de base — jamais l'hôte |
| `DB_PORT` | `5432` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Propres à l'établissement |

La base n'est jamais publiée sur l'hôte : elle n'est joignable que par le réseau
Docker interne.

### Secrets partagés

| Variable | Rôle | Si vide |
|---|---|---|
| `ASSISTANCE_SECRET` | Vérification des jetons du mode assistance | Le mode assistance est refusé |
| `REPORTING_SECRET` | Protection de l'API de reporting | **L'API de reporting est désactivée** |

Ces deux secrets sont partagés avec la console. Les changer impose de recréer le
conteneur — ils sont inscrits dans le `docker-compose`.

Un secret vide est un **refus par défaut**, jamais une ouverture.

### Notifications Web Push

| Variable | Rôle |
|---|---|
| `VAPID_SUBJECT` | `mailto:` de l'éditeur |
| `VAPID_PUBLIC_KEY` | Clé publique, exposée au client pour l'abonnement |
| `VAPID_PRIVATE_KEY` | Clé privée |

> Ces clés identifient **l'éditeur de l'application, pas l'établissement**. Elles sont
> communes à toute la plateforme, générées une fois par
> `php artisan webpush:vapid` puis figées.

Sans elles, l'envoi des push échoue — mais jamais l'action métier
(voir [`Notifier`](../app/Services/Notifier.php)).

### Assistant IA

| Variable | Rôle |
|---|---|
| `MISTRAL_API_KEY` | Clé API Mistral |
| `MISTRAL_MODEL` | Modèle, ex. `mistral-small-latest` |

Sans clé, `/ai-chat` répond une erreur explicite.

### Messagerie

`MAIL_MAILER=resend` avec `RESEND_API_KEY`. Utilisée pour les codes d'arrivée client
et l'envoi des bons de commande aux fournisseurs.

### Sessions, cache, files d'attente

En production, les trois pointent sur la base :

```dotenv
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

C'est ce que la console injecte. L'application n'a besoin ni de Redis ni d'un worker
séparé — les variables `REDIS_*` du `.env.example` ne sont pas utilisées.

---

## Niveau 2 — Paramètres d'établissement

`Tenant::settings`, colonne JSON, éditée depuis **`/settings`**.

L'accès est ouvert au manager et aux responsables de département (`reception`,
`housekeeping_leader`, `restaurant_chief`, `shop_manager`) — chacun voit l'onglet qui
le concerne.

### Les onglets

| Onglet | Contenu | Qui |
|---|---|---|
| **Général** | Identité, logo, coordonnées | manager |
| **Hébergement** | Délais de remise en état par type, packs | manager, réception |
| **Réception** | Heures d'arrivée et de départ, politique d'annulation | manager, réception |
| **Housekeeping** | Organisation du service | manager, chef d'équipe |
| **Restaurant** | Réglages du service | manager, chef |
| **Boutique** | Réglages boutique | manager, gérant |
| **Taxes** | TVA et taxes de séjour | manager |
| **Prestations** | Catalogue des extras facturables | manager |
| **Partenaires** | Conventions et remises négociées | manager |

Les onglets **Prestations** et **Partenaires** sont réservés au manager : ces
catalogues engagent des prix et des remises.

### Réglages structurants

Deux d'entre eux gouvernent la disponibilité, et méritent d'être connus :

| Réglage | Onglet | Effet |
|---|---|---|
| `cleaning_delay_by_type` | Hébergement | Délai de remise en état, par type de chambre |
| `check_in_time` / `check_out_time` | Réception | Bornes horaires de la rotation |

Ensemble, ils déterminent si une rotation le jour même est acceptée. Voir
[Hébergement — disponibilité](hebergement.md#disponibilité).

### Import / export

La plupart des onglets exposent un import et un export CSV
(`/settings/export/{tab}`, `/settings/import/{tab}`), ainsi que les catalogues de
prestations, partenaires et packs.

---

## Développement local

En local, tout passe par le `.env` :

```dotenv
APP_NAME="Villa Boutanga"
APP_URL=http://localhost:8000
APP_LOCALE=fr

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=wetchah
DB_USERNAME=postgres
DB_PASSWORD=postgres

TENANT_SLUG=villa-boutanga
TENANT_CURRENCY=XAF
TENANT_MODULES=["restaurant","shop","housekeeping","accounting","analytics","discussions"]
```

`TENANT_MODULES` est le levier le plus utile en développement : il permet de tester
l'application avec et sans un module, sans toucher à la base.

Après modification :

```bash
php artisan config:clear
```

---

## Pour aller plus loin

- [Installation](installation.md) — mise en place
- [Architecture](architecture.md) — l'entrypoint et l'initialisation
- [Rôles et accès](roles-et-acces.md) — modules et droits
