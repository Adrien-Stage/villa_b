# Comptabilité

Deux briques distinctes : les **caisses**, qui encadrent chaque encaissement au
quotidien, et la **comptabilité de caisse**, qui en tire les états financiers.

---

## Les sessions de caisse

Aucune opération d'argent n'existe hors d'une session de caisse nominative.

### Le principe

Un utilisateur **ouvre sa caisse** en déclarant son fonds de départ. Toutes ses
opérations y sont rattachées. En fin de service, il **ferme sa caisse** en déclarant
le montant réellement compté.

`CashRegisterSession` enregistre :

| Champ | Contenu |
|---|---|
| `user_id`, `module` | Qui, et sur quelle activité |
| `opening_amount` | Fonds de caisse déclaré à l'ouverture |
| `theoretical_closing_amount` | Ce que le système attend |
| `actual_closing_amount` | Ce qui est réellement compté |
| `discrepancy_amount` | L'écart entre les deux |
| `notes`, `closing_notes` | Justifications |

> **L'écart est la donnée qui compte.** Une caisse qui tombe juste ne prouve rien
> d'autre que sa cohérence ; un écart récurrent sur une même personne ou un même
> service est un signal exploitable.

### Deux caisses indépendantes

| Module | Rôles | Routes |
|---|---|---|
| `reception` | `reception`, `cashier`, `manager` | `/bookings/cash-register/*` |
| `shop` | `shop_manager`, `shop_cashier` | `/shop/cash-register/*` |

Elles sont totalement séparées : une caisse boutique ouverte ne débloque pas les
actions de réception.

### Le verrou

[`EnsureCashRegisterOpen`](../app/Http/Middleware/EnsureCashRegisterOpen.php), alias
`caisse`, bloque toute action métier sur une réservation tant que la caisse de
l'utilisateur n'est pas ouverte : modification, arrivée, départ, ligne de folio,
encaissement.

> Ce n'est pas un masquage de boutons : le middleware bloque aussi un POST direct
> hors interface. C'est ce qui garantit que **toute opération d'argent est rattachable
> à une session nominative**.

### Qui ouvre ferme

La fermeture n'a pas de restriction de rôle supplémentaire : le contrôleur borne la
session à `auth()->id()`. Chacun ferme la sienne, personne ne ferme celle d'un autre.

Une reprise de caisse existe (`POST /cash-register/resume`) pour retrouver une
session laissée ouverte.

### Décaissements

`CashRegisterDisbursement` enregistre les sorties d'espèces en cours de service —
petits achats, avances, remboursements. Elles entrent dans le calcul du montant
théorique de clôture.

---

## La comptabilité de caisse

[`AccountingService`](../app/Services/AccountingService.php) — accessible aux rôles
`accountant`, `manager` et `admin`.

### Le principe

> On ne compte l'argent que lorsqu'il **entre** (recette encaissée) ou **sort**
> (dépense décaissée).

C'est une comptabilité de trésorerie, pas d'engagement. Une facture émise mais non
réglée n'est pas une recette — elle est une créance.

### Les recettes ne sont pas stockées

Point d'architecture important :

> Les recettes **ne sont pas dupliquées** dans une table dédiée. Elles sont lues à la
> volée dans les paiements et les commandes des trois activités — hébergement,
> restauration, boutique. Seules les **dépenses** saisies vivent dans une table
> (`Expense`).

Conséquence : il n'y a pas de risque de désynchronisation entre le module et la
comptabilité, mais chaque état est recalculé à la demande.

### L'anti-double-comptage

C'est la subtilité centrale du module :

> Une commande restaurant ou boutique réglée « à la chambre » (`room_charge`) **n'est
> pas une recette directe**. Elle grossit le solde du séjour et sera encaissée via le
> paiement de ce séjour. On l'exclut donc des recettes.

Sans cette exclusion, un dîner porté au folio serait compté deux fois : une fois au
restaurant, une fois à la réception.

### Les états

| État | Route | Contenu |
|---|---|---|
| **Tableau de bord** | `/accounting` | Vue d'ensemble de la période |
| **Journal** | `/accounting/journal` | Chronologie des entrées et sorties |
| **Compte de résultat** | `/accounting/compte-de-resultat` | Recettes − dépenses par activité |
| **Créances** | `/accounting/creances` | Soldes restant dus par séjour |
| **Caisse** | `/accounting/caisse` | Sessions, écarts, décaissements |
| **Dépenses** | `/accounting/depenses` | Saisie et suivi |

Les créances ne remontent que pour les statuts de séjour où un solde restant dû est
une vraie créance : `confirmed`, `checked_in`, `checked_out`, `completed`. Une
réservation annulée avec un solde n'est pas une créance.

### Les dépenses

`Expense` est la seule table d'écritures saisies : montant, catégorie, date, pièce
justificative. Création, modification et suppression depuis `/accounting/depenses`.

### Montants

Tous les montants sont en **centimes FCFA**, en entier. Aucun flottant ne circule
dans les calculs financiers.

---

## Analytics

Module optionnel (`analytics`), réservé au **manager** — distinct de la comptabilité.

`/analytics` agrège le chiffre d'affaires des trois activités sur une période
(`today`, `week`, `month`, `year`) à des fins de pilotage, pas de tenue de comptes.

---

## Pour aller plus loin

- [Hébergement](hebergement.md) — le folio et les paiements de séjour
- [Restaurant](restaurant.md) — le règlement à la chambre
- [APIs et intégrations](apis-et-integrations.md) — l'API de reporting consommée par la console business
