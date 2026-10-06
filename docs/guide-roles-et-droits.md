# Guide des rôles et des droits

**Wetchah — l'application de l'hôtel et la console d'orchestration**

Ce guide explique qui peut faire quoi, pourquoi, et comment le régler. Il s'adresse
à trois lecteurs :

- la **direction de l'hôtel** (manager), qui veut comprendre ce que chacun peut faire ;
- l'**administrateur de l'hôtel** (son service informatique), qui crée les comptes et
  règle les droits ;
- l'**équipe Wetchah** (administrateur technique de la console) et les
  **propriétaires**, qui règlent les droits depuis la console.

Le détail droit par droit, tiré du code, est dans
l'[annexe](guide-roles-et-droits-annexe.md). Les documents techniques sont
[Rôles et accès](roles-et-acces.md) pour l'application et `docs/roles-et-acces.md`
dans le dépôt de la console.

---

## 1. L'essentiel en une page

1. **Trois questions, dans l'ordre**, avant chaque action :
   1. le module est-il activé pour l'hôtel ?
   2. la personne détient-elle le droit ?
   3. pour un encaissement, sa caisse est-elle ouverte ?
2. **Les droits d'une personne viennent de trois sources, et de rien d'autre** :
   - ses **rôles** ;
   - les **exceptions** posées sur un de ses rôles ou sur elle ;
   - pour l'administrateur, une **intervention** déclarée.

   Le département range le personnel ; il ne donne aucun droit.
3. **Un chef fait le travail de ses membres.** Le chef de réception détient tout ce
   que détient le réceptionniste.
4. **Un rôle peut être tenu en plein exercice ou en lecture seule.** En lecture
   seule, la personne consulte le service sans y agir.
5. **Un refus l'emporte toujours** sur une autorisation, quelle que soit son origine.
6. **Quatre fonctions restent dans des mains différentes** : autoriser, détenir,
   enregistrer, contrôler. Un cumul interdit est refusé, sauf dérogation motivée et
   tracée.
7. **Chacun compte sa propre caisse ; la comptabilité contresigne.** Personne
   d'autre ne clôt une caisse.
8. **Le personnel d'un restaurant ne voit que les restaurants où il est affecté.**
   La direction et le contrôle les voient tous.
9. **L'administrateur administre, il n'exploite pas.** Il crée les comptes et règle
   la configuration. Il n'encaisse, ne valide et ne comptabilise que pendant une
   intervention déclarée, motivée et limitée dans le temps.
10. **La console Wetchah règle sa propre couche de droits, et seulement elle.** Ce
    que l'hôtel a réglé n'est jamais écrasé.

---

## 2. Les acteurs

### 2.1 Dans la console Wetchah

| Rôle | Ce qu'il fait |
|---|---|
| **Administrateur technique** (`tech_admin`) | Équipe Wetchah. Crée et met à jour les établissements, crée leurs administrateurs, règle la couche « console » des droits, suit les interventions, ouvre le mode assistance. |
| **Propriétaire** (`owner`) | Voit ses établissements (chiffres consolidés). Règle les droits de ses établissements et leurs comptes administrateurs. |
| **Éditeur de site** (`site_editor`) | Modifie le contenu du site vitrine d'un seul établissement. Rien d'autre. |

Ces comptes vivent dans la console. Les employés de l'hôtel vivent dans la base de
l'hôtel : la console ne les crée pas et n'y écrit pas (sauf l'administrateur, voir
plus bas).

### 2.2 Dans l'hôtel

Les rôles sont rangés par niveau. Un chef (niveau 3) **inclut** ses membres
(niveau 4) : il détient leurs droits en plus des siens.

```
Niveau 1  Administrateur ............................ service informatique de l'hôtel
Niveau 2  Manager ................................... direction des opérations
Niveau 3  Chef de réception ─────────── inclut ── Réceptionniste
          Gouvernant(e) général(e) ──── inclut ── Valet / Femme de chambre
          Responsable de restaurant ─── inclut ── Serveur, Caissier restaurant
          Chef de cuisine ───────────── inclut ── Cuisinier
          Responsable boutique ──────── inclut ── Vendeur-caissier
          Chef économe ──────────────── inclut ── Magasinier
          Responsable administratif et financier ── inclut ── Comptable
Transversal (lecture seule) : Contrôleur de gestion, Contrôleur qualité & audit
Technique : Support Wetchah (lecture seule, ouvert par le mode assistance)
```

