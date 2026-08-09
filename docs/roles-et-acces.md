# Rôles et accès

Le contrôle d'accès de l'application se joue sur **quatre niveaux successifs**, du
plus large au plus fin :

```
1. Le module est-il activé pour cet établissement ?     module:restaurant
2. L'utilisateur a-t-il un rôle autorisé ?              role:restaurant_chief,manager
3. A-t-il le droit d'écrire, ou seulement de lire ?     module.access:restaurant
4. Sa caisse est-elle ouverte ?                         caisse
```

Une route peut porter les quatre. Chacun répond à une question différente, et aucun
ne remplace les autres.

## Le catalogue des rôles

[`App\Support\RoleCatalog`](../app/Support/RoleCatalog.php) est la **source unique de
vérité**. La rubrique Utilisateurs lit la table `roles`, jamais une liste codée en
dur.

| Rôle | Module | Assignable | Périmètre |
|---|---|---|---|
| `admin` | direction | ❌ | Accès complet, y compris l'administration |
| `manager` | direction | ❌ | Pilotage complet de l'établissement |
| `customer_guest` | portail | ❌ | Accès client au portail |
| `reception` | hebergement | ✅ | Accueil, réservations, arrivées et départs |
| `cashier` | hebergement | ✅ | Encaissements et facturation |
| `housekeeping_leader` | housekeeping | ✅ | Supervision du service ménage |
| `housekeeping_staff` | housekeeping | ✅ | Personnel de ménage |
| `restaurant_chief` | restaurant | ✅ | Cuisine et restaurant, carte, fiches techniques |
| `restaurant_staff` | restaurant | ✅ | Service en salle |
| `restaurant_cook` | restaurant | ✅ | Cuisine : bons et plats prêts |
| `shop_manager` | boutique | ✅ | Catalogue et stocks boutique |
| `shop_cashier` | boutique | ✅ | Ventes et encaissements boutique |
| `econome` | economat | ✅ | Magasin central, fournisseurs, achats |
| `accountant` | comptabilite | ✅ | Comptabilité et rapports financiers |

> **`is_assignable: false` signifie qu'un manager ne peut pas attribuer ce rôle**
> depuis la rubrique staff. Les rôles privilégiés (`admin`, `manager`) ne se
> distribuent pas depuis l'interface courante.

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

## Niveau 2 — Le rôle

`role:manager,reception` — [`EnsureRoleAccess`](../app/Http/Middleware/EnsureRoleAccess.php).

En cas de refus, le middleware journalise l'incident et répond selon le contexte :
JSON `403` avec `access_denied: true` pour une requête AJAX, sinon redirection avec
un message affiché en popup.

### Deux systèmes de rôles

`hasRole()` interroge d'abord la relation `roles` (table pivot), puis retombe sur la
colonne `role` héritée.

> Ce double mécanisme est intentionnel et doit être préservé : des comptes anciens
> n'existent que dans la colonne, et `Notifier` résout ses destinataires en tenant
> compte des deux.

## Niveau 3 — Lecture ou écriture

`module.access:restaurant` — [`EnsureModuleWriteAccess`](../app/Http/Middleware/EnsureModuleWriteAccess.php).

Le pivot `role_user` porte un **niveau** (`read` ou `write`) par rôle, donc par
module. Un utilisateur en lecture seule peut consulter les pages (GET) mais pas agir
(POST/PUT/PATCH/DELETE).

Trois règles gouvernent la résolution :

- **La direction n'est jamais restreinte.** `admin` et `manager` passent toujours.
- **L'absence de marqueur vaut écriture.** Un pivot vide — comptes créés avant cette
  fonctionnalité — est traité comme `write`. La lecture seule est une restriction
  qu'on **active volontairement**, jamais un défaut hérité.
- **Le plus permissif l'emporte.** Un utilisateur avec deux rôles sur le même module,
  l'un en lecture et l'autre en écriture, peut écrire.

## Niveau 4 — Le verrou de caisse

`caisse` — [`EnsureCashRegisterOpen`](../app/Http/Middleware/EnsureCashRegisterOpen.php).

Aucune action métier sur une réservation — modification, arrivée, départ,
encaissement, ligne de folio — n'est possible tant que l'utilisateur n'a pas
**ouvert sa caisse**.

> Ce n'est pas un simple masquage de boutons : le middleware bloque aussi un POST
> direct hors interface. C'est ce qui garantit que toute opération d'argent est
> rattachable à une session de caisse nominative.

Voir [Comptabilité](comptabilite.md).

## Deux entrées d'authentification

| Route | Public | Vue |
|---|---|---|
| `/login` | Le personnel de l'établissement | `auth/login` |
| `/admin` | L'administrateur de l'établissement | `admin/auth/login` |

S'y ajoute `/assistance/enter`, sans authentification préalable : l'entrée du
technicien de la console, dont la confiance repose entièrement sur la signature du
jeton. Voir [APIs et intégrations](apis-et-integrations.md#mode-assistance).

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

### Qui ouvre la caisse la ferme

La fermeture de caisse n'a **pas** de restriction de rôle supplémentaire : le
contrôleur borne la session à `auth()->id()`. Chacun ferme la sienne, personne ne
ferme celle d'un autre.

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
