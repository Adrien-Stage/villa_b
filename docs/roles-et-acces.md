# Rôles et accès

Le contrôle d'accès de l'application se joue sur **trois niveaux successifs**, du
plus large au plus fin :

```
1. Le module est-il activé pour cet établissement ?     module:restaurant
2. L'utilisateur détient-il le droit ?                   permission
3. Sa caisse est-elle ouverte ?                          caisse
```

Une route peut porter les trois. Chacun répond à une question différente, et aucun
ne remplace les autres. La lecture seule n'est plus un verrou à part : c'est une
question de droits, posée au même moteur que le reste (niveau 2).

## Le catalogue des rôles

[`App\Support\RoleCatalog`](../app/Support/RoleCatalog.php) est la **source unique de
vérité**. La rubrique Utilisateurs lit la table `roles`, jamais une liste codée en
dur.

La hiérarchie suit celle d'un hôtel. Un chef **inclut** ses membres : il détient
leurs droits sans qu'on les recopie, et porte aussi leurs incompatibilités.

| Niveau | Rôle | Service | Inclut | Statut |
|---|---|---|---|---|
| 1 | `admin` — Administrateur | informatique | — | actif, créé depuis la console |
| 2 | `manager` — Manager | direction | — | actif, privilégié |
| 3 | `reception_chief` — Chef de réception | hébergement | `reception` | actif |
| 3 | `housekeeping_leader` — Gouvernant(e) général(e) | housekeeping | `housekeeping_staff` | actif |
| 3 | `restaurant_manager` — Responsable de restaurant | restaurant | `restaurant_staff`, `cashier` | actif |
| 3 | `restaurant_chief` — Chef de cuisine (cuisine seule) | restaurant | `restaurant_cook` | actif |
| 3 | `shop_manager` — Responsable boutique | boutique | `shop_cashier` | actif |
| 3 | `econome` — Chef économe | économat | `storekeeper` | actif |
| 3 | `finance_manager` — Responsable administratif et financier | comptabilité | `accountant` | actif |
| 4 | `reception` — Réceptionniste (encaisse) | hébergement | — | actif |
| 4 | `housekeeping_staff` — Valet / Femme de chambre | housekeeping | — | actif |
| 4 | `restaurant_staff` — Serveur, `restaurant_cook` — Cuisinier | restaurant | — | actif |
| 4 | `cashier` — Caissier restaurant | restaurant | — | actif |
| 4 | `shop_cashier` — Vendeur-caissier | boutique | — | actif |
| 4 | `storekeeper` — Magasinier | économat | — | actif |
| 4 | `accountant` — Comptable | comptabilité | — | actif |
| — | `controller`, `quality_auditor` — contrôle, lecture seule | contrôle | — | actif |
| — | `customer_guest` — portail client | portail | — | actif, privilégié |
| — | `support` — Support Wetchah, compte technique du mode assistance | informatique | — | actif, privilégié |
| — | `rh_manager`, `it_support` | — | — | retirés |

