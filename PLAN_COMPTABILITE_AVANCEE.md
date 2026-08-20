# Plan d'implémentation — Module « Comptabilité Avancée » (SYSCOHADA révisé)

> Rubrique cible : `Comptabilité` (`/accounting/*`)
> Source fonctionnelle : *Guide Stratégique — Intégration de la Comptabilité SYSCOHADA Révisée dans l'ERP Hôtelier*
> **Périmètre arrêté : Phases 0 à 6** (socle légal + auxiliaire + fournisseurs + analytique)

---

## 1. Paramètres arrêtés

Ces cinq points sont tranchés et conditionnent tout le reste.

| Paramètre | Décision |
|---|---|
| **Pays** | Cameroun — TVA **19,25 %** |
| **Base de prix** | Les prix affichés restent **TTC**. La TVA est extraite « en dedans ». |
| **Taxe de séjour** | À gérer : montant par nuitée selon le classement de l'établissement |
| **Date de bascule** | 1ᵉʳ janvier de l'exercice, avec **reprise des à-nouveaux** N-1 |
| **Périmètre** | Phases 0 → 6, jusqu'à l'analytique incluse |
| **Validation** | Un expert-comptable valide — **avant la Phase 1** |

Conséquences immédiates :

- **L'export e-Tax Bénin sort du périmètre.** Le document source était rédigé pour le Bénin ; la télédéclaration camerounaise relève de la DGI et suit son propre format. À spécifier séparément le jour venu.
- **Le plan de comptes SYSCOHADA reste valable** : il est commun à l'espace OHADA. Seuls les taux et les téléprocédures sont nationaux.

---

## 2. Où en est le module aujourd'hui

Audit du code réel, pas des intentions.

### Ce qui existe et sert

`AccountingService` (321 lignes) implémente une **comptabilité de caisse** : `recettes()`, `depenses()`, `compteDeResultat()`, `creances()`, `caisse()`, `journal()`. Six écrans sous `/accounting`, réservés à `accountant`, `manager`, `admin`.

Plusieurs briques sont déjà en place, et elles comptent :

| Brique | Ce qu'elle apporte |
|---|---|
| `StockService` — CMUP + mouvements tracés | Alimente `601`/`602`/`6032` sans rien réécrire |
| `RestaurantStockService` — stock théorique valorisé | Le Food Cost existe déjà, il lui manque son écriture |
| `RoomCostSheet` — marge de contribution par type | Socle de l'analytique classe 9 |
| `CashRegisterSession` — clôture avec écart constaté | Point d'accroche naturel du night audit |
| Montants en **centimes entiers** partout | Équilibre débit/crédit exact, sans dérive de flottant |

### Les trois écarts bloquants

**1. Le chiffre d'affaires n'est stocké nulle part.**

`recettes()` et `journal()` **recalculent** tout à la volée depuis `Payment`, `RestaurantCustomerOrder` et `ShopOrder`. Seule la table `expenses` persiste quelque chose.

> Un grand livre ne peut pas fonctionner ainsi. Une écriture est un **fait daté et figé** : elle doit survivre à la modification, voire à la suppression, de l'opération qui l'a produite. Passer au SYSCOHADA, c'est d'abord passer d'un calcul à un enregistrement.

**2. La TVA n'existe pas.**

Les colonnes sont là (`bookings.tax_amount`, `invoices.tax_amount`, `invoice_items.tax_rate`), mais :

- `BookingController:620` — `$taxAmount = 0;` **codé en dur** ;
- l'onglet *Taxes* des paramètres est une **maquette** : « Aucune taxe configurée », bouton inerte ;
- `vat_rate` n'existe que comme colonne d'export CSV, lue nulle part.

**3. Aucune notion de tiers comptable, de période, ni de verrouillage.**

Pas de plan de comptes, pas de journal comptable, pas d'exercice. `creances()` calcule des soldes dus mais ignore l'ancienneté et le lettrage.

---

## 3. Le calcul TTC → HT, en détail

