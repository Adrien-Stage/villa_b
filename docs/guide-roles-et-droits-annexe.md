# Annexe — Qui détient quoi

> Générée par `php artisan droits:annexe` depuis le catalogue des droits
> (`PermissionCatalog`). Ne pas modifier à la main : relancer la commande après
> tout changement du catalogue.
>
> Elle montre le **modèle livré**, hiérarchie comprise (un chef détient les droits
> de ses membres). Les écarts posés par la console, par l'hôtel ou sur une
> personne se lisent dans l'application, écran **Rôles & droits**, et sur la fiche
> de chaque employé. Retour au [guide](guide-roles-et-droits.md).

## Résumé par rôle

| Rôle | Niveau | Inclut | Consulte | Agit | Services où il agit |
|---|---|---|---:|---:|---|
| Administrateur (`admin`) | 1 | — | 112 | 28 | Restaurant, Chambres, Paramètres, Rôles & droits, Interventions, Utilisateurs |
| Manager (`manager`) | 2 | — | 110 | 89 | Restaurant, Réservations, Économat, Chambres, Paramètres, Groupes, Housekeeping, Clients, POS Réception, Utilisateurs, Planning |
| Chef de réception (`reception_chief`) | 3 | `reception` | 32 | 43 | Réservations, Économat, Chambres, Paramètres, Groupes, Clients, POS Réception, Planning |
| Réceptionniste (`reception`) | 4 | — | 30 | 28 | Réservations, Économat, Chambres, Groupes, Clients, POS Réception |
| Gouvernant(e) général(e) (`housekeeping_leader`) | 3 | `housekeeping_staff` | 9 | 18 | Économat, Paramètres, Housekeeping, Planning |
| Valet / Femme de chambre (`housekeeping_staff`) | 4 | — | 4 | 6 | Housekeeping |
| Responsable de restaurant (`restaurant_manager`) | 3 | `restaurant_staff`, `cashier` | 27 | 40 | Restaurant, Économat, Paramètres, Planning |
| Chef de cuisine (`restaurant_chief`) | 3 | `restaurant_cook` | 23 | 38 | Restaurant, Économat, Paramètres, Planning |
| Serveur (salle) (`restaurant_staff`) | 4 | — | 11 | 8 | Restaurant |
| Cuisinier (cuisine) (`restaurant_cook`) | 4 | — | 15 | 3 | Restaurant |
| Caissier restaurant (`cashier`) | 4 | — | 11 | 7 | Restaurant |
| Responsable boutique (`shop_manager`) | 3 | `shop_cashier` | 15 | 20 | Économat, Paramètres, Boutique, Planning |
| Vendeur-caissier (`shop_cashier`) | 4 | — | 7 | 6 | Boutique |
| Chef économe (`econome`) | 3 | `storekeeper` | 24 | 47 | Économat, Planning |
| Magasinier (`storekeeper`) | 4 | — | 17 | 5 | Économat |
| Responsable administratif et financier (`finance_manager`) | 3 | `accountant` | 32 | 25 | Comptabilité, Économat, Chambres, Planning |
| Comptable (`accountant`) | 4 | — | 32 | 21 | Comptabilité, Économat, Chambres |
| Contrôleur de gestion (`controller`) | transversal | — | 78 | 0 | — |
| Contrôleur Qualité & Audit (`quality_auditor`) | transversal | — | 61 | 0 | — |
| Support Wetchah (`support`) | transversal | — | 95 | 0 | — |

## Droit par droit

« ✓ » : le rôle détient le droit. La colonne **Nature** dit s'il consulte ou s'il agit.
Seuls les rôles qui détiennent au moins un droit du service ont une colonne.

### Chambres

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | RAF | Comptable | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `rooms.cost_sheets.assumptions` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.document` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |  | ✓ |
| `rooms.cost_sheets.export` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |  |  |
| `rooms.cost_sheets.import` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.items.creer` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.items.modifier` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.items.supprimer` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.starter` | **agit** |  | ✓ |  |  | ✓ | ✓ |  |  |  |
| `rooms.cost_sheets.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |  | ✓ |
| `rooms.creer` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.export` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ | ✓ |  |
| `rooms.images.supprimer` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.import` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.modifier` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.supprimer` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.types.creer` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.types.export` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ | ✓ |  |
| `rooms.types.import` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.types.modifier` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.types.supprimer` | **agit** | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `rooms.updateStatus` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |  |  |
| `rooms.voir` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |

