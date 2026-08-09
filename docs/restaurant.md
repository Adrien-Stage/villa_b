# Restaurant

Module optionnel (`restaurant`), et le plus riche de l'application : carte, service
en salle, écran cuisine, garde-manger valorisé, fiches techniques, inventaires et
facturation.

## Vue d'ensemble

```
RestaurantMenuCategory ──1──n── RestaurantMenuItem
                                     │
                                     └──1──1── RestaurantRecipe ──1──n── RestaurantRecipeLine
                                                                                │
RestaurantPantryCategory ──1──n── RestaurantPantryItem ─────────────────────────┘
                                     └──1──n── RestaurantPantryMovement

RestaurantCustomerOrder ──1──n── RestaurantCustomerOrderItem
RestaurantShift          RestaurantStockCount ──1──n── RestaurantStockCountLine
```

Le lien central est la **fiche technique** : elle relie un plat de la carte aux
ingrédients du garde-manger. C'est elle qui rend le stock et le coût automatiques.

## La carte

`Paramètres` de la carte réservés au **chef cuisinier** : catégories, plats, prix,
photos, et les **services** auxquels chaque plat est disponible (petit-déjeuner,
déjeuner, dîner).

Import et export CSV disponibles (`/restaurant/menus-export`, `/restaurant/menus-import`).

## Le service en salle

### Prise de service

Un serveur ouvre son service (`RestaurantShift`) avant de prendre des commandes.
C'est cette prise de service qui le rend éligible aux affectations automatiques.

| Action | Route |
|---|---|
| Ouvrir | `POST /restaurant/shifts/open` |
| Fermer | `POST /restaurant/shifts/close` |

### Le cycle d'une commande

```
pending → confirmed → preparing → ready → served
                                    └── canceled (à tout moment)
```

| Étape | Qui | Route |
|---|---|---|
| Créer | serveur, chef | `POST /restaurant/orders` |
| Transmettre en cuisine | serveur, chef | `POST /restaurant/orders/{order}/send-to-kitchen` |
| Prendre en préparation | cuisinier, chef | `POST /restaurant/orders/{order}/preparing` |
| Signaler prêt | cuisinier, chef | `POST /restaurant/orders/{order}/ready` |
| Servir | serveur, chef | `POST /restaurant/orders/{order}/served` |

> **La cuisine et la salle ne communiquent pas directement.** Chaque commande passe
> par un serveur : c'est lui qui transmet en cuisine, et lui qui apporte le plat.
> Cette contrainte est ce qui rend la responsabilité traçable de bout en bout.

Un serveur peut aussi **réclamer** une commande non attribuée (`claim`) ou la
**réassigner** à un collègue (`reassign`).

### L'écran cuisine

`/restaurant/kitchen` — la vue des cuisiniers : les bons transmis, dans l'ordre,
avec les deux seules actions dont ils ont besoin (en préparation, prêt).

## Le portail client (QR)

`/portal/{slug}/restaurant` — **accessible sans authentification**, destiné à être
ouvert par le client depuis un QR code posé sur la table.

Le client consulte la carte, compose sa commande et la valide. Le numéro de table est
transmis en paramètre d'URL.

### Répartition automatique

[`RestaurantAssignmentService`](../app/Services/RestaurantAssignmentService.php)
confie chaque commande du portail à un serveur en service.

> **Règle : au moins chargé.** La commande va au serveur en service qui a le moins de
> commandes actives ; à égalité, à celui dont la dernière affectation est la plus
> ancienne. Sur la durée, la charge s'égalise d'elle-même — un serveur dont les
> tables traînent n'est pas noyé sous de nouvelles commandes.

Le serveur reçoit une notification
([`RestaurantOrderAssigned`](../app/Notifications/RestaurantOrderAssigned.php)).

## Le garde-manger

[`RestaurantStockService`](../app/Services/RestaurantStockService.php) — 494 lignes,
le moteur du module.

> **Toute écriture de stock passe par ici.** C'est la seule façon de garantir que le
> stock, le coût moyen pondéré et la piste d'audit des mouvements restent cohérents
> entre eux.

