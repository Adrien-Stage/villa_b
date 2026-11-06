# Spécifications Exhaustives — Lot 4 : Inventaire Contrôleur → RAF (SoD & Faille S8)

Ce document constitue la référence absolue pour l'implémentation du **Lot 4**. Il formalise et applique les exigences de l'audit métier et sécurité du 5 novembre 2026 ([`2026-11-05-audit-metier-securite.md`](./2026-11-05-audit-metier-securite.md)), en particulier le **Constat S8** (inventaire sans validation exclusive du RAF, absence de contre-écritures et absence de verrou temporel).

---

## 1. Objectifs & Enjeux Métier

Dans les établissements hôteliers du groupe, l'inventaire physique périodique est l'acte comptable et de contrôle interne le plus critique :
1. **Séparation des Tâches (SoD) :** L'agent qui détient physiquement les stocks (Économe, Magasinier, Chef cuisinier) ne doit **ni constater seul, ni clôturer, ni régulariser** les écarts de stock de son propre périmètre.
2. **Exclusivité du Contrôle de Gestion (`controller`) :** Le contrôleur de gestion (ou son équipe de contrôle des marges) est le **seul habilité à ouvrir et saisir l'inventaire contradictoire**.
3. **Exclusivité de Validation du RAF (`manager` / `finance_manager`) :** Aucun mouvement de régularisation financière ou d'ajustement physique de stock ne s'applique tant que le RAF n'a pas formellement approuvé l'inventaire. Le contrôleur ne peut en aucun cas valider ses propres comptages.
4. **Verrou Temporel des 48 Heures :**
   - **Verrou de saisie :** 48 heures maximum après l'ouverture de la feuille pour saisir et soumettre le comptage. Au-delà, l'inventaire non soumis est gelé.
   - **Verrou d'annulation RAF :** Le RAF dispose d'un délai strict de **48 heures après validation** pour annuler un inventaire validé si une anomalie est découverte. Passé ce délai de 48h, l'inventaire est irrévocablement verrouillé.
5. **Intégrité Comptable & Contre-passation :** En cas d'annulation sous 48h par le RAF, le système ne se contente pas de changer un drapeau en base de données : il génère des **contre-écritures de régularisation opposées** (contre-passation intégrale des mouvements de stock) pour préserver une piste d'audit irréprochable.
6. **Isolation des Périmètres :** L'inventaire d'un dépôt ou d'un restaurant n'immobilise jamais le reste de l'établissement.

---

## 2. Matrice des Droits et Exclusivités Métier

### 2.1. Contrôleur de Gestion (`controller`)
* **Missions autorisées :**
  * Ouverture de la feuille d'inventaire physique (`economat.stock_counts.creer`, `economat.stores.counts.creer`, `restaurant.stock_counts.creer`).
  * Saisie et modification des quantités comptées et motifs d'écarts (`economat.stock_counts.modifier`, `economat.stores.counts.modifier`, `restaurant.stock_counts.modifier`).
  * Importation du fichier de comptage Excel/CSV (`economat.stock_counts.import`).
  * Soumission de l'inventaire au RAF pour validation (`economat.stock_counts.submit`, `economat.stores.counts.submit`, `restaurant.stock_counts.submit`).
  * Abandon / Annulation d'un inventaire non validé (en état `draft` ou `submitted`) (`economat.stock_counts.cancel`, `economat.stores.counts.cancel`, `restaurant.stock_counts.cancel`).
  * Consultation et export des feuilles d'inventaire et PV (`voir`, `export`, `report`).
* **Interdictions formelles :**
  * **Interdiction absolue de valider ou clôturer l'inventaire** (`stock_counts.close` / `validate` interdit au contrôleur).
  * **Interdiction d'annuler un inventaire déjà validé** (réservé exclusivement au RAF).

### 2.2. Directeur / RAF (`manager` / `finance_manager`)
* **Exclusivités fermes et non dérogeables (Constat S8) :**
  * **Seul habilité à valider l'inventaire** (`economat.stock_counts.close`, `economat.stores.counts.close`, `restaurant.stock_counts.close`). C'est cette validation qui déclenche l'ajustement effectif des stocks.
  * **Seul habilité à rejeter une soumission** d'inventaire (`stock_counts.reject`), renvoyant la feuille au contrôleur avec motif de rejet.
  * **Seul habilité à annuler un inventaire déjà validé** (`stock_counts.cancel_validated`), exclusivement **dans les 48 heures suivant la validation**, avec génération de contre-écritures automatiques.
* **Interdictions formelles :**
  * Ne saisit pas les quantités physiques à la place du contrôleur (respect du principe de séparation constatation / validation).

### 2.3. Économe (`econome`), Magasiniers (`storekeeper`) et Chefs (`restaurant_chief`)
* **Retrait strict des privilèges de décision :**
  * **Interdiction formelle d'ouvrir, modifier, soumettre, valider ou annuler un inventaire**.
  * Autorisation de consultation (`voir`, `report`) et de téléchargement de la trame de comptage papier / Excel (`export`).

---

## 3. Cycle de Vie & Machine à États de l'Inventaire

Chaque inventaire (Magasin central, Dépôt de service, Restaurant) suit la machine à états suivante :

```
             ┌────────────────────────────────────────────────────────┐
             │                                                        │
             ▼                                                        │
         [ DRAFT ] ─────────── (48h max de saisie)                    │ Rejet RAF
             │                                                        │ (avec motif)
             │ Contrôleur : soumettre                                 │
             ▼                                                        │
       [ SUBMITTED ] ─────────────────────────────────────────────────┘
             │
             │ RAF : valider
             ▼
        [ VALIDATED / CLOSED ] ─── (Stock ajusté)
             │
             │ RAF : annuler (fenêtre stricte <= 48h)
             ▼
        [ CANCELLED ] ──────────── (Contre-passation des stocks)
```