### Réservations

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `bookings.approve` | **agit** |  | ✓ |  |  |  |  |  |
| `bookings.cancel` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.cancellation_receipt` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `bookings.cash_register.close` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ |
| `bookings.cash_register.close.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.cash_register.disbursements.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.cash_register.open` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ |
| `bookings.cash_register.open.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.cash_register.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `bookings.checkIn` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.checkOut` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.checkin_code.send` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.confirm` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.drafts.continue` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ |
| `bookings.drafts.resume` | consulte | ✓ | ✓ | ✓ | ✓ |  |  | ✓ |
| `bookings.drafts.save` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.drafts.supprimer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.drafts.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `bookings.folio.add` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.folio.remove` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.modifier` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.payment.add` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `bookings.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Groupes

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `groups.addRoom` | **agit** |  | ✓ |  |  |  |  |  |
| `groups.cancel` | **agit** |  | ✓ |  |  |  |  |  |
| `groups.checkInAll` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `groups.checkOutAll` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `groups.creer` | **agit** |  | ✓ |  |  |  |  |  |
| `groups.folio.add` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `groups.invoice` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |
| `groups.modifier` | **agit** |  | ✓ |  |  |  |  |  |
| `groups.payment.add` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `groups.removeRoom` | **agit** |  | ✓ |  |  |  |  |  |
| `groups.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Agenda

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `agenda.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Clients

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `customers.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `customers.export` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |
| `customers.import` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `customers.modifier` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `customers.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### POS Réception

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `reception.pos.history` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |
| `reception.pos.receipt` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |
| `reception.pos.sales.creer` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `reception.pos.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Factures

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `invoices.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Housekeeping

| Droit | Nature | Admin | Manager | Gouv. | Valet | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `housekeeping.assignments.creer` | **agit** |  | ✓ | ✓ |  |  |  |  |
| `housekeeping.available` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.clean` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.inspect` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.issue` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.ready` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.reject` | **agit** |  | ✓ | ✓ | ✓ |  |  |  |
| `housekeeping.teams.creer` | **agit** |  | ✓ | ✓ |  |  |  |  |
| `housekeeping.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Restaurant

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Resp. resto | Chef cuis. | Serveur | Cuisinier | Caissier | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `restaurant.banquets.creer` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.banquets.modifier` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.banquets.payments.creer` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.banquets.status` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.banquets.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |
| `restaurant.bar.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.billing.paid` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.billing.receipt` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ | ✓ |
| `restaurant.billing.unpaid` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.billing.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ | ✓ |
| `restaurant.breakfast.serve` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.breakfast.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `restaurant.buffets.close` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.buffets.creer` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.buffets.entries.creer` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.buffets.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ | ✓ |
| `restaurant.cash_register.close` | consulte | ✓ | ✓ |  |  | ✓ |  |  |  | ✓ |  |  | ✓ |
| `restaurant.cash_register.close.creer` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.cash_register.disbursements.creer` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.cash_register.open` | consulte | ✓ | ✓ |  |  | ✓ |  |  |  | ✓ |  |  | ✓ |
| `restaurant.cash_register.open.creer` | **agit** |  |  |  |  | ✓ |  |  |  | ✓ |  |  |  |
| `restaurant.cash_register.voir` | consulte | ✓ | ✓ |  |  | ✓ |  |  |  | ✓ | ✓ |  | ✓ |
| `restaurant.consumption.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `restaurant.kitchen.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.menus.categories.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.categories.modifier` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.categories.supprimer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.export` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |  |
| `restaurant.menus.import` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.items.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.items.modifier` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.items.supprimer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `restaurant.menus.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.orders.bar_ready` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.orders.claim` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.orders.creer` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.orders.preparing` | **agit** |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |
| `restaurant.orders.ready` | **agit** |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |
| `restaurant.orders.reassign` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.orders.send_to_kitchen` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.orders.served` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.orders.status` | **agit** |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.orders.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.pantry.categories.creer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.categories.modifier` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.categories.supprimer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.export` | consulte | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ | ✓ |  |
| `restaurant.pantry.import` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.items.creer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.items.modifier` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.items.receive` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.items.supprimer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.movements.creer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.pantry.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.recipes.creer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.recipes.export` | consulte | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ | ✓ |  |
| `restaurant.recipes.import` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.recipes.modifier` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.recipes.produce` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.recipes.supprimer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.recipes.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.restaurants.creer` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |
| `restaurant.restaurants.modifier` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |
| `restaurant.restaurants.spaces.creer` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |
| `restaurant.restaurants.spaces.modifier` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |
| `restaurant.restaurants.team.modifier` | **agit** | ✓ | ✓ |  |  | ✓ |  |  |  |  |  |  |  |
| `restaurant.restaurants.voir` | consulte | ✓ | ✓ |  |  | ✓ |  |  |  |  | ✓ | ✓ | ✓ |
| `restaurant.shifts.close` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.shifts.open` | **agit** |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |
| `restaurant.stock_counts.close` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.stock_counts.creer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.stock_counts.modifier` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.stock_counts.supprimer` | **agit** |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `restaurant.stock_counts.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ | ✓ |
| `restaurant.waste.creer` | **agit** |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |
| `restaurant.waste.voir` | consulte | ✓ | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ |

### Boutique

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Resp. bout. | Vendeur | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `shop.cash_register.close` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  |  | ✓ |
| `shop.cash_register.close.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.cash_register.disbursements.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.cash_register.open` | consulte | ✓ | ✓ |  |  | ✓ | ✓ |  |  | ✓ |
| `shop.cash_register.open.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.cash_register.voir` | consulte | ✓ | ✓ |  |  | ✓ |  | ✓ |  | ✓ |
| `shop.orders.creer` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.orders.paid` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.orders.receipt` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `shop.orders.refund` | **agit** |  |  |  |  | ✓ | ✓ |  |  |  |
| `shop.orders.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `shop.products.creer` | **agit** |  |  |  |  | ✓ |  |  |  |  |
| `shop.products.export` | consulte | ✓ | ✓ |  |  | ✓ |  | ✓ | ✓ |  |
| `shop.products.import` | **agit** |  |  |  |  | ✓ |  |  |  |  |
| `shop.products.modifier` | **agit** |  |  |  |  | ✓ |  |  |  |  |
| `shop.products.supprimer` | **agit** |  |  |  |  | ✓ |  |  |  |  |
| `shop.products.voir` | consulte | ✓ | ✓ |  |  | ✓ |  | ✓ | ✓ | ✓ |

