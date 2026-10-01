# Mode POS reception - Plan d'implementation

## Objectif

Ajouter un espace de travail **Mode POS reception** pour les utilisateurs dont la
tache principale est la gestion de l'hebergement. Cet espace est une interface
plein ecran, sans barre laterale, centree sur :

- la lecture et la gestion de l'agenda des reservations ;
- la creation de reservations ;
- les actions de sejour autorisees par le role de l'utilisateur ;
- l'ouverture, le suivi et la cloture de la caisse de reception.

Le Mode POS ne cree pas de nouvelles regles metier. Il reutilise les services,
controleurs et contraintes existants afin que le comportement reste identique
depuis le PMS classique et depuis l'interface POS.

## Perimetre fonctionnel

### Utilisateurs autorises

- `reception` : acces complet aux actions de reception deja autorisees.
- `manager` : meme acces, pour supervision et remplacement.
- Les autres roles : acces refuse, meme en saisissant l'URL directement.

Les droits du mode sont ceux du catalogue (`reception.pos.*`), verifies par le
middleware `permission` : une restriction de module posee sur la personne
(hebergement exclu ou en lecture seule) s'y applique aussi.

### Ecran principal

L'URL initiale sera `GET /reception/pos`. A son ouverture, l'utilisateur arrive
directement sur l'agenda, en pleine largeur et pleine hauteur utile de la fenetre.
L'interface ne contient pas de sidebar.

La barre superieure fixe contient :

- le nom et le logo de l'etablissement ;
- l'identite de l'utilisateur connecte et l'heure courante ;
- un bouton `Nouvelle reservation` ;
- l'etat de la caisse et son solde especes en cours ;
- un bouton contextuel `Ouvrir la caisse` ou `Cloturer la caisse` ;
- un bouton `Quitter le mode POS`, qui revient a l'agenda standard.

L'agenda reprend les capacites de l'ecran existant : vues jour, semaine et mois,
navigation temporelle, recherche, filtres de statut et acces a la fiche d'une
reservation. Un clic sur une case libre propose une nouvelle reservation avec la
date d'arrivee pre-remplie.

### Reservations et contexte POS

Le parcours de reservation existant reste la source unique de verite : client,
sejour, disponibilite, choix de chambre, tarification, confirmation et paiements.
Le POS transmet simplement un contexte `pos` sur les liens et formulaires du
wizard.

Ce contexte permet de :

- afficher les pages du wizard avec le layout POS ;
- conserver les dates choisies dans le calendrier ;
- revenir a `/reception/pos` apres creation, annulation ou consultation ;
- ne jamais changer les validations ou la logique de disponibilite.

Il ne doit pas etre stocke comme une preference utilisateur globale : quitter le
POS ramene immediatement au layout normal. La conservation se fait uniquement dans
les parametres de navigation du parcours en cours et dans des champs caches
verifies par le serveur.

### Caisse et solde visible

Le POS utilise les sessions `CashRegisterSession` de module `reception` deja
existantes. Une session est personnelle : un receptionniste ne peut ouvrir,
reprendre ou cloturer que sa propre caisse de reception.

Le solde affiche est le **solde theorique d'especes**, en centimes dans le code et
en FCFA formates dans l'interface :

```
solde especes = fond d'ouverture
               + encaissements cash termines
               - remboursements cash
               - decaissements
```

Exemple attendu : fond d'ouverture `100 000 FCFA`, puis un paiement especes de
`50 000 FCFA` donne un solde de `150 000 FCFA`.

Les paiements carte, virement et Mobile Money sont affiches dans un detail de
jour, mais ne modifient pas le solde d'especes physique. Cette distinction evite
de confondre chiffre encaisse et argent reellement disponible dans le tiroir-caisse.

Quand aucune caisse n'est ouverte, le calendrier reste consultable et une
reservation peut etre preparee. Les actions qui produisent un mouvement de caisse
(paiement, remboursement, check-in/check-out ou modification de folio selon les
regles actuelles) restent protegees par le middleware `caisse` et proposent
l'ouverture de caisse.

## Architecture cible