- **L'administrateur consulte tout et n'écrit rien de métier, hors intervention
  déclarée** (voir [Le mode intervention](#le-mode-intervention)). Il administre
  l'application — configuration et **comptes du personnel**, managers compris — ; il
  ne tient aucun service. Il ne se cumule avec aucun autre rôle. Ses propres comptes
  ne se créent que depuis la console d'orchestration
  (`POST /api/comptes/administrateurs`) : personne, dans l'établissement, n'accorde un
  niveau égal au sien. Tant qu'il n'y en a pas, le manager gère les comptes du
  personnel (hors managers).
- **Il n'y a pas de caissier à l'hébergement** : le réceptionniste encaisse. Le slug
  historique `cashier` désigne le caissier du restaurant.
- **En préparation** : statut d'un rôle défini, droits compris, mais pas encore
  proposé. Aucun rôle n'y est aujourd'hui : écrans et contrôleurs posent des droits,
  ou la fonction exercée (`User::exerce()`), plus des rôles nommés.
- **Retiré** : plus proposé ; les RH deviennent une plateforme sœur, et
  l'administrateur appartient déjà au service informatique.

> **`is_assignable: false` signifie que le rôle ne se distribue pas depuis la
> rubrique Utilisateurs** : rôles privilégiés (`admin`, `manager`), rôles en
> préparation et rôles retirés. Niveau, inclusions et statut vivent dans le code ;
> la table `roles` ne porte que les colonnes lues par les écrans.

### Ajouter un rôle

Il suffit de l'ajouter au catalogue. `RoleCatalog::sync()` est rejoué par
`roles:sync` à **chaque démarrage** du conteneur, y compris sur un établissement
déjà en service.

L'opération est idempotente : aucun doublon, et **le pivot `role_user` n'est jamais
touché** — les rattachements existants survivent.

```bash
php artisan roles:sync
```

> C'est la seule donnée de référence qui se propage automatiquement. Les seeders
> d'installation, eux, ne sont jamais rejoués — voir
> [Architecture — l'entrypoint](architecture.md#lentrypoint).

## Niveau 1 — Le module est-il activé ?

`module:restaurant` refuse l'accès si le module n'est pas dans `TENANT_MODULES`.

Cela bloque **l'accès direct par URL**, pas seulement l'affichage du lien. Un
établissement sans restaurant renvoie 403 sur `/restaurant/menus`, même à un
manager.

## Niveau 2 — Le droit

`permission` — [`EnsurePermission`](../app/Http/Middleware/EnsurePermission.php), qui
interroge [`PermissionResolver`](../app/Services/PermissionResolver.php) : le droit
se déduit du nom de la route (`economat.items.store` → `economat.items.creer`). Les
écrans posent la même question avec `@droit(...)`, les services avec `allows()`.

Le moteur décide dans cet ordre :

1. une **restriction de module** posée sur la personne depuis la console —
   exclusion, ou lecture seule — l'emporte sur tout ;
2. un **refus** explicite, sur la personne ou sur l'un de ses rôles ;
3. une **autorisation** explicite, sur la personne ou sur l'un de ses rôles ;
4. le **catalogue** ([`PermissionCatalog`](../app/Support/PermissionCatalog.php)),
   hiérarchie comprise.

En cas de refus, l'incident est journalisé ; la réponse est un JSON `403` avec
`access_denied: true` pour une requête AJAX, sinon une redirection avec un message
affiché en popup.

### Les couches d'écarts

Les écarts au catalogue (`permission_grants`) portent leur **origine** :

| Couche | Origine | Qui la règle |
|---|---|---|
| Console | `erp` | La console d'orchestration, rôle par rôle (« Droits & rôles ») |
| Hôtel | `etablissement` | L'établissement, sur ses rôles |
| Exceptions nominatives | `etablissement` | L'établissement, sur une personne, avec échéance possible (`expires_at`) |

La console ne remplace jamais que **sa** couche (`PUT /api/permissions/matrice`).
Elle ne règle ni l'administrateur, ni le portail client, ni un rôle retiré. Une
autorisation qui ferait exercer à un rôle une fonction incompatible avec la sienne
(`DutySegregation::conflitsDUneAutorisation`) exige une **dérogation motivée**,
tracée au journal. Voir [APIs et intégrations](apis-et-integrations.md#api-dorchestration).

Une **portée** (`propre`, `departement`, `etablissement`) borne les données d'un
droit là où un écran l'applique (`DepartmentScoping`) : la liste
`PermissionScope::DROITS_BORNES`, gardée alignée sur le code par un test, dit
lesquels.

### Les affectations font foi

Les rôles d'une personne sont ses **affectations** (table pivot `role_user`). La
colonne héritée `users.role` ne compte que pour un compte **sans aucune
affectation** : la console remplace les affectations sans toucher la colonne, qui
garderait sinon un rôle retiré — et ses droits. `hasRole()`, `hasAnyRole()`,
`exerce()`, le moteur de droits et `Notifier` suivent la même règle
(`User::rolesDetenus()`).

### Reprise des comptes et revue

Une migration ([`RepriseDesRoles`](../app/Support/RepriseDesRoles.php)) a repris les
comptes existants, sans retirer de droit :

- un compte sans affectation reçoit son rôle hérité en affectation (l'ancien
  `housekeeping` devient `housekeeping_staff`, aux mêmes droits) ;
- une colonne périmée est réalignée sur le rôle principal des affectations ;
- la salle et la caisse passent du chef de cuisine au responsable de restaurant :
  chaque chef de cuisine reçoit aussi ce rôle, pour ne rien perdre.

Ce qui reste à trancher s'affiche avec :

```bash
php artisan roles:revue
```

Cumuls cuisine et salle à confirmer, rôles retirés, cumuls interdits, comptes sans
rôle, absence de comptable (les caisses attendraient leur contrôle) ou
d'administrateur. La commande ne modifie rien.

### La lecture seule

Le pivot `role_user` porte un **niveau** (`read` ou `write`) par rôle. Un rôle
affecté en lecture seule ne donne que ses **droits de consultation** — ceux du
gabarit comme ceux qu'une autorisation pose sur ce rôle.

- **L'absence de marqueur vaut écriture.** Un pivot sans niveau — comptes créés
  avant cette fonctionnalité — est traité comme `write`.
- **La colonne héritée ne contourne pas l'affectation.** `users.role` ne compte, pour
  écrire, que pour un compte sans aucune affectation.
- **Un autre rôle en écriture peut donner le droit.** Le refus vient de l'absence de
  rôle en écriture qui le porte, pas d'un verrou sur le module.

## Niveau 3 — Le verrou de caisse

`caisse` — [`EnsureCashRegisterOpen`](../app/Http/Middleware/EnsureCashRegisterOpen.php).

Aucune action métier sur une réservation — modification, arrivée, départ,
encaissement, ligne de folio — n'est possible tant que l'utilisateur n'a pas
**ouvert sa caisse**.

> Ce n'est pas un simple masquage de boutons : le middleware bloque aussi un POST
> direct hors interface. C'est ce qui garantit que toute opération d'argent est
> rattachable à une session de caisse nominative.

Voir [Comptabilité](comptabilite.md).

## Entrées d'authentification

| Route | Public | Vue |
|---|---|---|
| `/login` | Tout le personnel, administrateur compris | `auth/login` |
| `/assistance/enter` | Le support de l'éditeur, par jeton signé | — |

L'ancienne console « admin global » (`/admin`) est retirée : reste de l'époque
multi-établissements, elle gérait des établissements depuis l'intérieur de l'un
d'eux. L'administrateur se connecte comme tout le monde.

Le support entre sous un **compte technique distinct**, « Support Wetchah » (rôle
`support`), créé au premier passage. Ce compte **consulte sans écrire** (aucune
exportation non plus), ne s'ouvre jamais par mot de passe — la page de connexion le
refuse —, et chacune de ses sessions est enregistrée (`support_sessions`) : l'hôtel
les voit dans *Administration → Sessions du support*. Voir
[APIs et intégrations](apis-et-integrations.md#mode-assistance).

## Administration

Section de la barre latérale, chaque lien posant son droit :

| Écran | Droit | Qui |
|---|---|---|
| **Rôles & droits** (`/droits`) | `droits.voir`, `droits.modifier`, `droits.exceptions.*` | L'administrateur règle ; le contrôle de gestion consulte |
| **Interventions** (`/interventions`) | `interventions.voir`, `interventions.creer` | L'administrateur ouvre ; le manager suit |
| **Journal d'audit** (`/journal-audit`) | `audit.voir` | L'administrateur, le contrôle de gestion |
| **Sessions du support** (`/support/sessions`) | `support.sessions.voir` | L'administrateur, le manager |

### Rôles & droits

La même grille que la matrice de la console, **limitée à la couche de l'hôtel**
(origine `etablissement`) : le modèle et la couche de la console s'y lisent (badge
**C**), sans s'y modifier. Mêmes garde-fous que la console — aperçu obligatoire,
motif, alerte et dérogation motivée sur un cumul, refus d'un écran périmé — car la
règle vit à un seul endroit ([`PermissionMatrix`](../app/Services/PermissionMatrix.php)).

Les **exceptions nominatives** s'y posent aussi : un droit accordé ou refusé à une
personne, motivé, avec échéance possible. Une autorisation qui ferait cumuler des
fonctions incompatibles exige une dérogation. Aucune exception ne vise
l'administrateur ni le support : leurs droits sont fixés par construction.

La **fiche d'un employé** (`/users/{id}`) montre ses rôles et leur niveau, ses cumuls
éventuels, son périmètre, ses exceptions et restrictions, et ce que le moteur lui
accorde réellement, module par module.

### Le mode intervention

L'administrateur consulte tout et n'écrit que la configuration (chambres, onglet
Général des paramètres) et les comptes. **Encaisser, valider, comptabiliser : il ne
le fait que pendant une intervention** qu'il déclare — motif, durée (15 min à 4 h),
services concernés ([`Intervention::PERIMETRES`](../app/Models/Intervention.php)).

- Le moteur de droits lui ouvre alors l'écriture dans ces services, et eux seuls,
  jusqu'à la fin (étape 5 de `PermissionResolver`).
- Un bandeau reste visible tant qu'elle dure — pour lui, qui peut la terminer, et
  pour la direction.
- Le manager est notifié à l'ouverture et à la clôture.
- Chaque action porte la référence de l'intervention dans le journal d'audit.
- La trace part à la console d'orchestration (`ERP_API_URL`). Console injoignable :
  l'intervention a lieu quand même, la trace est rejouée toutes les cinq minutes
  (`interventions:transmettre`) et reste marquée **tardive**.
- Une intervention arrivée au bout de sa durée est close automatiquement.

## Cas notables

Quelques arbitrages du fichier de routes qui surprennent à la lecture mais sont
délibérés.

### Le manager peut lire, pas écrire

Sur le restaurant et la boutique, le manager figure dans les groupes de **lecture**
et en est **explicitement exclu** en écriture :

```php
// Lecture — le manager peut consulter
->middleware(['role:manager,restaurant_chief,cashier', …])

// Écriture — manager exclu
->middleware(['role:restaurant_chief,cashier', …])
```

Le manager supervise et contrôle ; il ne saisit pas les commandes ni les
encaissements à la place de ses équipes. Cette séparation est ce qui rend le
contrôle crédible.

### Qui ouvre la caisse la compte, la comptabilité la clôt

Le comptage n'a **pas** de restriction de rôle supplémentaire : le contrôleur borne
la session à `auth()->id()`. Chacun compte la sienne, personne ne compte celle d'un
autre. La caisse cesse alors d'encaisser et attend la **contresignature de la
comptabilité** (comptable ou responsable administratif et financier), seule à
constater l'écart. Ce n'est pas un réglage : ni l'établissement ni le manager ne
peuvent en dispenser.

### Les demandes à l'économat sont ouvertes

Les routes de consultation et de création de demandes acceptent tous les
responsables de département — `reception`, `housekeeping_leader`,
`restaurant_chief`, `shop_manager` — en plus de l'`econome`. Le contrôleur cloisonne
ensuite chacun à ses propres demandes.

La **gestion** du magasin (articles, fournisseurs, bons, validation) reste réservée
à l'économe.

### Le housekeeping n'a pas accès aux montants

`canAccessFinancialData()` liste explicitement les rôles autorisés à voir les
données financières. Le personnel de ménage en est absent : il voit les chambres et
leur état, jamais les tarifs ni les soldes.

## Pour aller plus loin

- [Architecture](architecture.md) — modules et catalogue
- [Comptabilité](comptabilite.md) — sessions de caisse
- [Hébergement](hebergement.md) — le cycle d'un séjour