### Économat

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Gouv. | Resp. resto | Chef cuis. | Resp. bout. | Économe | Magasinier | RAF | Comptable | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `economat.categories.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.categories.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.categories.supprimer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.categories.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.control.suggestions.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.control.suggestions.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `economat.control.variances.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `economat.control.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `economat.count_sheets.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.items.adjust` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ |  |
| `economat.items.import` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.opening` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.supprimer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.items.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.orders.cancel` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.orders.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.orders.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |  | ✓ | ✓ |  |
| `economat.orders.receive` | **agit** |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |
| `economat.orders.send` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.orders.transmit` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.orders.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.purchase_requests.approve` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `economat.purchase_requests.cancel` | **agit** |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `economat.purchase_requests.convert` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.purchase_requests.creer` | **agit** |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `economat.purchase_requests.reject` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `economat.purchase_requests.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `economat.receipts.cancel` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.receipts.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |
| `economat.receipts.direct.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.receipts.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ |  |
| `economat.receipts.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.requisitions.approve` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.requisitions.cancel` | **agit** |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `economat.requisitions.creer` | **agit** |  |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  | ✓ | ✓ |  |  |  |
| `economat.requisitions.deliver` | **agit** |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |
| `economat.requisitions.export` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  | ✓ | ✓ |  |
| `economat.requisitions.reject` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.requisitions.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `economat.stock_counts.cancel` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stock_counts.close` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stock_counts.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stock_counts.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |
| `economat.stock_counts.report` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |  | ✓ | ✓ | ✓ |
| `economat.stock_counts.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.stores.counts.cancel` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.counts.close` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.counts.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.counts.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |
| `economat.stores.counts.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.stores.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.supprimer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.stores.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.suppliers.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.suppliers.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.suppliers.supprimer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.suppliers.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |
| `economat.units.creer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.units.modifier` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.units.supprimer` | **agit** |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `economat.voir` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ | ✓ | ✓ |

### Comptabilité

| Droit | Nature | Admin | Manager | RAF | Comptable | Contrôleur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|
| `accounting.cash` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.cash_reviews` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.cash_reviews.creer` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.expenses` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.expenses.creer` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.expenses.modifier` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.expenses.supprimer` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.income_statement` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.journal` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.accounts` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.aged` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.analytic` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.analytic.margins` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.analytic.mirror` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.auxiliary` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.auxiliary.ledger` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.balance` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.entry` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.entry.reverse` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.general` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.journals` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.night_audit` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.night_audit.run` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.opening` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.opening.creer` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.periods` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.periods.lock` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.reconcile` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.reconcile.auto` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.reconcile.undo` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.suppliers` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.suppliers.creer` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.ledger.suppliers.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `accounting.ledger.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `accounting.ledger.withholding` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.ledger.years.open` | **agit** |  |  | ✓ | ✓ |  |  |
| `accounting.receivables` | consulte | ✓ | ✓ | ✓ | ✓ |  | ✓ |
| `accounting.revenue_journal` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `accounting.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Analytique

| Droit | Nature | Admin | Manager | Contrôleur | Support |
|---|---|:-:|:-:|:-:|:-:|
| `analytics.voir` | consulte | ✓ | ✓ | ✓ | ✓ |

### Éditions

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Gouv. | Valet | Resp. resto | Chef cuis. | Serveur | Cuisinier | Caissier | Resp. bout. | Vendeur | Économe | Magasinier | RAF | Comptable | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `editions.export` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |
| `editions.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Planning