1. **`draft` (Brouillon en cours de comptage) :**
   - Stock théorique et CUMP figés à l'ouverture.
   - Saisie par le contrôleur.
   - Si `now() > created_at + 48h`, la saisie est verrouillée.
2. **`submitted` (Soumis au RAF) :**
   - Les comptages sont verrouillés en attente de la décision du RAF.
   - Aucun mouvement de stock réel n'a encore été créé.
3. **`closed` / `validated` (Validé par le RAF) :**
   - Le RAF approuve.
   - Les mouvements de stock (`StockMovement`, `ServiceStoreMovement`, `RestaurantPantryMovement`) de type `ADJUSTMENT` sont générés sous transaction atomique.
   - Horodatage `validated_at` et enregistrement de `validated_by`.
4. **`rejected` (Rejeté par le RAF) :**
   - Le RAF refuse l'inventaire avec un motif obligatoire (`rejection_reason`).
   - L'inventaire repasse en `draft` (ou statut `rejected`) pour permettre au contrôleur de corriger les lignes contestées.
5. **`cancelled` (Annulé) :**
   - Si annulé avant validation : la feuille est marquée `cancelled` sans aucun impact sur le stock.
   - Si annulé **après validation par le RAF (dans la limite des 48h)** :
     - Vérification stricte : `now() <= validated_at + 48h`.
     - Génération des **contre-écritures de stock** inverses pour rétablir les stocks initiaux.
     - Horodatage `cancelled_at`, enregistrement de `cancelled_by` et du motif `cancellation_reason`.

---

## 4. Règle du Verrou des 48 Heures

1. **Calcul du délai d'annulation :**
   $$\Delta t = \text{now}() - \text{validated\_at}$$
   - Si $\Delta t \le 48\text{ h}$ : Annulation autorisée pour le RAF.
   - Si $\Delta t > 48\text{ h}$ : Rejet automatique par le serveur (`403 Forbidden` ou `422 Unprocessable Entity` avec message d'interdiction explicite).
2. **Calcul du délai de saisie :**
   - Si $\text{now}() > \text{created\_at} + 48\text{ h}$ en statut `draft` : La mise à jour des comptages est refusée. Le contrôleur doit abandonner l'inventaire périmé et en ouvrir un nouveau pour refléter la réalité physique du jour.

---

## 5. Contre-Écritures & Piste d'Audit

Lors de l'annulation d'un inventaire validé :
1. Chaque mouvement d'ajustement initial est identifié par sa source (`source_type` = `stock_count` et `source_id` = `$inventaire->id`).
2. Pour chaque ligne ayant eu une variance $\Delta q \ne 0$ :
   - Le stock physique est réajusté de $-\Delta q$.
   - Un mouvement de stock compensatoire est créé avec le type `TYPE_ADJUSTMENT`, quantité $-\Delta q$, et motif :
     `"Contre-passation annulation inventaire REF par RAF Nom"`
3. L'historique d'audit `audit_logs` / `activity_log` consigne l'opération complète (auteur, motif, horodatage, montants contre-passés).

---

## 6. Impacts Base de Données

Ajout des colonnes de traçabilité sur les 3 tables d'inventaire (`stock_counts`, `service_store_counts`, `restaurant_stock_counts`) via une nouvelle migration :
- `submitted_at` (timestamp, nullable)
- `submitted_by` (foreignId vers `users`, nullable)
- `validated_at` (timestamp, nullable)
- `validated_by` (foreignId vers `users`, nullable)
- `rejected_at` (timestamp, nullable)
- `rejected_by` (foreignId vers `users`, nullable)
- `rejection_reason` (string 500, nullable)
- `cancelled_at` (timestamp, nullable)
- `cancelled_by` (foreignId vers `users`, nullable)
- `cancellation_reason` (string 500, nullable)

---

## 7. Plan de Tests (100% Succès Requis)

Les tests dans `tests/Feature/Lot4InventaireControleurRafTest.php` vérifieront :
1. **Contrôleur seul habilité à ouvrir et saisir :**
   - `controller` peut ouvrir, saisir, importer et soumettre.
   - `econome`, `storekeeper`, `restaurant_chief`, `purchasing_manager` se voient refuser l'ouverture et la saisie.
2. **RAF seul habilité à valider :**
   - `manager` peut valider l'inventaire soumis ; les stocks sont régularisés.
   - `controller` se voit refuser la validation de l'inventaire (SoD stricte).
   - `econome` et autres rôles se voient refuser la validation.
3. **Rejet de soumission par le RAF :**
   - `manager` peut rejeter avec motif ; la feuille redevient modifiable par le contrôleur.
4. **Verrou 48h pour la saisie :**
   - Saisie acceptée à $H+47\text{h}59\text{m}$.
   - Saisie refusée à $H+48\text{h}01\text{m}$.
5. **Annulation sous 48h par le RAF avec contre-écritures :**
   - À $H+47\text{h}$ après validation, le RAF annule : les stocks initiaux sont parfaitement rétablis via contre-passation.
   - Tentative d'annulation par le contrôleur : refusée.
   - Tentative d'annulation par le RAF à $H+48\text{h}01\text{m}$ : rejetée avec message de verrou expiré.
6. **Isolation des périmètres :**
   - L'inventaire d'un dépôt de service ou d'un restaurant ne bloque pas les mouvements des autres dépôts ni du magasin central.
7. **Idempotence & Concurrence :**
   - Deux validations simultanées ne génèrent pas un double mouvement de stock.