C'est le cœur de la Phase 0, et l'endroit où une erreur d'arrondi devient une balance fausse.

Les prix saisis restent des TTC. La TVA se calcule **en dedans** :

```
HT  = TTC / 1,1925
TVA = TTC − HT
```

> **Ne jamais arrondir HT et TVA indépendamment.** On arrondit le HT au centime, puis on **déduit** la TVA par différence. C'est la seule façon de garantir `HT + TVA = TTC` à l'unité près sur chaque ligne — sinon la balance dérive d'un centime par facture, et le grand livre ne s'équilibre plus.

```php
$ht  = (int) round($ttc / 1.1925);
$tva = $ttc - $ht;   // jamais round($ttc * 0.1925 / 1.1925)
```

### La structure du 19,25 %

Le taux camerounais se décompose en **TVA 17,5 %** majorée des **centimes additionnels communaux (CAC) à 10 %** de la TVA : `17,5 × 1,10 = 19,25 %`.

> **À soumettre à l'expert-comptable :** la TVA et les CAC doivent-ils être comptabilisés sur **un seul compte** `4431`, ou **ventilés** en deux lignes (TVA d'un côté, CAC de l'autre) ? La réponse change le schéma d'imputation, et il est beaucoup moins coûteux de la trancher avant la Phase 1 qu'après.

---

## 4. La taxe de séjour

C'est un ajout au périmètre initial, et elle **ne se comptabilise pas comme du chiffre d'affaires**.

| Caractéristique | Traitement |
|---|---|
| Assiette | Par nuitée, montant fixe selon le classement de l'établissement |
| Nature | Perçue **pour le compte de la fiscalité locale** — c'est une dette, pas un produit |
| Imputation | Crédit d'un compte de tiers (`447x`), **jamais** `706` |
| TVA | À priori hors base TVA — collecte pour un tiers |

Le schéma d'une nuitée devient donc :

```
D 411000   TTC encaissable auprès du client
   C 706     hébergement HT
   C 4431    TVA (+ CAC)
   C 447x    taxe de séjour — dette envers la commune
```

> ⚠️ **Les montants que tu m'as transmis (500 à 5 000 FCFA selon le standing) doivent être vérifiés.** L'une des deux sources citées concerne la **Côte d'Ivoire**, pas le Cameroun. Le barème, l'assiette (par personne ou par chambre ?) et l'exclusion de la base TVA sont à confirmer auprès de l'expert-comptable, textes à l'appui.

Implications produit :

- Le **classement de l'établissement** (nombre d'étoiles) devient un paramètre, aujourd'hui inexistant
- Un **barème** paramétrable, pas un montant codé en dur
- Une ligne distincte sur la facture client — le document légal doit la faire apparaître séparément

---

## 5. La reprise des à-nouveaux

Tu as tranché pour la **continuité d'exploitation**, pas le démarrage à neuf. C'est ce qu'impose l'OHADA pour une structure en activité, et cela ajoute un livrable que le plan initial n'avait pas.

| Règle | Traduction technique |
|---|---|
| Le grand livre démarre au **1ᵉʳ janvier** de l'exercice | `fiscal_years` avec date d'ouverture |
| Les comptes de **bilan** (classes 1-5) reprennent leurs soldes N-1 | Écriture d'à-nouveaux, journal `OD`, datée du 1ᵉʳ janvier |
| Les comptes de **gestion** (classes 6-7) repartent à zéro | Aucune reprise |
| Les **comptes de tiers** reprennent solde **et** détail | Reprise ligne à ligne, avec l'auxiliaire — sinon le lettrage et la balance âgée démarrent aveugles |

> **Le point délicat** : ces soldes N-1 n'existent nulle part dans l'application. Ils viennent de la comptabilité tenue jusqu'ici par le cabinet — vraisemblablement sur tableur ou sur un autre logiciel. Il faut donc un **écran de saisie de la balance d'ouverture**, avec import CSV, et un contrôle d'équilibre qui refuse une balance déséquilibrée.