Il n'y a pas de caissier à l'hébergement : le réceptionniste encaisse. Le
« caissier » est celui du restaurant.

---

## 3. Rôle par rôle

Chaque fiche dit ce que le rôle fait, ce qu'il ne fait pas, et ce qu'il faut savoir.
« Consulte » veut dire voir sans agir.

### Administration et direction

**Administrateur** — le service informatique de l'hôtel.

- *Fait* :
  - crée et tient les comptes du personnel, managers compris, et leur attribue des
    rôles ;
  - règle les droits de l'hôtel (rôles et exceptions) ;
  - règle l'onglet Général des paramètres ;
  - décrit les chambres, leurs types, les restaurants et leurs salles ;
  - consulte tous les services.
- *Ne fait pas* : encaisser, valider, comptabiliser, servir. Pour agir dans
  l'exploitation, il déclare une **intervention** (§ 8).
- *À savoir* :
  - son compte ne se crée que depuis la console Wetchah ;
  - il ne cumule aucun autre rôle, car celui qui distribue les droits ne doit pas
    pouvoir s'en servir.

**Manager** — la direction des opérations.

- *Fait* :
  - dirige l'hébergement : réservations, séjours, groupes, clients, caisse de la
    réception, housekeeping, chambres, tarifs, prestations et partenaires ;
  - valide ce qui engage l'hôtel : séjours offerts, demandes d'achat ;
  - crée les restaurants, leurs salles et leurs équipes ;
  - gère les comptes du personnel ;
  - consulte tous les autres services.
- *Ne fait pas* :
  - saisir à la place des responsables du restaurant, de la boutique, de l'économat
    ou de la comptabilité ;
  - contresigner une caisse.
- *À savoir* : il supervise, il ne fait pas le travail de ses équipes. Un directeur
  qui saisirait à leur place brouillerait la responsabilité de chacun.

### Hébergement

**Chef de réception** — encadre la réception.

- *Fait* : tout ce que fait le réceptionniste, plus décrire les chambres et leurs
  types, et régler les paramètres de la réception.

**Réceptionniste** — accueil, réservations, séjours.

- *Fait* :
  - réservations, arrivées, départs, lignes de folio, encaissements ;
  - groupes, clients ;
  - POS de la réception ;
  - sa caisse : ouvrir, sortie de caisse, compter ;
  - statut des chambres ;
  - demandes à l'économat.
- *Consulte* : les notes de restaurant que des résidents ont reportées sur leur
  séjour, dans tous les restaurants.
- *Ne fait pas* : encaisser au restaurant ou à la boutique.

### Housekeeping

**Gouvernant(e) général(e)** — dirige les étages.

- *Fait* : tout ce que fait le valet, plus composer les équipes, affecter les
  chambres, régler les paramètres du housekeeping et faire les demandes à
  l'économat.

**Valet / Femme de chambre** — remise en état des chambres.

- *Fait* : nettoyer, signaler une chambre prête ou un incident, inspecter et
  rejeter.
- *Ne voit pas* : les montants.

### Restaurant

Chaque membre du personnel du restaurant agit **dans les restaurants où il est
affecté** (§ 7).

**Responsable de restaurant** — dirige la salle.

- *Fait* :
  - tout ce que font le serveur et le caissier ;
  - compose la carte ;
  - réassigne et corrige le statut des commandes ;
  - ouvre et clôt les buffets ;
  - établit les devis de banquet et les fait avancer (confirmé, réalisé) ;
  - compose l'équipe de ses restaurants ;
  - fait les demandes à l'économat.

**Chef de cuisine** — dirige la cuisine.

- *Fait* :
  - tout ce que fait le cuisinier ;
  - la carte ;
  - les fiches techniques et la production ;
  - le garde-manger : articles, réceptions, mouvements ;
  - les inventaires ;
  - les demandes à l'économat.
- *Ne fait pas* : la salle ni la caisse.

**Serveur (salle)** — le service.

- *Fait* :
  - prise de service ;
  - commandes et transmission en cuisine ;
  - service des plats ;
  - petits-déjeuners ;
  - le bar (boissons prêtes).

**Cuisinier** — la cuisine.

- *Fait* : prendre un bon en préparation, signaler le plat prêt, déclarer une perte.

**Caissier restaurant** — l'encaissement.

- *Fait* :
  - encaisser les notes ;
  - enregistrer les entrées au buffet ;
  - encaisser l'acompte et le solde des banquets ;
  - tenir sa caisse : ouvrir, sortie de caisse, compter.

### Boutique