| Droit | Nature | Admin | Manager | Chef réc. | Récep. | Gouv. | Valet | Resp. resto | Chef cuis. | Serveur | Cuisinier | Caissier | Resp. bout. | Vendeur | Économe | Magasinier | RAF | Comptable | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `planning.affectations.creer` | **agit** |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ |  |  |  |  |
| `planning.affectations.supprimer` | **agit** |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ |  |  |  |  |
| `planning.publier` | **agit** |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ |  |  |  |  |
| `planning.recopier` | **agit** |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ |  |  |  | ✓ |  | ✓ |  | ✓ |  |  |  |  |
| `planning.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### Paramètres

| Droit | Nature | Admin | Manager | Chef réc. | Gouv. | Resp. resto | Chef cuis. | Resp. bout. | Économe | Contrôleur | Auditeur | Support |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `settings.cancellation_policies.creer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.cancellation_policies.default` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.cancellation_policies.modifier` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.cancellation_policies.supprimer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.export` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  | ✓ |  |  |
| `settings.import` | **agit** |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |
| `settings.modifier` | **agit** | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |
| `settings.packages.creer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.packages.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |
| `settings.packages.import` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.packages.modifier` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.packages.supprimer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.partners.creer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.partners.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |  |
| `settings.partners.import` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.partners.modifier` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.partners.supprimer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.quarts.creer` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.quarts.modifier` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.quarts.supprimer` | **agit** | ✓ | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.services.creer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.services.export` | consulte | ✓ | ✓ |  |  |  |  |  |  | ✓ | ✓ |  |
| `settings.services.import` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.services.modifier` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.services.supprimer` | **agit** |  | ✓ |  |  |  |  |  |  |  |  |  |
| `settings.voir` | consulte | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  | ✓ |

### Utilisateurs

| Droit | Nature | Admin | Manager | Contrôleur | Support |
|---|---|:-:|:-:|:-:|:-:|
| `users.creer` | **agit** | ✓ | ✓ |  |  |
| `users.modifier` | **agit** | ✓ | ✓ |  |  |
| `users.resetPassword` | **agit** | ✓ | ✓ |  |  |
| `users.toggleStatus` | **agit** | ✓ | ✓ |  |  |
| `users.voir` | consulte | ✓ | ✓ | ✓ | ✓ |

### Rôles & droits

| Droit | Nature | Admin | Contrôleur | Support |
|---|---|:-:|:-:|:-:|
| `droits.apercu` | **agit** | ✓ |  |  |
| `droits.exceptions.creer` | **agit** | ✓ |  |  |
| `droits.exceptions.supprimer` | **agit** | ✓ |  |  |
| `droits.modifier` | **agit** | ✓ |  |  |
| `droits.voir` | consulte | ✓ | ✓ | ✓ |

### Interventions

| Droit | Nature | Admin | Manager | Support |
|---|---|:-:|:-:|:-:|
| `interventions.creer` | **agit** | ✓ |  |  |
| `interventions.terminer` | **agit** | ✓ |  |  |
| `interventions.voir` | consulte | ✓ | ✓ | ✓ |

### Journal d'audit

| Droit | Nature | Admin | Contrôleur | Support |
|---|---|:-:|:-:|:-:|
| `audit.voir` | consulte | ✓ | ✓ | ✓ |

### Support

| Droit | Nature | Admin | Manager | Support |
|---|---|:-:|:-:|:-:|
| `support.sessions.voir` | consulte | ✓ | ✓ | ✓ |