C'est un livrable à part entière de la Phase 1, pas une note de bas de page. Sans lui, le module démarre sur des créances clients à zéro alors que l'établissement a des factures en cours.

---

## 6. Décisions d'architecture

### 6.1 Le module s'ajoute, il ne remplace pas

La comptabilité de caisse actuelle **reste**. Elle répond à une question quotidienne — « combien est entré en caisse aujourd'hui » — que le grand livre ne remplace pas.

```
/accounting              Comptabilité de caisse  (existant, inchangé)
/accounting/ledger       Comptabilité générale   (nouveau)
```

### 6.2 Comptes collectifs + auxiliaire

C'est la mise en garde centrale du document, et elle se traduit en décision de schéma :

```php
// ❌ Ce qu'il ne faut PAS faire
Account::create(['code' => '411001', 'name' => 'Client Jean Dupont']);

// ✅ Un seul compte collectif, la granularité sur la ligne
JournalEntryLine::create([
    'account_code'   => '411000',
    'auxiliary_type' => Customer::class,   // ou Supplier::class, User::class
    'auxiliary_id'   => $customer->id,
]);
```

### 6.3 Un seul écrivain

Le projet a déjà cette convention pour le stock. On l'étend :

> **Toute écriture comptable passe par `LedgerService`.** Il est seul à insérer dans `journal_entries`, et refuse toute écriture déséquilibrée (`Σ débit ≠ Σ crédit`) ou visant une période verrouillée.

### 6.4 Idempotence obligatoire

```
journal_entries : source_type + source_id + schema  →  contrainte UNIQUE
```

Sans elle, une double génération est indétectable a posteriori, et non corrigible autrement que par extourne.

### 6.5 Immutabilité

Pas d'`update()` ni de `delete()` sur une écriture validée. La correction est une **contre-passation** : écriture inverse, datée du jour de la correction, référençant l'originale.

---

## 7. Validation par l'expert-comptable *(avant Phase 1)*

À soumettre au cabinet, dans cet ordre :

1. **Le plan de comptes** — extrait SYSCOHADA révisé retenu, comptes collectifs, comptes de produits par activité (hébergement, restaurant, boutique)
2. **TVA et CAC** — un compte `4431` unique, ou ventilation TVA / CAC ?
3. **La taxe de séjour** — barème applicable au Cameroun, assiette (personne ou chambre), compte `447x` exact, inclusion ou non dans la base TVA
4. **Les schémas d'imputation** — la table du §9.2, ligne par ligne
5. **La balance d'ouverture** — format de reprise attendu, niveau de détail sur les tiers
6. **Le calendrier de verrouillage** — délai retenu au titre de l'Article 22

> Cette validation est un **jalon bloquant**, pas une revue de courtoisie. Un plan de comptes corrigé après la Phase 2 impose de régénérer toutes les écritures produites entre-temps.

---

## 8. Séquencement

```
      Validation expert-comptable
                 │
Phase 0  TVA + taxe de séjour ──────► bloque tout le reste
                 │
Phase 1  Plan de comptes + journaux + à-nouveaux
                 │
Phase 2  Écritures automatiques
                 │
      ┌──────────┼──────────┐
      ▼          ▼          ▼
Phase 3     Phase 4     Phase 5          (parallélisables)
Night audit Auxiliaire  Fournisseurs
verrouillage lettrage   + RAS
      └──────────┼──────────┘
                 ▼
           Phase 6  Analytique          ← fin du périmètre
                 ┆
           Phase 7  Liasse fiscale       (hors périmètre)
```

---

## 9. Le plan par phases

### Phase 0 — TVA et taxe de séjour *(prérequis bloquant)*