**Responsable boutique** — tout ce que fait le vendeur, plus le catalogue des
produits et les demandes à l'économat.

**Vendeur-caissier** — ventes, remboursements, et sa caisse.

### Économat

**Chef économe** — le magasin central.

- *Fait* :
  - articles, catégories, fournisseurs ;
  - bons de commande ;
  - validation des demandes des services ;
  - dépôts de service ;
  - inventaires ;
  - reprise du stock initial ;
  - conversion d'une demande d'achat validée en commande.
- *Ne fait pas* : valider une demande d'achat. C'est une décision de dépense, que la
  direction se réserve.

**Magasinier** — réceptionne les livraisons, livre les demandes, saisit les
comptages.

### Comptabilité et finances

**Responsable administratif et financier** et **Comptable** — les mêmes droits.

- *Font* :
  - écritures, rapprochements, clôtures, audit de nuit ;
  - dépenses ;
  - fiches de coût des chambres ;
  - **contresignature des comptages de caisse** : eux seuls la font.
- *Ne font pas* : encaisser, ni détenir le stock (§ 5).

### Contrôle

**Contrôleur de gestion** — consulte tous les services, la comptabilité et les caisses
comprises. N'écrit nulle part.

**Contrôleur qualité & audit** — consulte l'exploitation (hébergement, housekeeping,
restaurant, boutique, économat). Ne voit ni la comptabilité, ni les caisses, ni les
fiches de coût, ni l'export du fichier clients. N'écrit nulle part.

Ni l'un ni l'autre ne cumule un rôle opérationnel : un contrôle exercé sur son propre
travail n'est plus un contrôle.

### Support Wetchah

Compte technique propre à chaque hôtel, ouvert seulement par le mode assistance de la
console, jamais par un mot de passe. Il consulte pour diagnostiquer et n'écrit rien.
L'hôtel voit ses sessions (Administration → Sessions du support).

---

## 4. Comment un droit se décide

### 4.1 Les trois verrous

| Verrou | Question | Réponse en cas de refus |
|---|---|---|
| Module | Le module est-il activé pour l'hôtel ? | Le module n'apparaît pas |
| Droit | La personne détient-elle ce droit ? | Message « accès refusé », incident journalisé |
| Caisse | Pour encaisser : sa caisse est-elle ouverte ? | « Ouvrez votre caisse » |

### 4.2 L'ordre de décision

Pour un droit donné, le moteur s'arrête à la première réponse :

1. un **refus** posé sur la personne ou sur l'un de ses rôles ;
2. une **autorisation** posée sur la personne ou sur l'un de ses rôles ;
3. le **modèle livré** : les droits du rôle, hiérarchie comprise ;
4. pour l'administrateur, une **intervention en cours** sur ce service.

### 4.3 Les couches de réglage

Les écarts au modèle se posent à trois endroits, chacun par son autorité :

| Couche | Réglée par | Où | Porte sur |
|---|---|---|---|
| Modèle | Le code livré | — | Tous les rôles |
| Console | Wetchah ou le propriétaire | Console → Droits & rôles | Les rôles |
| Hôtel | L'administrateur de l'hôtel | Application → Administration → Rôles & droits | Les rôles |
| Exceptions nominatives | L'administrateur de l'hôtel | Fiche de l'employé, ou Rôles & droits | Une personne, avec une échéance possible |

La console ne remplace jamais que sa couche. L'hôtel voit les deux couches, et la
console voit celle de l'hôtel, marquée **H**, et les exceptions nominatives, marquées
**N**.

### 4.4 La lecture seule par rôle

À l'attribution d'un rôle, on choisit **lecture** ou **lecture / écriture**. En lecture
seule, la personne garde tous les droits de consultation du rôle et aucune action,
même si une autorisation a été posée sur ce rôle. Un autre rôle qu'elle tient en
écriture peut, lui, donner l'action.

Pour retirer **une seule action** à une personne, on utilise une exception nominative
(§ 9.3).

### 4.5 Ce que l'écran montre

- Une rubrique du menu apparaît dès que la personne détient au moins un droit du
  module.
- Un bouton ou un lien n'apparaît que si la personne détient le droit de l'action.
- La **fiche de l'employé** (Utilisateurs → un nom), section « Ses accès », liste ce
  que le moteur lui accorde réellement, service par service puis écran par écran, en
  clair (« Bons de commande — Envoyer au fournisseur ») ; le code du droit apparaît au
  survol. C'est la référence en cas de doute.

---

## 5. La séparation des tâches

