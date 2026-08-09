# WeTchah App — Application de gestion d'établissement

Le logiciel métier livré à chaque établissement hôtelier de la plateforme WeTchah :
hébergement, restaurant, boutique, économat, ménage et comptabilité de caisse, dans
une seule application.

**Une instance = un établissement.** Chaque client reçoit son propre conteneur, sa
propre base de données, ses propres utilisateurs. Il n'y a pas de mutualisation :
l'isolation est physique, pas logique.

> Ce dépôt est **le produit**. Il n'est pas déployé à la main : il est publié en
> image Docker sur GHCR, puis instancié par la console d'administration.

---

## Les trois dépôts de la plateforme

| Dépôt | Rôle | Stack |
|---|---|---|
| [`wetchah_erp`](https://github.com/Adrien-Stage/erp_pms) | La console qui fabrique et supervise les établissements | Laravel 12, SQLite |
| **`wetchah_app`** *(ce dépôt)* | Le PMS livré à chaque établissement | Laravel 12 + Blade, PostgreSQL |
| [`wetchah_site`](https://github.com/clyde237/site_villab) | Le site vitrine public, optionnel | SvelteKit 2 |

Le cycle de livraison ne passe jamais par un déploiement manuel :

```
push sur main → la CI publie ghcr.io/adrien-stage/villa_b → l'ERP met à jour l'établissement
```

---

## Ce que fait l'application

| Domaine | Contenu |
|---|---|
| **Hébergement** | Chambres, types, tarifs, packs, réservations individuelles et de groupe, folio, check-in/check-out, factures, fiches techniques de coût |
| **Housekeeping** | Équipes, affectation des chambres, cycle de nettoyage et d'inspection, signalement d'incidents |
| **Restaurant** | Carte, commandes en salle, écran cuisine, garde-manger, fiches techniques, inventaires, services, facturation, portail QR client |
| **Économat** | Magasin central : articles, fournisseurs, bons de commande, demandes des départements |
| **Boutique** | Catalogue, ventes, caisse dédiée |
| **Comptabilité** | Comptabilité de caisse, dépenses, journal, compte de résultat, créances |
| **Transverse** | Clients, utilisateurs et rôles, discussions internes, analytics, notifications, PWA, assistant IA |

Les modules ne sont pas tous actifs partout : chaque établissement reçoit la liste
de ses modules depuis la console d'administration.

---

## Démarrage rapide (développement local)

Prérequis : PHP 8.4, Composer, Node 22, PostgreSQL.

```bash
composer install && npm install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate --seed
```

```bash
composer dev
```

Le seeder de développement crée le jeu de données de démonstration
« Villa Boutanga » — chambres, clients, réservations, utilisateurs de test.

Installation complète : **[docs/installation.md](docs/installation.md)**.

---

## Documentation

| Document | Contenu |
|---|---|
| **[Architecture](docs/architecture.md)** | Modèle mono-établissement, modules, couche services, données, conventions |
| **[Installation](docs/installation.md)** | Développement local, et comment l'application est réellement déployée |
| **[Configuration](docs/configuration.md)** | Variables d'environnement, `TENANT_*`, paramètres d'établissement |
| **[Rôles et accès](docs/roles-et-acces.md)** | Catalogue des rôles, modules, niveaux lecture/écriture, verrou de caisse |
| **[Hébergement](docs/hebergement.md)** | Chambres, disponibilité, réservations, folio, check-out, factures, housekeeping |
| **[Restaurant](docs/restaurant.md)** | Carte, commandes, cuisine, garde-manger, fiches techniques, inventaires, portail |
| **[Économat et boutique](docs/economat-et-boutique.md)** | Magasin central, achats, demandes internes, ventes boutique |
| **[Comptabilité](docs/comptabilite.md)** | Caisses, comptabilité de caisse, dépenses, états financiers |
| **[APIs et intégrations](docs/apis-et-integrations.md)** | API publique, API de reporting, mode assistance, PWA, push, IA |
| **[Développement](docs/developpement.md)** | Cycle de livraison, tests, conventions, dette connue |

---

## Structure du code

```
app/
├─ Console/Commands/SyncRoles.php    Commande « roles:sync »
├─ Enums/                            BookingStatus, RoomStatus
├─ Http/
│  ├─ Controllers/                   ~45 contrôleurs, sous-dossiers Api, Economat,
│  │                                 Reception, Shop, Auth, Concerns
│  └─ Middleware/                    RBAC, modules, niveaux d'accès, verrou de caisse
├─ Models/                           57 modèles
├─ Notifications/                    17 notifications métier (base + Web Push)
├─ Services/                         13 services — le cœur métier
└─ Support/
   ├─ RoleCatalog.php                Référentiel des rôles, source unique
   ├─ TenantModules.php              Modules actifs pour cet établissement
   ├─ Countries.php                  Référentiel pays
   └─ CsvSanitizer.php               Protection contre l'injection de formules
```

Points d'entrée : [`routes/web.php`](routes/web.php) (531 lignes) et
[`routes/api.php`](routes/api.php).

---

## Licence

Projet propriétaire. Tous droits réservés.