### Routes

Ajouter un groupe protege, dans `routes/web.php` :

```php
Route::prefix('reception/pos')
    ->name('reception.pos.')
    ->middleware('permission')
    ->group(function () {
        Route::get('/', [ReceptionPosController::class, 'index'])->name('index');
        Route::get('/cash-summary', [ReceptionPosController::class, 'cashSummary'])->name('cash-summary');
    });
```

Les routes existantes de reservation et de caisse ne sont pas remplacees. Elles
acceptent un contexte POS strictement valide, afin de choisir le layout et la
redirection de retour sans ouvrir de redirection arbitraire.

### Controleur POS

Creer `app/Http/Controllers/Reception/ReceptionPosController.php`.

Responsabilites :

- charger l'agenda en reutilisant une methode extraite de `BookingController` ;
- charger la session de caisse ouverte de l'utilisateur ;
- obtenir son resume via `CashRegisterBalanceService` ;
- rendre la vue POS ;
- retourner le meme resume au format JSON pour le rafraichissement leger de
  l'interface.

La preparation des donnees de calendrier doit etre extraite dans un service ou une
classe de presentation partagee. `BookingController::agenda()` et le controleur
POS l'utiliseront tous les deux. Il ne faut pas appeler un controleur depuis un
autre controleur ni dupliquer `agendaBookings()`.

### Service de solde de caisse

Creer `app/Services/CashRegisterBalanceService.php` avec une methode telle que
`summaryFor(CashRegisterSession $session): CashRegisterBalance`.

Le service retourne au minimum :

- `openingAmount` ;
- `cashPaymentsTotal` ;
- `cashRefundsTotal` ;
- `disbursementsTotal` ;
- `theoreticalCashAmount` ;
- les totaux non especes, groupes par methode de paiement.

Le calcul actuellement present dans
`Reception/CashRegisterController::showCloseForm()` doit deleguer a ce service.
Ainsi, le POS et l'ecran de cloture affichent toujours le meme montant.

### Layout et vues

Ajouter :

- `resources/views/layouts/pos.blade.php` : shell autonome, sans sidebar ;
- `resources/views/reception/pos/index.blade.php` : calendrier, actions et
  widgets de caisse ;
- un partial ou composant Blade pour le calendrier partage avec
  `resources/views/bookings/agenda.blade.php` ;
- des modales POS pour ouverture de caisse, cloture et detail du solde.

Le layout POS conserve les assets Vite, les notifications, le token CSRF,
l'accessibilite clavier et les scripts Alpine deja utilises par l'application.

### Rafraichissement du solde

Au chargement, le solde est rendu par le serveur. Il est ensuite mis a jour :

1. immediatement apres une ouverture, un paiement, un remboursement ou un
   decaissement effectue dans le POS ;
2. par un appel `GET /reception/pos/cash-summary` toutes les 30 secondes ;
3. lorsque la fenetre redevient visible (`visibilitychange`).

Cette premiere version ne requiert ni WebSocket ni infrastructure temps reel. Le
polling court couvre les modifications realisees dans un autre onglet, tout en
gardant une charge negligeable.

## Regles d'integrite et de securite

- Tous les montants restent des entiers en centimes dans la base et dans les
  services. La conversion depuis les champs de formulaire doit etre centralisee
  afin d'eviter les erreurs d'arrondi.
- Le resume ne lit que les paiements rattaches a `cash_register_session_id` de la
  session ouverte de l'utilisateur courant.
- Une session cloturee est immuable pour les mouvements de caisse ; son solde reste
  consultable dans l'historique existant.
- L'ouverture de caisse doit etre protegee contre deux requetes simultanees. Le
  controle applicatif existant est conserve et renforce par une transaction ou une
  contrainte adaptee a PostgreSQL pour empecher deux sessions ouvertes du meme
  utilisateur et du meme module.
- Les redirections de retour POS sont une liste blanche de routes internes, jamais
  une URL fournie directement par le navigateur.
- Les actions deja conditionnees par les droits et le middleware `caisse` ne sont
  pas contournees par le nouveau layout.