Deux principes structurent le module.

### 1. Le stock est théorique

Il se déduit des fiches techniques : vendre 5 ndolé sort 2,5 kg d'arachide.

L'**inventaire physique** confronte ce théorique au réel, et l'écart mesure le
gaspillage, le sur-portionnage et le vol. C'est la raison d'être du module : sans
inventaire, le stock théorique ne dit rien.

### 2. Le coût suit le stock

Chaque entrée recalcule le **coût moyen pondéré** de l'ingrédient. Le coût matière
des plats et la marge se mettent donc à jour tout seuls quand le prix de l'arachide
monte — sans ressaisie.

Les sorties sont valorisées au coût moyen du moment.

### Le stock peut devenir négatif

C'est un choix explicite, pas un défaut :

> La cuisine sait parfois mieux que le système. Bloquer une vente sur une donnée de
> stock imparfaite coûte plus cher que de tolérer un stock négatif signalé.

### Concurrence

Chaque mouvement s'exécute dans une transaction avec `lockForUpdate()` sur
l'ingrédient : deux ventes simultanées ne lisent jamais le même stock.

### Réception de marchandise

`POST /restaurant/pantry/items/{item}/receive` — saisie en **unités d'achat**,
valorisée. C'est l'entrée qui recalcule le coût moyen pondéré.

Un seuil bas déclenche
[`PantryItemLowStock`](../app/Notifications/PantryItemLowStock.php).

## Fiches techniques

`RestaurantRecipe` et ses lignes relient un plat aux ingrédients et à leurs quantités.
Réservées au **chef cuisinier**.

Deux usages :

- **Déstockage automatique** à la vente du plat ;
- **Production** (`POST /restaurant/recipes/{recipe}/produce`) — fabriquer un
  sous-produit ou une préparation à l'avance, ce qui déstocke les ingrédients et
  entre le produit fini.

Import et export CSV disponibles.

## Inventaires physiques

`RestaurantStockCount` et ses lignes.

| Action | Route |
|---|---|
| Ouvrir | `POST /restaurant/stock-counts` |
| Saisir | `PUT /restaurant/stock-counts/{stockCount}` |
| Clôturer | `POST /restaurant/stock-counts/{stockCount}/close` |

La clôture confronte le comptage réel au stock théorique et génère les mouvements
d'ajustement. C'est là que l'écart devient une donnée exploitable.

## Facturation

`/restaurant/billing` — consultation ouverte au manager, actions réservées au **chef
et au caissier**.

| Action | Route |
|---|---|
| Marquer payée | `POST /restaurant/billing/{order}/paid` |
| Annuler le paiement | `POST /restaurant/billing/{order}/unpaid` |
| Reçu | `GET /restaurant/billing/{order}/receipt` |

### Le règlement à la chambre

Une commande peut être réglée `room_charge` : elle n'est alors **pas** encaissée au
restaurant. Elle devient une ligne de folio du séjour et sera réglée au départ.

> C'est ce qui évite le double comptage en comptabilité : la recette est comptée une
> seule fois, au moment où l'argent entre réellement. Voir
> [Comptabilité](comptabilite.md).

## Répartition des droits

| Rôle | Peut |
|---|---|
| `restaurant_chief` | Tout : carte, garde-manger, fiches techniques, inventaires, commandes, facturation |
| `restaurant_staff` | Service : prise de service, commandes, transmission cuisine, service |
| `restaurant_cook` | Cuisine : prise en préparation, plat prêt |
| `cashier` | Facturation |
| `manager` | **Lecture seule** — consulte tout, ne saisit rien |

L'exclusion du manager en écriture est délibérée : il supervise, il ne prend pas les
commandes à la place de ses équipes. Voir
[Rôles et accès](roles-et-acces.md#cas-notables).

## Pour aller plus loin

- [Économat et boutique](economat-et-boutique.md) — l'autre système de stock
- [Hébergement](hebergement.md) — le folio
- [APIs et intégrations](apis-et-integrations.md) — la carte exposée au site vitrine
