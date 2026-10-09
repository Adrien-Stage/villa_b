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
| Marquer comme transmis (sans email) | `POST /economat/bons/{order}/transmis` |
| Réceptionner | `POST /economat/bons/{order}/reception` |
| Annuler | `POST /economat/bons/{order}/annuler` |

[`PurchaseOrderService`](../app/Services/PurchaseOrderService.php) gère le cycle et
**délègue l'entrée en stock à `StockService`** — jamais d'écriture directe.

L'envoi produit un e-mail au fournisseur
([`PurchaseOrderMail`](../app/Mail/PurchaseOrderMail.php)). La réception peut être
partielle, d'où le statut intermédiaire.

Un fournisseur sans email (vendeur du marché, fournisseur joint par
téléphone) reçoit son bon autrement : remis en main propre, dicté au
téléphone, envoyé par WhatsApp. « Marquer comme transmis » fait passer le bon à
`sent` comme un email, et la colonne `transmission` garde le moyen. On ne
réceptionne qu'un bon transmis : sans cette action, un fournisseur sans
adresse bloquait toute la chaîne.

#### Réception directe, sans bon de commande

Une marchandise arrive parfois sans commande : achat au comptant au marché,
livraison imprévue, urgence. La réception directe (`GET|POST
/economat/receptions/directe`, droit `economat.receipts.direct.creer`, réservé
à l'économe) l'enregistre en une fois.

[`GoodsReceiptService::receiveDirect`](../app/Services/GoodsReceiptService.php)
établit un **bon de régularisation** (`transmission = regularisation`) qui
porte ce qui est gardé, puis le réceptionne par le circuit ordinaire. Le stock,
le coût moyen, le rapprochement de la facture fournisseur et l'annulation
fonctionnent donc comme pour toute réception. Annuler la réception annule
aussi le bon de régularisation, qui n'attend aucune livraison.

Le fournisseur et les articles absents du magasin se créent depuis le
formulaire, sous leurs droits habituels (`economat.suppliers.creer`,
`economat.items.creer`). Une application encore vide peut ainsi enregistrer ce
qui vient d'arriver. Le prix unitaire est obligatoire : il valorise le stock.

Pour la marchandise déjà en magasin au démarrage, ce n'est pas une réception :
c'est la reprise du stock initial (Articles › Reprise du stock, ou la colonne
`stock_initial` de l'import CSV).

### 2. Distribution — la demande interne

```
awaiting_endorsement → pending → approved → delivered
          └──── rejected (refus au visa)
                     └──── rejected
         (cancelled par le demandeur tant que rien n'est livré)
```

Un membre d'un service demande des articles au magasin ; son chef vise la demande
avant qu'elle arrive chez l'économe.

| Étape | Qui | Route |
|---|---|---|
| Créer | tout membre d'un service, pour son propre service | `POST /economat/demandes` |
| Viser ou refuser (motif obligatoire) | chef du service, ou la direction | `POST /economat/demandes/{r}/viser` |
| Valider | économe | `POST /economat/demandes/{r}/valider` |
| Refuser | économe | `POST /economat/demandes/{r}/refuser` |
| Livrer | économe | `POST /economat/demandes/{r}/livrer` |
| Annuler | le demandeur | `POST /economat/demandes/{r}/annuler` |

[`StockRequisitionService`](../app/Services/StockRequisitionService.php) sépare
volontairement validation et livraison :

> L'économe peut approuver le **principe** de la demande, puis servir plus tard — et
> ajuster à la livraison les quantités réellement disponibles. C'est la livraison,
> pas la validation, qui déstocke.

#### Le visa du chef de service

Chaque service a son chef (`StockRequisition::CHEFS`) : chef de réception pour
l'hébergement, gouvernante pour les étages, chef cuisinier ou responsable restaurant
pour la restauration (dans les restaurants où il travaille), responsable boutique,
RAF pour la comptabilité, direction pour « autre ». La direction vise aussi pour tout
service, quand le chef est absent ou que le service n'en a pas.

- La demande d'un membre (réceptionniste, valet, cuisinier, serveur, vendeur,
  comptable) naît `awaiting_endorsement` ; ses chefs sont prévenus, pas l'économe.
  Sans chef dans le service, la direction est prévenue.
