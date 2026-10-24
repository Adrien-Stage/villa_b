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

### Trois caisses, un seul circuit

| Module | Rôles | Routes |
|---|---|---|
| `reception` | `reception` (et son chef), `manager` | `/bookings/cash-register/*` |
| `shop` | `shop_cashier`, `shop_manager` | `/shop/cash-register/*` |
| `restaurant` | `cashier`, ou `restaurant_manager` s'il le faut | `/restaurant/cash-register/*` |

Le circuit est le même partout ([`CashRegisterCircuit`](../app/Services/CashRegisterCircuit.php)) :
ouverture avec un fond, encaissements rattachés à la session, comptage par son
titulaire — la caisse cesse alors d'encaisser —, contresignature de la comptabilité
qui seule la clôt et constate l'écart.

Les caisses sont séparées : une caisse boutique ouverte ne débloque pas les actions de
réception.

**Une caisse par restaurant.** Chaque session désigne son point de vente
(`point_of_sale_id`) : pour un restaurant, sa caisse. Avec plusieurs restaurants, on
choisit la sienne à l'ouverture, parmi les restaurants où l'on est affecté. Une note,
une entrée au buffet ou un règlement de banquet s'encaisse à la caisse **de son
restaurant**.

**Une session par personne.** Chacun compte ce qu'il a encaissé : une personne n'a
qu'une session en cours par caisse, et un tiroir de restaurant n'a qu'une session
ouverte à la fois — la suivante attend que la précédente soit comptée. Un comptage en
attente de contrôle empêche son titulaire d'en ouvrir une nouvelle.

Au restaurant, tout encaissement d'une note passe par la caisse ouverte de celui qui
encaisse ; la facturation sur la chambre n'en demande pas (le folio la porte). Un
encaissement ne s'annule que dans sa propre caisse, tant qu'elle n'est pas comptée.

Le solde théorique d'une caisse de restaurant compte trois sources d'espèces : les
notes, les entrées au buffet au forfait et les règlements de banquets (acompte et
solde). L'écran de comptage les détaille.

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

## Pilotage (contrôle de gestion)

Tuile **Pilotage** (`/pilotage`, droits `pilotage.*`) : l'outil du contrôleur de
gestion. Le contrôleur et le responsable financier l'établissent, la direction le
consulte. Sans module à activer.

### Le budget

`/pilotage/budget` — produits et charges prévus, par centre et par mois
([`BudgetLine`](../app/Models/BudgetLine.php)) : hébergement, restauration, boutique,
économat, et **structure** (le non-ventilé de l'analytique : loyer, assurances,
administration).

Le réalisé ([`RealiseDeGestion`](../app/Services/RealiseDeGestion.php)) vient du
**grand livre analytique** dès que le module `ledger` porte des écritures validées
sur l'année (droits constatés, centre par centre) ; sinon de la **comptabilité de
caisse** (recettes encaissées par activité, dépenses saisies en structure). La source
retenue est affichée sur la grille.

L'écart ([`BudgetDeGestion`](../app/Services/BudgetDeGestion.php)) porte sur les mois
écoulés et se lit dans le sens de l'hôtel : recettes au-dessus, charges en dessous du
budget sont favorables. « Partir du réalisé N−1 » propose le réalisé de l'année
précédente dans la grille, sans rien enregistrer.

### Le tableau de bord de gestion

`/pilotage` — un mois (le mois en cours s'arrête à aujourd'hui), comparé au mois
précédent sur le même nombre de jours
([`TableauDeBordGestion`](../app/Services/TableauDeBordGestion.php)) :

| Domaine | Indicateurs |
|---|---|
| Hébergement | Taux d'occupation, prix moyen (ADR), RevPAR, revenu d'hébergement, revenu par canal de vente, taux d'annulation et de non-présentation des arrivées |
| Restauration | Chiffre d'affaires, couverts, revenu par couvert, coût matière, pertes |
| Trésorerie | Encaissements par activité et par moyen, dépenses, créances clients |
| Budget | Produits et charges du mois par centre, ramenés aux jours écoulés, et leur écart |

Les indicateurs d'hébergement se lisent sur les séjours, nuit par nuit (séjours
offerts exclus du revenu), et non sur les encaissements. Le revenu par couvert ne
porte que sur les commandes qui ont leurs couverts (saisis à la commande en salle).

### Les alertes de dérive

Chaque matin à 7 h 30, `pilotage:alertes`
([`AlertesDeGestion`](../app/Services/AlertesDeGestion.php)) constate sur le mois en
cours :

| Règle | Déclenchement |
|---|---|
| Charges au-dessus du budget | charges d'un centre au-delà du budget ramené aux jours écoulés, plus la tolérance |
| Recettes en retard sur le budget | recettes d'un centre en deçà de ce budget, moins la tolérance |
| Coût matière au-dessus du seuil | coût matière de la restauration depuis le début du mois |
| Marge en baisse | marge d'un centre inférieure de plus de N points à celle du mois précédent |

Les règles de budget et de marge attendent le 8 du mois : avant, un prorata varie trop.
Une dérive est notifiée une fois par règle, centre et mois
([`ManagementAlert`](../app/Models/ManagementAlert.php)) au contrôleur de gestion, au
responsable financier et à la direction ; les jours suivants, sa valeur se met à jour.
Les seuils (tolérance, coût matière, baisse de marge) se règlent sur le tableau de bord.

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
