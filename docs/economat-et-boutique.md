# Économat et boutique

Deux modules distincts, réunis ici parce qu'ils partagent la même mécanique : un
stock valorisé et des mouvements tracés.

---

# Économat

Le **magasin central** de l'établissement. Ce n'est pas un stock de vente : c'est le
dépôt qui approvisionne tous les départements.

> **À ne pas confondre avec le garde-manger du restaurant.** Ce sont deux systèmes de
> stock **séparés**, avec leurs propres tables, services et modèles. L'économat est le
> magasin central géré par l'`econome` ; le garde-manger
> ([Restaurant](restaurant.md#le-garde-manger)) est le stock de la cuisine, géré par
> le chef. Un article qui passe de l'un à l'autre le fait par une demande interne.

## Le modèle

```
StockCategory ──1──n── StockItem ──1──n── StockMovement
                            │
Supplier ──1──n── PurchaseOrder ──1──n── PurchaseOrderLine
StockRequisition ──1──n── StockRequisitionLine
```

## Le moteur des mouvements

[`StockService`](../app/Services/StockService.php) est le point de passage obligé.

> **Toute variation passe par ce service** : c'est lui qui journalise le mouvement,
> met à jour le stock courant et recalcule le coût moyen pondéré. Le concentrer ici
> évite que chaque contrôleur réinvente — et fasse diverger — cette logique.

### Coût moyen pondéré

Chaque entrée recalcule la moyenne :

```
nouveau coût moyen = (valeur existante + valeur reçue) / quantité totale
```

C'est la moyenne pondérée classique, qui lisse les variations successives de prix
d'achat. `last_purchase_price` conserve par ailleurs le dernier prix payé.

### Concurrence

Chaque mouvement s'exécute dans une transaction avec `lockForUpdate()` sur l'article :
deux réceptions simultanées du même article ne partent jamais du même stock.

### Montants

Montants en **centimes FCFA** (entiers), quantités en **décimal**.

## Les trois flux

### 1. Achat — le bon de commande

```
draft → sent → partially_received → received
   └──────────────── cancelled
```

| Action | Route |
|---|---|
| Créer | `POST /economat/bons` |
| Envoyer au fournisseur | `POST /economat/bons/{order}/envoyer` |
| Réceptionner | `POST /economat/bons/{order}/reception` |
| Annuler | `POST /economat/bons/{order}/annuler` |

[`PurchaseOrderService`](../app/Services/PurchaseOrderService.php) gère le cycle et
**délègue l'entrée en stock à `StockService`** — jamais d'écriture directe.

L'envoi produit un e-mail au fournisseur
([`PurchaseOrderMail`](../app/Mail/PurchaseOrderMail.php)). La réception peut être
partielle, d'où le statut intermédiaire.

### 2. Distribution — la demande interne

```
pending → approved → delivered
    └──── rejected
    └──── cancelled
```

Un responsable de département demande des articles au magasin.

| Étape | Qui | Route |
|---|---|---|
| Créer | tout responsable de département | `POST /economat/demandes` |
| Valider | économe | `POST /economat/demandes/{r}/valider` |
| Refuser | économe | `POST /economat/demandes/{r}/refuser` |
| Livrer | économe | `POST /economat/demandes/{r}/livrer` |
| Annuler | le demandeur | `POST /economat/demandes/{r}/annuler` |

[`StockRequisitionService`](../app/Services/StockRequisitionService.php) sépare
volontairement validation et livraison :

> L'économe peut approuver le **principe** de la demande, puis servir plus tard — et
> ajuster à la livraison les quantités réellement disponibles. C'est la livraison,
> pas la validation, qui déstocke.

Les demandes sont ouvertes à `reception`, `housekeeping_leader`, `restaurant_chief`
et `shop_manager` en plus de l'`econome`. Le contrôleur cloisonne chacun à ses
propres demandes ; la **gestion** du magasin reste réservée à l'économe.

Chaque étape déclenche une notification
([`StockRequisitionSubmitted`](../app/Notifications/StockRequisitionSubmitted.php),
[`StockRequisitionUpdated`](../app/Notifications/StockRequisitionUpdated.php)).

### 3. Correction — l'ajustement

`POST /economat/articles/{item}/ajustement` — correction manuelle après inventaire ou
constat de perte, tracée comme tout autre mouvement.

## Fournisseurs

`Supplier` — coordonnées, articles fournis, historique des bons de commande.

## Seuils d'alerte

Un article sous son seuil déclenche
[`StockItemBelowThreshold`](../app/Notifications/StockItemBelowThreshold.php) vers
l'économe.

## Import / export

Les articles s'importent et s'exportent en CSV (`/economat/articles-export`,
`/economat/articles-import`).

---

# Boutique

Module optionnel (`shop`). Point de vente d'articles — boutique de souvenirs,
produits locaux, articles culturels.

## Le modèle

```
ShopCategory ──1──n── ShopProduct
ShopOrder ──1──n── ShopOrderItem
```

Chaque commande est rattachée à une **session de caisse**
(`cash_register_session_id`) : aucune vente n'existe hors d'une caisse ouverte.

## Le catalogue

Réservé au `shop_manager` : articles, prix, stock, photos, catégories.

Import et export CSV disponibles (`/shop/products-export`, `/shop/products-import`).

Un article sous son seuil déclenche
[`ShopProductLowStock`](../app/Notifications/ShopProductLowStock.php).

## Les ventes

| Action | Qui | Route |
|---|---|---|
| Créer | `shop_manager`, `shop_cashier` | `POST /shop/orders` |
| Marquer payée | idem | `PATCH /shop/orders/{order}/paid` |
| Rembourser | idem | `PATCH /shop/orders/{order}/refund` |
| Reçu | + `manager` en lecture | `GET /shop/orders/{order}/receipt` |

Comme au restaurant, une vente peut être portée au **folio d'un séjour**
(`folio_item_id`) plutôt qu'encaissée immédiatement.

## La caisse boutique

La boutique a **sa propre caisse**, distincte de celle de la réception : même
mécanique, module `shop`. Voir [Comptabilité](comptabilite.md).

Ouverture accessible au `shop_manager` et au `shop_cashier` ; la fermeture est bornée
par le contrôleur à l'utilisateur qui a ouvert la session.

## Répartition des droits

| Rôle | Peut |
|---|---|
| `shop_manager` | Tout : catalogue, ventes, caisse |
| `shop_cashier` | Ventes et caisse, pas le catalogue |
| `manager` | **Lecture seule** — consulte, ne saisit rien |

## Pour aller plus loin

- [Restaurant](restaurant.md) — le garde-manger, l'autre système de stock
- [Comptabilité](comptabilite.md) — caisses et recettes
- [Rôles et accès](roles-et-acces.md) — droits détaillés