| Tâche | Détail |
|---|---|
| Table `tax_rates` | Libellé, taux, compte de TVA collectée (`4431`), compte déductible (`4451`), actif |
| Extraction « en dedans » | Helper unique, arrondi du HT puis TVA par différence (§3) |
| Positions fiscales | Exonérations : export, client international → 0 % |
| Classement établissement | Nouveau paramètre — conditionne le barème de la taxe de séjour |
| Barème taxe de séjour | Paramétrable, par nuitée, selon le classement |
| Onglet *Taxes* fonctionnel | Remplacer la maquette de `settings/index.blade.php:326` |
| Alimenter `tax_amount` | Retirer le `= 0` de `BookingController:620`, `GroupBookingController`, `ShopOrderController`, `PublicBookingController` |
| Affichage HT / TVA / taxe / TTC | Folio, facture, commandes resto et boutique |

> **Les prix restent TTC** : aucun tarif ne change pour le client. Ce qui change, c'est leur **décomposition** sur les documents. La facture doit désormais faire apparaître le HT, la TVA et la taxe de séjour séparément — ce qui la rend opposable, alors qu'aujourd'hui elle ne l'est pas.

### Phase 1 — Socle : plan de comptes, journaux, à-nouveaux

| Livrable | Contenu |
|---|---|
| `accounts` | Code, libellé, classe (1-9), type, collectif oui/non, actif |
| Plan SYSCOHADA | Livré par **migration de données** — les seeders ne sont jamais rejoués en production |
| `journals` | `VT` ventes · `AC` achats · `BQ` banque · `CA` caisse · `OD` opérations diverses |
| `fiscal_years` / `fiscal_periods` | Exercice, période mensuelle, `locked_at` |
| `journal_entries` | Date, journal, libellé, `source_type`/`source_id`, `posted_at`, `reversed_by` |
| `journal_entry_lines` | Compte, débit, crédit, `auxiliary_type`/`auxiliary_id`, centre analytique |
| `LedgerService` | Écrivain unique, contrôle d'équilibre, refus si période verrouillée |
| **Balance d'ouverture** | Écran de saisie + import CSV + contrôle d'équilibre (§5) |
| **Écriture d'à-nouveaux** | Génération au 1ᵉʳ janvier depuis la balance d'ouverture |
| Écrans | Plan de comptes, journaux, grand livre, balance générale |

> Le plan de comptes doit passer par une **migration de données**, pas par un seeder : côté production, `ProductionTenantSeeder` ne tourne qu'au tout premier démarrage. Un plan livré en seeder n'atteindrait aucun établissement déjà en service.

### Phase 2 — Génération automatique des écritures

Un schéma d'imputation par événement métier, chacun idempotent.

| Événement | Écriture |
|---|---|
| Check-out / facture | `D 411000` (aux. client) · `C 706` · `C 4431` · `C 447x` taxe de séjour |
| Encaissement espèces | `D 571` caisse · `C 411000` |
| Encaissement banque | `D 521` banque · `C 411000` |
| Vente restaurant | `D 411000` ou `571` · `C 706`/`701` · `C 4431` |
| Vente boutique | `D 571` · `C 701` · `C 4431` |
| Réception fournisseur | `D 601`/`602` · `D 4451` TVA déductible · `C 401000` (aux. fournisseur) |
| Sortie de stock (fiche technique) | `D 6032` · `C 32x` — le Food Cost |
| Transfert inter-centres | `D`/`C 588` |
| Dépense saisie | `D 6xx` · `C 571`/`521` |

**Le `room_charge` doit être respecté.** Une commande portée au folio n'est pas un encaissement : elle crédite le produit et débite le compte client, sans mouvement de trésorerie. La logique anti-double-comptage existe déjà dans `AccountingService` — la reprendre à l'identique, ne pas la réinventer.

### Phase 3 — Night audit et verrouillage (Article 22)

| Tâche | Détail |
|---|---|
| Clôture journalière | Valide le CA du jour, génère les écritures manquantes, fige la journée |
| Point d'accroche | La fermeture de `CashRegisterSession` existe et constate déjà un écart |
| Verrouillage de période | `locked_at`, ≤ 1 mois après la fin du mois |
| Extourne | Seul mode de correction après verrouillage |
| Commande planifiée | Le planificateur tourne déjà dans le conteneur, rien à installer |

