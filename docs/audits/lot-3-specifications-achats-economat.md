# Spécifications Exhaustives — Lot 3 : Achats, Économat & Ségrégation des Tâches (SoD)

Ce document constitue la référence absolue pour l'implémentation du **Lot 3**. Il applique les exigences de l'audit métier et sécurité du 5 novembre 2026 ([`2026-11-05-audit-metier-securite.md`](./2026-11-05-audit-metier-securite.md)), en particulier le **Constat S7** (ségrégation stricte des tâches sur les achats, le stock et les tiers) et le **Circuit Achats – Contrôle – RAF**.

---

## 1. Objectifs & Enjeux Métier

Dans un établissement hôtelier de référence, la chaîne approvisionnement / économat est le principal vecteur de risque financier opérationnel. Le Lot 3 verrouille techniquement le circuit pour éliminer quatre risques majeurs :
1. **La création de commandes fictives** couverte par une réception complaisante (collusion commande / réception).
2. **La falsification des prix ou des fournisseurs référencés** (altération rétroactive du catalogue ou des coordonnées bancaires).
3. **L'ajustement de complaisance du stock physique** pour masquer des disparitions ou des couages.
4. **La réception passive au magasin** : si le magasinier connaît à l'avance les prix et les montants ou recopie aveuglément le bon de commande au lieu de procéder à un contrôle physique contradictoire et quantitatif.

---

## 2. Matrice des Rôles et Exclusivités Métier

### 2.1. Responsable des Achats (`purchasing_manager`)
* **Missions autorisées :**
  * Création des nouveaux articles au catalogue (`economat.items.creer`).
  * Création des nouveaux fournisseurs (`economat.suppliers.creer`).
  * Émission de commandes rattachées à une demande d'achat approuvée (`economat.purchase_requests.convert`).
  * Émission de commandes directes sans expression de besoin préalable (`economat.orders.creer`, `economat.orders.store`).
  * Envoi et transmission des bons de commande aux fournisseurs (`economat.orders.send`, `economat.orders.transmit`, `economat.orders.cancel`).
  * Consultation globale en lecture de l'économat et des alertes de réapprovisionnement (`economat.items.voir`, `economat.movements.voir`, `economat.receipts.voir`, `achats.voir`).
* **Interdictions formelles et non dérogeables (Constat S7) :**
  * **Interdiction absolue de modifier ou supprimer des articles** (`economat.items.modifier`, `economat.items.supprimer` refusés).
  * **Interdiction absolue de modifier ou supprimer des fournisseurs** (`economat.suppliers.modifier`, `economat.suppliers.supprimer` refusés).
  * **Interdiction absolue de modifier les liaisons/tarifs fournisseurs sur articles existants** (`economat.items.suppliers.link`, `economat.items.suppliers.unlink` refusés).
  * **Interdiction absolue de réceptionner physiquement la marchandise** (`economat.orders.receive`, `economat.receipts.creer` refusés).
  * **Interdiction absolue d'ajuster le stock ou les quantités initiales** (`economat.items.adjust`, `economat.items.opening` refusés).

### 2.2. Contrôleur de Gestion (`controller`)
* **Exclusivités fermes et non dérogeables :**
  * **Seul habilité à modifier ou supprimer les articles** du catalogue (`economat.items.modifier`, `economat.items.supprimer`).
  * **Seul habilité à modifier ou supprimer les fiches fournisseurs** (`economat.suppliers.modifier`, `economat.suppliers.supprimer`).
  * **Seul habilité à gérer les tarifs contractuels fournisseurs** (`economat.items.suppliers.link`, `economat.items.suppliers.unlink`).
  * **Seul habilité à ajuster le stock magasin et dépôts** en dehors des réceptions/sorties ordinaires (`economat.items.adjust`).
  * **Seul habilité à valider la reprise du stock initial** (`economat.items.opening`).
  * **Seul habilité à confirmer les imports Excel de classeur** modifiant les articles ou les stocks (`economat.classeur.confirm`).
  * Consultation de tous les écarts, marges et propositions de réapprovisionnement (`economat.control.variances.voir`, `economat.control.voir`).

### 2.3. Économe (`econome`) et Magasiniers (`storekeeper`)
* **Missions opérationnelles autorisées :**
  * Expression des besoins via les demandes d'achat (`economat.purchase_requests.creer`).
  * Réception physique quantitative contradictoire des marchandises livrées (`economat.receipts.creer`, `economat.orders.receive`).
  * Traitement des réceptions directes sans commande préalable (`economat.receipts.direct.creer`).
  * Traitement des réquisitions et sorties vers les services (`economat.requisitions.deliver`, `economat.requisitions.approve`).
  * Tenue des fiches de comptage tous services / dépôts (`economat.count_sheets.*`).
  * Gestion des conditionnements physiques et des découpes (`economat.items.transformation.*`).
* **Confidentialité stricte des Bons de Commande (BC) et des Prix :**
  * **Aucune visibilité des bons de commande détaillés** (`economat.orders.voir` et `economat.orders.export` strictement interdits).
  * **Suppression de tout lien actif vers les BC** sur les écrans accessibles au magasinier et à l'économe (liste des réceptions, détail d'une réception, fiche fournisseur, demande d'achat).
  * **Masquage des prix d'achat unitaires et montants valorisés** dans les formulaires et impressions de demandes d'achat et de réception lorsque consultés par l'économat.

