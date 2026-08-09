# Hébergement

Le module cœur : le parc de chambres, la vente, le séjour et sa facturation. Toujours
actif, quel que soit l'établissement.

## Le parc

```
RoomType ──1──n── Room ──1──n── RoomImage
   │                │
   │                └──1──n── RoomStatusHistory
   ├──1──n── RoomRate          tarifs
   └──1──1── RoomCostSheet     fiche technique de coût
```

Un **type** porte la capacité, la configuration des lits, les photos et les tarifs.
Une **chambre** est une unité physique de ce type, avec son numéro et son état.

Les types et les chambres s'importent et s'exportent en CSV
(`/rooms/export`, `/rooms/types/export`).

## Le cycle de vie d'une chambre

[`RoomStatus`](../app/Enums/RoomStatus.php) :

```
available → occupied → dirty → cleaning → clean → inspected → available
```

Plus deux états exceptionnels, hors cycle : `maintenance` et `out_of_order`.

Les transitions sont contrôlées par `canTransitionTo()` — on ne saute pas d'étape.
Chaque changement est historisé dans `RoomStatusHistory`.

## Disponibilité

[`RoomAvailabilityService`](../app/Services/RoomAvailabilityService.php) répond à
deux questions distinctes : ce qu'on **affiche** comme disponible, et ce qu'on
**accepte** de réserver.

> Une chambre est disponible **pour des dates**, pas dans l'absolu. Une suite occupée
> cette semaine reste vendable pour le mois prochain — c'est pourquoi le catalogue
> montre tout le parc et que seule la période demandée décide.

Deux règles gouvernent l'acceptation :

**1. Intervalle semi-ouvert `[arrivée, départ)`.** Deux séjours qui se touchent bout
à bout ne se chevauchent pas : le client qui part le 12 libère la chambre pour celui
qui arrive le 12.

**2. Le délai de remise en état.** La rotation le jour même n'est acceptée que si
l'heure d'arrivée tombe après l'heure de départ **augmentée du délai de ménage**.
Une suite présidentielle ne se prépare pas aussi vite qu'une chambre économique — le
délai est configurable par type.

Les délais se règlent dans **Paramètres → Hébergement**, les heures d'arrivée et de
départ dans **Paramètres → Réception**.

> Le site vitrine et la réception passent **tous deux par ce service**. C'est
> délibéré : sans cela, le site public promettrait des dates que la réception
> refuserait.

Les statuts `maintenance` et `out_of_order` rendent la chambre invendable — ils n'ont
pas d'échéance connue, contrairement au cycle de ménage.

## Réservations

### Le cycle

[`BookingStatus`](../app/Enums/BookingStatus.php) :

| Statut | Signification |
|---|---|
| `pending` | En attente — demande à valider |
| `confirmed` | Confirmée |
| `checked_in` | Séjour en cours |
| `checked_out` | Client parti |
| `completed` | Terminée |
| `cancelled` | Annulée |
| `no_show` | Non présenté |

`isFinal()` marque les statuts sans suite (`completed`, `cancelled`, `no_show`).
`blocksRoom()` détermine lesquels immobilisent la chambre — c'est ce que le calcul
de disponibilité interroge.

### Les actions

Toutes les actions métier passent le middleware `caisse` : **rien n'est possible tant
que la caisse de l'utilisateur n'est pas ouverte**.

| Action | Route |
|---|---|
| Créer | `POST /bookings` |
| Modifier | `PUT /bookings/{booking}` |
| Valider | `POST /bookings/{booking}/approve` |
| Confirmer | `POST /bookings/{booking}/confirm` |
| Arrivée | `POST /bookings/{booking}/checkin` |
| Départ | `POST /bookings/{booking}/checkout` |
| Annuler | `POST /bookings/{booking}/cancel` |
| Ligne de folio | `POST /bookings/{booking}/folio` |
| Encaissement | `POST /bookings/{booking}/payment` |

Une réservation porte aussi un **code d'arrivée** (`checkin_code`), envoyé au client
par e-mail ([`CheckinCodeMail`](../app/Mail/CheckinCodeMail.php)), et un **booker**
distinct du client lorsque la réservation est faite par un tiers.

### Réservations de groupe

`GroupBooking` fédère plusieurs réservations individuelles : arrivée et départ
groupés, folio et paiement au niveau du groupe, facture unique.

La création et la modification d'un groupe sont réservées au **manager** — les
arrivées et départs restent ouverts à la réception.

## Le folio

`FolioItem` est le registre du séjour. Il enregistre **toutes** les prestations —
payantes ou offertes :

`room` · `restaurant` · `shop` · `activity` · `spa` · `housekeeping` · `minibar` ·
`laundry` · `discount` · `payment` · `other`

