# Proposition du role admin global

## Objectif

Dans ce projet, l'admin global doit piloter la plateforme complete, pas remplacer le manager d'un etablissement au quotidien. Il agit au niveau multi-tenant : etablissements, comptes managers, securite, configuration globale, support et supervision.

Le manager reste responsable de l'exploitation d'un hotel precis : reservations, chambres, clients, housekeeping, restaurant, boutique, caisse et parametres internes de son etablissement.

## Perimetre recommande

### 1. Gestion des etablissements

L'admin global devrait pouvoir :

- Creer un nouvel etablissement/tenant.
- Modifier les informations generales d'un etablissement : nom, slug, pays, adresse, contacts, devise, logo.
- Activer, suspendre ou archiver un etablissement.
- Voir l'etat d'onboarding d'un etablissement.
- Configurer les modules actifs par etablissement : hotel, restaurant, boutique, housekeeping, comptabilite, IA.
- Acceder a une vue detaillee d'un etablissement pour diagnostic.

### 2. Gestion des managers

L'admin global devrait pouvoir :

- Creer le compte manager principal d'un etablissement.
- Modifier les informations d'un manager.
- Activer ou desactiver un compte manager.
- Reinitialiser le mot de passe d'un manager.
- Transferer la responsabilite d'un etablissement a un autre manager.
- Voir quels managers sont rattaches a quels etablissements.

### 3. Supervision multi-etablissements

L'admin global devrait disposer d'un dashboard global avec :

- Nombre total d'etablissements actifs.
- Nombre total d'utilisateurs actifs.
- Nombre total de chambres.
- Reservations du jour, arrivees, departs et clients in-house par etablissement.
- Chiffre d'affaires consolide, si le module financier est actif.
- Alertes globales : impayes, stock bas, chambres en maintenance, comptes inactifs, erreurs systeme.
- Comparaison des performances entre etablissements.

### 4. Roles et permissions

L'admin global devrait pouvoir :

- Voir la liste des roles disponibles.
- Comprendre quels modules chaque role peut utiliser.
- Activer ou desactiver certains roles selon l'etablissement.
- Eventuellement creer des roles personnalises si le projet evolue vers une gestion plus avancee des permissions.

Les roles operationnels doivent rester separes :

- `manager` : gestion complete d'un etablissement.
- `reception` : clients, reservations, check-in, check-out.
- `housekeeping_leader` et `housekeeping_staff` : nettoyage, assignations, incidents.
- `restaurant_chief` et `restaurant_staff` : menus, commandes, garde-manger.
- `cashier` : paiements et facturation.
- `shop_manager` et `shop_cashier` : boutique, caisse, stock.
- `accountant` : rapports et comptabilite.

### 5. Audit et securite

L'admin global devrait pouvoir :

- Consulter les logs d'acces.
- Consulter les tentatives d'acces refuse.
- Voir les connexions recentes des utilisateurs.
- Desactiver un compte compromis.
- Forcer une reinitialisation de mot de passe.
- Voir les actions sensibles : creation de compte, modification de role, paiement, annulation, suppression.
- Filtrer les logs par etablissement, utilisateur, module ou periode.

### 6. Support operationnel

L'admin global devrait pouvoir assister un etablissement sans devenir un utilisateur metier permanent.

Deux approches sont possibles :

- Lecture globale : l'admin voit les donnees des etablissements sans pouvoir modifier les operations quotidiennes.
- Mode assistance : l'admin peut entrer temporairement dans le contexte d'un etablissement pour diagnostiquer ou corriger un probleme.

Le mode assistance doit etre trace :

- Qui a accede a quel etablissement.
- Quand l'acces a commence et fini.
- Quelles actions ont ete effectuees.
- Quelle justification a ete donnee.

### 7. Configuration globale

L'admin global devrait pouvoir gerer :

- Les parametres applicatifs generaux.
- Les modules disponibles sur la plateforme.
- Les limites par etablissement : nombre d'utilisateurs, nombre de chambres, modules autorises.
- Les integrations globales : email, stockage, IA, paiement, notifications.
- Les statuts techniques : files d'attente, erreurs d'envoi email, erreurs API.

### 8. Abonnements ou licences

Si le projet devient un PMS multi-client, l'admin global devrait aussi pouvoir gerer :

- Le plan actif d'un etablissement.
- Les modules inclus.
- La date d'expiration.
- La suspension ou reactivation.
- L'historique des paiements d'abonnement.

Cette facturation plateforme doit rester separee de la facturation operationnelle de l'hotel.

## Ce que l'admin global ne devrait pas faire par defaut

L'admin global ne devrait pas etre l'utilisateur qui gere les operations quotidiennes :

- Creer une reservation.
- Encaisser une facture client.
- Modifier le stock boutique.
- Changer le statut d'une chambre.
- Gerer une commande restaurant.

Ces actions doivent rester dans les roles metier. Si l'admin doit les faire pour assistance, cela devrait passer par un mode special, limite et audite.

## Modules admin a creer

Une structure possible serait :

- `/admin/dashboard` : vue globale multi-etablissements.
- `/admin/tenants` : gestion des etablissements.
- `/admin/managers` : gestion des managers.
- `/admin/roles` : consultation et configuration des roles.
- `/admin/modules` : activation des modules par etablissement.
- `/admin/audit-logs` : logs et securite.
- `/admin/system-health` : etat technique de la plateforme.

## Correction a prevoir dans le projet actuel

Aujourd'hui, le role `admin` existe dans le modele et les seeders, mais il n'a presque pas d'acces fonctionnel dans les routes et l'interface.

Il faudrait donc choisir clairement entre deux strategies :

### Strategie A : admin global separe

L'admin accede uniquement a un vrai espace `/admin`. Il ne voit pas directement les modules metier, sauf en lecture globale ou en mode assistance.

C'est la strategie recommandee pour un projet multi-tenant propre.

### Strategie B : admin super-utilisateur

L'admin est ajoute a presque tous les middlewares existants : chambres, reservations, clients, restaurant, boutique, housekeeping, analytics, utilisateurs.

C'est plus rapide a implementer, mais moins propre : l'admin devient un role operationnel omnipotent, ce qui complique l'audit et la separation des responsabilites.

## Recommandation

Pour ce projet, la meilleure approche est la strategie A :

- Creer un espace admin global distinct.
- Laisser les modules metier aux roles d'etablissement.
- Ajouter un mode assistance audite pour les cas exceptionnels.
- Garder le manager comme responsable principal d'un tenant.

Cela respecte mieux l'architecture multi-tenant et prepare le projet a gerer plusieurs etablissements sans melanger supervision plateforme et exploitation quotidienne.