Quatre fonctions doivent rester dans des mains différentes : **autoriser, détenir,
enregistrer, contrôler**. Qui en cumule deux peut commettre un acte et le dissimuler.

| Cumul refusé | Pourquoi |
|---|---|
| Chef économe ou magasinier **+** comptable | Détenir le stock et tenir les livres : un vol se couvre par une écriture de régularisation |
| Caissier, responsable de restaurant, réceptionniste ou vendeur-caissier **+** comptable | Encaisser et enregistrer : l'encaissement du jour couvre le trou de la veille |
| Contrôleur de gestion ou contrôleur qualité **+** tout rôle opérationnel | Un contrôle exercé sur son propre travail n'est plus un contrôle |
| Administrateur **+** tout autre rôle | Celui qui distribue les droits ne doit pas pouvoir s'en servir |

Les rôles sont examinés avec ce qu'ils incluent : un responsable administratif et
financier porte les incompatibilités du comptable.

**Dérogation.** Dans un petit hôtel, trois personnes ne peuvent pas toujours tenir
quatre fonctions. Le cumul reste possible :

- il faut cocher la dérogation et écrire un motif ;
- elle est tracée au journal ;
- elle reste visible dans les alertes.

Les mêmes règles s'appliquent quand on **autorise une action** à un rôle, depuis
l'hôtel ou depuis la console. Autoriser l'enregistrement à un caissier en fait un
caissier comptable : la case passe au rouge et exige la même dérogation.

---

## 6. Les caisses

Le circuit est le même partout : réception, boutique, chaque restaurant.

```
Ouvrir (avec un fond) → encaisser → compter → attendre le contrôle → contresignature de la comptabilité → close
```

- **Une session par personne** : chacun compte ce qu'il a encaissé.
- **Un tiroir, une session ouverte à la fois.** Au restaurant, une caisse par
  restaurant. La personne suivante attend que la précédente ait compté.
- **Un comptage en attente de contrôle** empêche son titulaire d'ouvrir une nouvelle
  caisse.
- **Le solde théorique est recalculé**, jamais saisi : c'est lui qui fait apparaître
  l'écart. Au restaurant, il compte les notes, les entrées au buffet et les règlements
  de banquet en espèces.
- **La contresignature** revient à la comptabilité seule, jamais à celui qui a compté.
  Ni l'hôtel ni le manager ne peuvent en dispenser.
- **Le report sur la chambre** n'est pas un encaissement : il ne demande pas de caisse,
  et le folio le porte jusqu'au départ.
- **Un encaissement ne s'annule** que dans sa propre caisse, tant qu'elle n'est pas
  comptée.