## Etapes d'implementation

### Phase 1 - Socle et acces

1. Ajouter les routes et `ReceptionPosController`.
2. Ajouter le layout POS minimal et le lien d'entree depuis l'agenda et le
   dashboard.
3. Verifier les roles, le module hebergement et le retour vers l'agenda normal.

**Critere d'acceptation :** un receptionniste autorise arrive sur un ecran sans
sidebar ; un utilisateur non autorise obtient un refus d'acces.

### Phase 2 - Agenda partage

1. Extraire le chargement et la presentation des donnees de calendrier.
2. Extraire le rendu Alpine/Blade reutilisable depuis `bookings/agenda.blade.php`.
3. Ajouter l'action `Nouvelle reservation` et le clic sur une date libre.

**Critere d'acceptation :** l'agenda standard et le POS affichent les memes
reservations, statuts et couleurs pour une periode donnee.

### Phase 3 - Parcours de reservation en focus

1. Faire propager le contexte POS dans les etapes de creation, selection de
   chambre, confirmation et fiches de reservation.
2. Choisir le layout POS lorsque le contexte est valide.
3. Rediriger vers le calendrier POS apres une action terminee.

**Critere d'acceptation :** une reservation creee depuis le POS respecte les memes
validations et apparait immediatement dans le calendrier sans retour impose vers la
sidebar classique.

### Phase 4 - Caisse et resume permanent

1. Introduire `CashRegisterBalanceService` et remplacer le calcul du formulaire
   de cloture par ce service.
2. Ajouter les widgets/modales d'ouverture et de cloture dans le POS.
3. Exposer le resume JSON authentifie et ajouter le rafraichissement Alpine.
4. Afficher explicitement les especes et les autres moyens de paiement.

**Critere d'acceptation :** une ouverture a `100 000`, suivie d'un paiement cash
de `50 000`, affiche `150 000 FCFA` sans ambiguite ; un paiement non cash ne change
pas ce montant.

### Phase 5 - Tests et finition

1. Ajouter des tests Pest de droits, module, rendu du POS et contexte de retour.
2. Ajouter des tests unitaires du service de solde pour les encaissements,
   remboursements, decaissements et paiements non cash.
3. Ajouter des tests HTTP pour l'ouverture et la cloture depuis le POS.
4. Verifier le comportement clavier, mobile/tablette, faible largeur et contraste.
5. Executer la suite ciblee, puis `php artisan test` et `./vendor/bin/pint --test`.

## Fichiers concernes

| Action | Fichier ou emplacement |
|---|---|
| Modifier | `routes/web.php` |
| Creer | `app/Http/Controllers/Reception/ReceptionPosController.php` |
| Creer | `app/Services/CashRegisterBalanceService.php` |
| Modifier | `app/Http/Controllers/BookingController.php` |
| Modifier | `app/Http/Controllers/Reception/CashRegisterController.php` |
| Creer | `resources/views/layouts/pos.blade.php` |
| Creer | `resources/views/reception/pos/index.blade.php` |
| Modifier / extraire | `resources/views/bookings/agenda.blade.php` |
| Modifier | vues du wizard de reservation et de caisse concernees |
| Creer | tests Pest POS et solde de caisse |

## Hors perimetre initial

- Remplacer le PMS standard par le POS pour tous les utilisateurs.
- Creer un nouveau moteur de disponibilite, de reservation ou de tarification.
- Ajouter une synchronisation temps reel par WebSocket.
- Melanger les caisses reception, restaurant et boutique.
- Modifier les regles comptables ou l'historique de caisse deja en production.

## Decisions a valider avant implementation

1. Les paiements Mobile Money doivent-ils rester hors du solde d'especes, comme
   recommande, ou etre inclus dans un indicateur global distinct ?
2. Le bouton POS doit-il etre visible pour les managers, ou reserve strictement au
   role `reception` ?
3. La creation de reservation doit-elle etre possible avant ouverture de caisse,
   comme le permet le flux actuel, ou faut-il rendre l'ouverture obligatoire des
   l'entree dans le POS ?