- Visée, elle passe `pending` et l'économe est prévenu. Refusée au visa, elle passe
  `rejected` avec le motif du chef ; l'économat ne la voit jamais arriver.
- La demande faite par le chef lui-même, l'économe ou la direction porte déjà le
  visa : elle naît `pending`.
- Le demandeur ne vise jamais sa propre demande ; l'économe ne valide pas une demande
  non visée.
- On ne demande que pour son propre service.

Un membre voit ses demandes ; un chef aussi celles de son service
(`StockRequisition::visiblesPour`) ; l'économat, la direction et le contrôle voient
tout. La **gestion** du magasin reste réservée à l'économe.

Chaque étape déclenche une notification
([`StockRequisitionSubmitted`](../app/Notifications/StockRequisitionSubmitted.php),
[`StockRequisitionUpdated`](../app/Notifications/StockRequisitionUpdated.php)).

### 3. Correction — l'ajustement

`POST /economat/articles/{item}/ajustement` — correction manuelle après inventaire ou
constat de perte, tracée comme tout autre mouvement.

Chaque mouvement garde le **stock avant** (`stock_movements.stock_before`) et le stock
après : un ajustement porte ainsi le stock initial de l'article et le stock compté, et
sa valeur (quantité × coût du mouvement).

### 4. Inventaire et fichier de comptage

L'inventaire de l'économat (`StockCount`) fige le théorique et gèle le magasin ; sa
clôture ajuste chaque article compté. Le comptage se saisit à la main ou par fichier :

| Action | Qui | Route |
|---|---|---|
| Télécharger le fichier de comptage (Excel) | économe, magasinier, direction, contrôle | `GET /economat/inventaires/fichier-comptage?inventaire={id}` (ou `?categorie={id}` hors inventaire) |
| Importer le fichier rempli | économe, magasinier | `POST /economat/inventaires/{id}/import` |
| Ouvrir un inventaire avec le fichier déjà rempli | économe | `POST /economat/inventaires` (champ `fichier`) |

Colonnes : `id`, `référence`, `article`, `catégorie`, `unité`, `stock théorique`,
`stock compté`, `motif`, `note` ([`StockCountImportService`](../app/Services/StockCountImportService.php)).
Une ligne se rattache à l'article par son identifiant, sinon sa référence, sinon son nom ;
une ligne sans quantité comptée ne change rien. Le motif se donne par son libellé ou son
code ; un motif inconnu devient « autre » et passe en note. Une ligne refusée (article
hors inventaire, quantité illisible, doublon) est listée à l'écran.

Avec « clôturer aussitôt » (droit de clôture), l'import clôture l'inventaire et ajuste le
stock, à condition qu'aucune ligne n'ait été refusée : sinon l'inventaire reste ouvert
pour correction.

### 5. Mouvements de stock

`GET /economat/mouvements` — tous les mouvements du magasin, filtrés par période (le mois
en cours par défaut), article, catégorie, nature, origine et motif, avec le stock avant,
l'entrée ou la sortie, le stock après, le coût, la valeur et le document d'origine. Avec un
article, la page devient sa **fiche de stock** : stock au début, entrées, sorties,
ajustements, stock en fin, dans l'ordre du temps. Export en impression, PDF, Excel et Word
(`/economat/mouvements/export`). Écran, export et édition « Mouvements de stock » lisent
le même journal ([`StockMovementJournal`](../app/Services/StockMovementJournal.php)).

## Fournisseurs

`Supplier` — coordonnées, articles fournis, historique des bons de commande.

## Unités de stockage

[`StockUnit`](../app/Models/StockUnit.php) — la liste des unités dans lesquelles
l'économat compte ses articles (kg, litre, pièce, casier…). L'économe la tient
dans **Paramètres › Économat** (droits `economat.units.*`) ; la direction la
consulte.

La fiche d'un article, la réception directe, la fiche fournisseur et l'import CSV
choisissent l'unité dans cette liste au lieu de la taper. L'import ramène « KG » à
« kg » et refuse une unité absente de la liste.

L'article garde le nom de son unité en clair (`stock_items.unit`) : bons, fiches de
comptage et éditions la lisent telle quelle. Renommer une unité renomme donc celle
des articles qui l'emploient. Une unité employée ne se supprime pas : elle se met
hors service, ne s'offre plus aux nouveaux articles, et reste valable pour ceux qui
la portent déjà.

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