> **Le folio est le point de jonction entre les modules.** Une commande restaurant
> ou boutique réglée « à la chambre » (`room_charge`) ne produit pas d'encaissement
> immédiat : elle devient une ligne de folio, et sera réglée au départ avec le reste
> du séjour.

C'est cette mécanique qui évite le double comptage en comptabilité — voir
[Comptabilité](comptabilite.md).

## Le check-out

[`CheckOutService`](../app/Services/CheckOutService.php) orchestre le départ **en une
seule transaction** :

1. Valider que le départ est possible
2. Finaliser les montants du séjour
3. Créer la facture et ses lignes
4. Passer la chambre en `dirty`
5. Attribuer les points de fidélité
6. Mettre à jour le statut de la réservation

> Si une étape échoue, **tout est annulé**. Aucune donnée partielle ne subsiste : pas
> de facture sans séjour clôturé, pas de chambre libérée sans facture.

## Fidélité

[`LoyaltyService`](../app/Services/LoyaltyService.php) attribue les points au
check-out :

- 1 point par 1 000 FCFA dépensés
- bonus selon le niveau actuel du client
- bonus selon la durée du séjour
- bonus week-end
- le niveau est recalculé après chaque séjour

Les mouvements sont tracés dans `LoyaltyTransaction`.

## Tarification

Trois mécanismes se combinent :

| Mécanisme | Modèle | Usage |
|---|---|---|
| **Tarifs** | `RoomRate` | Prix par type et par période |
| **Packs** | `RoomPackage` | Nuit + prestations groupées, à prix forfaitaire |
| **Partenaires** | `PartnerOrganization` | Conventions avec remise négociée |

Les **prestations** (`ServiceItem`) constituent le catalogue des extras facturables,
et alimentent les lignes de folio.

Les organisations partenaires sont réservées au manager : ces conventions engagent
des remises.

## Fiches techniques de coût

`Paramètres → Hébergement → Fiches techniques`, réservé au **manager et au
comptable**.

[`RoomCostingService`](../app/Services/RoomCostingService.php) calcule le **coût
variable par nuitée occupée** et la **marge de contribution** sur une chambre louée.

Approche *cost per occupied room* : on somme les postes variables — électricité, eau,
consommables, blanchisserie, ménage — ramenés à une nuitée, puis on les compare au
prix réellement pratiqué (ADR).

Un **démarrage rapide** (`applyStarter`) préremplit une fiche avec des postes types,
et l'ensemble s'importe et s'exporte en CSV.

## Housekeeping

Module optionnel (`housekeeping`).

### Organisation

`HousekeepingTeam` regroupe des utilisateurs ; `HousekeepingAssignment` affecte des
chambres à une équipe pour une journée.

### Le cycle de travail

| Action | Qui | Effet |
|---|---|---|
| Affecter | chef d'équipe, manager | Crée les affectations du jour |
| `POST /{room}/clean` | l'agent | `dirty` → `cleaning` |
| `POST /{room}/ready` | l'agent | `cleaning` → `clean` |
| `POST /{room}/inspect` | chef d'équipe | `clean` → `inspected` |
| `POST /{room}/available` | chef d'équipe | `inspected` → `available` |
| `POST /{room}/reject` | chef d'équipe | Refuse un nettoyage : retour en arrière |
| `POST /{room}/issue` | l'agent | Signale un incident, bascule éventuelle en maintenance |

Le rejet n'est possible que depuis l'état `clean` : c'est le contrôle qualité du chef
d'équipe avant remise en vente.

> **Le personnel de ménage ne voit aucun montant.** `canAccessFinancialData()` ne
> l'inclut pas : il voit les chambres et leur état, jamais les tarifs ni les soldes.

Les affectations et les incidents déclenchent des notifications
([`HousekeepingRoomsAssigned`](../app/Notifications/HousekeepingRoomsAssigned.php),
[`HousekeepingIssueReported`](../app/Notifications/HousekeepingIssueReported.php),
[`HousekeepingRoomToInspect`](../app/Notifications/HousekeepingRoomToInspect.php)).

## Clients

`Customer` centralise la fiche client : identité, nationalité, historique de séjours,
points de fidélité. `Guest` distingue les occupants d'une chambre du titulaire de la
réservation.

Import et export CSV disponibles (`/customers/export`, `/customers/import`).

## Factures

`Invoice` et `InvoiceItem` sont produites au check-out. Consultation réservée aux
rôles `manager`, `reception` et `cashier`.

## Pour aller plus loin

- [Comptabilité](comptabilite.md) — caisses et encaissements
- [Restaurant](restaurant.md) — commandes portées au folio
- [Rôles et accès](roles-et-acces.md) — qui peut quoi