| Caisse | Qui l'ouvre et la compte |
|---|---|
| Réception | Réceptionniste (et chef de réception, manager) |
| Boutique | Vendeur-caissier (et responsable boutique) |
| Chaque restaurant | Caissier (et responsable de restaurant s'il le faut) |

---

## 7. Plusieurs restaurants

L'hôtel crée autant de restaurants qu'il en exploite. Chacun a :

- sa carte ;
- sa cuisine et son bar ;
- son garde-manger et ses inventaires ;
- sa caisse ;
- son équipe et ses salles ;
- ses modes de service : à la carte, au buffet, ou les deux.

Un banquet se tient dans l'un ou l'autre.

| Qui | Voit |
|---|---|
| Personnel du restaurant (responsable, chef, serveur, cuisinier, caissier) | Les restaurants où il est **affecté**, un ou plusieurs |
| Direction et contrôle (administrateur, manager, contrôleurs, support) | Tous les restaurants, ensemble ou un par un |
| Réception | Dans tous les restaurants, les **seules notes reportées sur un séjour** |

- Le **sélecteur** en haut des écrans du restaurant choisit le restaurant courant, ou
  « Tous les restaurants ». Il ne donne aucun droit : il borne ce que l'écran montre.
- On compose une carte, un garde-manger, des fiches ou un inventaire **dans un
  restaurant**. Depuis la vue d'ensemble, ces écrans se consultent seulement.
- Une note, une entrée au buffet ou un règlement de banquet s'encaisse **à la caisse
  de son restaurant**.
- **Affecter le personnel** : depuis Restaurant → Restaurants → « Composer l'équipe »,
  par le manager ou le responsable du restaurant, ou depuis la gestion des
  utilisateurs.
- **Un membre du personnel non affecté** ne voit aucun restaurant, et l'écran le lui
  dit. Un hôtel qui n'a qu'un restaurant n'a rien à affecter : tout le monde y voit ce
  que ses droits lui ouvrent.

---

## 8. L'administrateur, les interventions et le support

### 8.1 L'intervention

Quand l'administrateur doit agir dans l'exploitation (corriger une note, débloquer un
encaissement), il déclare une intervention dans Administration → Interventions :

- un **motif** (dix caractères au moins) ;
- une **durée** : 15, 30, 60, 120 ou 240 minutes ;
- les **services** couverts : hébergement et réception, housekeeping, restaurant,
  boutique, économat, comptabilité et caisses.

Pendant l'intervention :

- un bandeau reste affiché ;
- le manager est prévenu ;
- chaque action est marquée au journal d'audit.

L'hôtel transmet la trace à la console Wetchah. Si la console est injoignable,
l'intervention a lieu quand même : la trace part dès que possible, marquée
**tardive**. L'intervention se termine à son échéance, ou plus tôt d'un clic.

### 8.2 Le mode assistance

Depuis la console, l'équipe Wetchah ouvre une session d'assistance **ticket par
ticket**. Elle entre sous le compte technique **Support Wetchah** de l'hôtel, en
lecture seule. L'hôtel voit la session, et la console peut la révoquer.

### 8.3 Le journal d'audit

Administration → Journal d'audit, pour l'administrateur et le contrôleur de gestion.
On y trouve :

- les connexions ;
- les refus d'accès ;
- les changements de comptes, de rôles et de droits ;
- les dérogations ;
- les interventions ;
- les actions sensibles (paiements, clôtures, annulations).

---

## 9. Gestes courants

### Dans l'application de l'hôtel

**9.1 Créer un employé.** Utilisateurs → « Ajouter un membre ».

1. Saisissez le nom, l'email, le téléphone et le mot de passe (8 caractères au moins).
2. Choisissez le département. Il pré-coche le rôle de base du service, que vous pouvez
   décocher.
3. Cochez un ou plusieurs rôles et leur niveau : lecture, ou lecture / écriture. Le
   premier coché est le rôle principal.
4. Pour le département Restauration, choisissez son restaurant dans la liste
   « Restaurant d'affectation » qui apparaît sous le département. Une personne
   présente dans plusieurs restaurants s'ajoute à leurs équipes depuis Paramètres ›
   Restaurant.

Un cumul interdit est refusé, sauf dérogation motivée.

**9.1 bis Modifier, désactiver, réinitialiser le mot de passe.** Depuis la liste
(menu ⋮ de la ligne) ou depuis la fiche de l'employé (boutons en haut à droite).

- **Modifier** : nom, email, téléphone, département, restaurant, rôles. Le mot de
  passe ne se change pas ici.
- **Réinitialiser le mot de passe** : l'application donne un mot de passe provisoire,
  affiché une seule fois, à remettre à la personne. Ses sessions ouvertes se ferment ;
  à sa connexion suivante, elle choisit le sien avant tout autre écran. Le provisoire
  n'est écrit ni au journal ni en clair en base.
- **Désactiver le compte** : la personne ne peut plus se connecter ; **Réactiver** la
  rétablit.

On n'applique aucune de ces actions à son propre compte. Droit
`users.resetPassword` : manager et administrateur.

**9.2 Donner un service en lecture seule.** Même écran : choisissez « lecture » pour
ce rôle. La personne consulte, sans agir.

**9.3 Retirer des accès à une personne.** Ouvrez sa fiche (Utilisateurs → son nom),
section « Ses accès » :

1. dépliez le service ; cochez les accès à retirer — ou utilisez les raccourcis
   « ce qui modifie (lecture seule) » et « tout le service » ;
2. dans le bandeau qui apparaît, écrivez un **motif** ;
3. si besoin, fixez une **échéance**, par exemple « jusqu'à la fin de l'inventaire » ;
4. « Retirer ».

Ses rôles ne changent pas, ses collègues non plus : seule cette personne perd ces
accès. Un accès retiré reste affiché, barré, avec un bouton **Rétablir**.

**9.3 bis Accorder un accès en plus.** Même fiche, colonne « Exceptions » : « Accorder
un accès en plus » propose, en clair, ce que ses rôles ne lui donnent pas. Motif
obligatoire, échéance possible. Si l'accès fait cumuler des fonctions incompatibles,
l'enregistrement est refusé, sauf dérogation cochée.

L'exception échue cesse d'agir et reste visible dans les alertes.

**9.4 Ajuster un rôle pour tout l'hôtel.** Administration → Rôles & droits, onglet
Matrice.

1. Cochez ou décochez les cases de la couche de l'hôtel.
2. Lancez l'**aperçu** : il montre, personne par personne, qui gagne ou perd quel
   droit.
3. Enregistrez avec un motif.

Un cumul interdit exige une dérogation.

**9.5 Créer un restaurant et son équipe.** Restaurant → Restaurants.

- **Le manager** crée le restaurant (nom, code, préfixe des notes, modes de service),
  puis ajoute ses salles.
- **Le manager ou le responsable** compose l'équipe.

**9.6 Vérifier ce qu'une personne peut faire.** Sa fiche liste ce que le moteur lui
accorde réellement. L'onglet **Alertes** de Rôles & droits signale :

- les cumuls ;
- les comptes sans rôle ;
- l'absence de comptable ou d'administrateur ;
- les exceptions échues.

En ligne de commande, `php artisan roles:revue` donne la même revue.

### Dans la console Wetchah

**9.7 Donner un administrateur à un hôtel.** Droits & rôles de l'établissement →
Comptes administrateurs → Créer. Le mot de passe est saisi, ou tiré au hasard et
montré une seule fois. La même page réinitialise, désactive et réactive.

**9.8 Régler la couche de la console.** Droits & rôles → Matrice.

1. Cochez les cases.
2. Lancez l'aperçu, qui est obligatoire.
3. Écrivez un motif, et une dérogation si un cumul apparaît.
4. Enregistrez.

Chaque enregistrement crée une version. L'onglet Historique compare les versions et
revient à l'une d'elles ; ce retour est inscrit comme une version nouvelle.

**9.9 Suivre les interventions.** Droits & rôles → Interventions. Une trace arrivée
en retard porte le badge **Tardive**.

**9.10 Assister un hôtel.** Support → depuis un ticket, ouvrir une session
d'assistance. Elle se révoque depuis la même liste.

---

## 10. Questions fréquentes

**Le manager ne peut pas encaisser au restaurant.** C'est voulu. Il supervise ; le
caissier ou le responsable de restaurant encaisse.

**Un serveur ne voit aucune commande.** Il n'est affecté à aucun restaurant : affectez-le
depuis Restaurant → Restaurants → « Composer l'équipe ».

**Rattacher quelqu'un au département Direction ne lui donne aucun droit.** Le
département range le personnel. Ce sont les rôles qui donnent les droits.

**L'administrateur ne peut pas corriger une note.** Il déclare une intervention sur le
service restaurant, le temps de la correction.

**Le comptable doit aussi encaisser.** Le cumul est refusé par défaut. Dans un petit
hôtel, accordez-le avec une dérogation motivée : elle reste tracée et visible.

**La console n'affiche pas un réglage fait à l'hôtel.** Elle l'affiche, mais ne le
modifie pas : badge **H** pour la couche de l'hôtel, **N** pour une exception
nominative.

**Une caisse ne se ferme pas.** Elle a été comptée et attend la contresignature de la
comptabilité (Comptabilité → Contrôle des comptages).

**Une restriction « service en lecture seule » posée autrefois depuis la console.** Elle
a été convertie en exceptions nominatives. Vous la retrouvez sur la fiche de la
personne, et vous pouvez la lever.

---

## 11. Repères pour l'équipe technique

| Sujet | Où |
|---|---|
| Rôles, niveaux, inclusions | `app/Support/RoleCatalog.php` |
| Droits et rôles qui les détiennent | `app/Support/PermissionCatalog.php` (le droit se déduit du nom de la route : `restaurant.orders.store` → `restaurant.orders.creer`) |
| Décision | `app/Services/PermissionResolver.php` |
| Cumuls interdits | `app/Support/DutySegregation.php` |
| Circuit des caisses | `app/Services/CashRegisterCircuit.php`, `app/Support/CashClosurePolicy.php` |
| Restaurants | `app/Services/RestaurantContext.php`, trait `AppartientAUnRestaurant` |
| Interventions | `app/Models/Intervention.php` |
| Écrans | `@droit(...)`, `@pour(...)`, `<x-sidebar-link>` (qui pose son droit) |
| Conformité | `PermissionCatalogConformityTest` (catalogue = routes), `tests/Fixtures/route_roles_avant_phase1.json` (référence des rôles par route) |
| Annexe de ce guide | `php artisan droits:annexe`, à relancer après tout changement du catalogue |

La console lit le catalogue par l'API de l'hôtel (`GET /api/permissions/matrice`) et
n'en garde aucune copie.