---

## 3. Ségrégation des Tâches Incompatibles (`DutySegregation`)

Les cumuls suivants sont strictement incompatibles et détectés au niveau applicatif :
1. `purchasing_manager` + `econome` : Interdit (commander et réceptionner).
2. `purchasing_manager` + `storekeeper` : Interdit (commander et réceptionner).
3. `purchasing_manager` + `accountant` : Interdit (engager la dépense et la régler).
4. `controller` + `purchasing_manager` : Interdit (contrôler et commander).
5. `controller` + `econome` / `storekeeper` : Interdit (contrôler et détenir le stock).

---

## 4. Impacts Applicatifs & Fichiers Cibles

### 4.1. Référentiel des Droits & Sécurité
* **`app/Support/PermissionCatalog.php`** :
  * Retrait de `economat.suppliers.modifier` et `economat.suppliers.supprimer` pour `purchasing_manager` (exclusivement `controller`).
  * Retrait de `economat.items.modifier` et `economat.items.supprimer` pour tout profil autre que `controller`.
  * `economat.items.suppliers.link` et `economat.items.suppliers.unlink` attribués à `controller` (et retirés de `purchasing_manager`).
  * `economat.classeur.confirm` réservé à `controller`.
  * Confirmation du blocage de `economat.orders.voir` et `economat.orders.export` pour `econome` et `storekeeper`.
  * Inscription de ces droits dans `ACTES_EXCLUSIFS` et `NON_DEROGEABLES`.

### 4.2. Contrôleurs & Routes
* **`StockItemController`** :
  * Les routes `update`, `destroy`, `adjust`, `opening`, `linkSupplier`, `unlinkSupplier` sont gardées par le middleware de permissions et exigent les droits exclusifs du contrôleur.
* **`SupplierController`** :
  * Les routes `update`, `destroy` sont réservées au contrôleur.
  * La méthode `store` reste permise au responsable des achats pour enrichir les nouveaux fournisseurs.
* **`PurchaseOrderController`** :
  * `create` et `store` ouverts au responsable des achats (bons directs sans demande préalable).
  * `index`, `show`, `export` strictement inaccessibles à l'économat et aux magasiniers (403).
* **`GoodsReceiptController`** :
  * Les réceptions physiques restent dans le magasin.
  * Masquage des prix sur les vues si l'utilisateur ne dispose pas du droit de voir les prix.

### 4.3. Vues Blade & Rendu
* **`resources/views/economat/items/`** :
  * Boutons « Modifier », « Supprimer », « Ajuster », « Reprise initiale » conditionnés par `@droit(...)`.
* **`resources/views/economat/suppliers/show.blade.php`** :
  * Section « Bons de commande en cours » masquée si `$voitBons` est faux.
  * Cartes de statistiques adaptées pour ne pas faire fuiter les montants de commandes vers le magasinier.
* **`resources/views/economat/receipts/show.blade.php` & `index.blade.php`** :
  * Remplacement des liens `orders.show` par du texte simple si l'utilisateur n'a pas `economat.orders.voir`.
  * Liens retour dans `receipts/create.blade.php` pointant vers `receipts.index` plutôt que vers `orders.show`.
* **Templates d'impression & E-mails** :
  * `resources/views/economat/purchase_requests/print.blade.php` : affichage explicite de l'unité avec la quantité (`10 kg`, `8 kg`).
  * `resources/views/economat/orders/print.blade.php` : affichage explicite de l'unité avec la quantité (`12 kg`).
  * `resources/views/emails/economat/purchase-order.blade.php` : affichage de l'unité avec la quantité (`12 kg`).

---

## 5. Critères d'Acceptation & Tests

1. **Tentative de modification / suppression par les achats :**
   * Un appel HTTP direct `PUT /economat/articles/{id}` par un `purchasing_manager` est rejeté en 403 Forbidden.
   * Un appel HTTP direct `DELETE /economat/articles/{id}` par un `purchasing_manager` est rejeté en 403 Forbidden.
   * Un appel HTTP direct `PUT /economat/fournisseurs/{id}` par un `purchasing_manager` est rejeté en 403 Forbidden.
   * Un appel HTTP direct `DELETE /economat/fournisseurs/{id}` par un `purchasing_manager` est rejeté en 403 Forbidden.
   * Aucune altération en base de données.
2. **Autorisation du contrôleur :**
   * Le `controller` peut modifier et supprimer un article (sans mouvements) et un fournisseur (sans commandes).
   * Le `controller` peut ajuster le stock et lier/délier des fournisseurs.
3. **Création directe par les achats :**
   * Le `purchasing_manager` peut créer un article et un fournisseur.
   * Le `purchasing_manager` peut émettre un bon de commande direct via `POST /economat/bons`.
4. **Confidentialité pour l'économe et le magasinier :**
   * Accès à `GET /economat/bons` par l'économe ou le magasinier rejeté en 403 Forbidden.
   * Accès à `GET /economat/bons/{id}` par l'économe ou le magasinier rejeté en 403 Forbidden.
   * Aucune fuite de lien ou de prix de commande dans les écrans de réception et fiches fournisseurs.
5. **Impression et courriels :**
   * Les tests de formatage d'impression et d'envoi de bons sans prix réussissent à 100%.