### Phase 4 — Auxiliaire et lettrage

- Grand livre auxiliaire clients et fournisseurs, par filtre sur `auxiliary_*`
- Lettrage automatique paiement ↔ facture, lettrage manuel en complément
- **Balance âgée** — remplace `creances()`, qui ignore aujourd'hui l'ancienneté
- Reprend le détail des tiers issu de la balance d'ouverture

### Phase 5 — Fournisseurs et retenues à la source

- Facture fournisseur adossée aux `PurchaseOrder` existants
- RAS **5 %** (prestations) · **10 %** / **15 %** (honoraires, prestations intellectuelles)
- Comptabilisée en taxe négative sur la facture d'achat → compte `4421`
- État des retenues pour la déclaration

> Les taux de RAS cités viennent du document béninois. **À faire confirmer** pour le Cameroun au même titre que la taxe de séjour.

### Phase 6 — Analytique (classe 9) — *fin du périmètre*

- Centres de profit : hébergement, restaurant, boutique, économat
- Comptes reflets `91` — mirer les charges sans toucher au bilan
- **RevPAR** : `706` croisé avec l'inventaire chambres
- **Marge brute par point de vente** : classe 7 contre classe 6
- Réutilise `RoomCostSheet` (marge de contribution) et le CMUP de `StockService`

### Phase 7 — Liasse fiscale *(hors périmètre de cette itération)*

Bilan et compte de résultat SYSCOHADA, TAFIRE, états N/N-1, télédéclaration DGI Cameroun.

> La reprise des à-nouveaux (Phase 1) rend le comparatif **N/N-1 possible dès la clôture du premier exercice**. C'est la suite naturelle, à spécifier quand le périmètre actuel sera en production.

---

## 10. Ce que je recommande d'exclure

| Piste | Pourquoi l'écarter |
|---|---|
| **Comptabilité en triple entrée (blockchain)** | Présentée comme prospective par le document lui-même. Aucune administration OHADA ne la reconnaît. L'immutabilité de l'Article 22 s'obtient par le verrouillage et l'extourne. |
| **Détection de fraude par IA** | Suppose un historique d'écritures qui n'existera qu'après plusieurs mois d'exploitation. À reconsidérer après la Phase 3, sur données réelles. |
| **Export e-Tax Bénin** | Hors cible géographique. L'équivalent camerounais relève de la DGI et suit son propre format. |

---

## 11. Risques

| Risque | Portée | Atténuation |
|---|---|---|
| **Arrondi TTC → HT** | Balance fausse d'un centime par facture | Arrondir le HT, déduire la TVA par différence (§3). À couvrir par des tests dès la Phase 0. |
| **Barème taxe de séjour erroné** | Dette sous-évaluée envers la commune | Confirmer le barème camerounais — l'une des sources fournies concerne la Côte d'Ivoire |
| **Balance d'ouverture déséquilibrée** | Grand livre faux dès le premier jour | Contrôle d'équilibre bloquant à l'import, validation par le cabinet |
| **Double génération d'écritures** | Fausse la balance | Contrainte `UNIQUE` sur `source_type + source_id + schema` dès la Phase 1 |
| **Plan de comptes absent en production** | Module inutilisable | Migration de données, jamais un seeder seul |
| **Plan de comptes corrigé tardivement** | Régénération de toutes les écritures | Validation expert-comptable **avant** la Phase 1 |
| **TVA/CAC mal ventilés** | Déclaration non conforme | Trancher avec le cabinet avant la Phase 1 (§3) |

---

## 12. Pour aller plus loin

- [Comptabilité (existant)](docs/comptabilite.md) — ce que fait le module aujourd'hui
- [Architecture](docs/architecture.md) — couche services, conventions, montants en centimes
- [Économat et boutique](docs/economat-et-boutique.md) — CMUP et mouvements, socle des comptes 60x
- [Hébergement](docs/hebergement.md) — folio et `room_charge`, à respecter en Phase 2
