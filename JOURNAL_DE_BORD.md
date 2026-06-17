# 📓 Journal de Bord & Suivi de Développement
*Projet : Villa Boutanga (PMS - Hôtel / Restaurant / Boutique)*

Ce document sert de repère pour suivre l'évolution du projet, savoir sur quel module on travaille actuellement, et garder une trace des fonctionnalités développées jour après jour.

---

## 🎯 Ce qui est en cours de conception (Aujourd'hui)
**Module actuel :** Paramétrage système & Affinage des règles métier.
**Objectif immédiat :** Finaliser la rubrique "Paramètres" (Hébergement, Restaurant, Boutique) et s'assurer que toutes les règles métier (check-in/out, fidélisation, taxes) soient correctement définies pour chaque chef de département.

---

## 📊 Avancement Global par Module

- [x] **Tableaux de bord (Dashboards) :** Refonte UI/UX dynamique par rôle (Réception, Housekeeping, etc.).
- [x] **Assistant IA (Kuété) :** Implémentation d'un Agent Autonome (Mistral) avec capacité de lire la BDD en temps réel (Function Calling) et mémoriser des consignes (JSON memory).
- [x] **Système de Permissions :** Sécurisation de la sidebar et des accès selon le rôle de l'employé.
- [x] **Housekeeping :** Logique d'assignation, compteurs de tâches et règles de disponibilité des équipes.
- [x] **Audit et Sécurité (Admin Global) :** Journal d'audit interactif (recherche, filtres, détails JSON AlpineJS), statistiques de sécurité, suspension de compte et réinitialisation de mot de passe.
- [ ] **Paramètres du Système :** Interfaces de configuration dynamique (en cours d'intégration avec la logique backend).
- [ ] **Programme de Fidélisation :** Interface prête, logique de calcul des points à brancher.
- [ ] **Boutique & Restaurant :** Gestion fine des stocks et impression de tickets cuisine.

---

## 📅 Journal de Développement (Logs)

### [2026-06-17]
- **Audit et Sécurité (Admin Global) :**
  - Implémentation complète de l'onglet "Audit" conforme au cahier des charges de l'administration globale.
  - Création de la table de base de données `audit_logs` et du modèle `AuditLog` pour la journalisation unifiée.
  - Intégration de filtres avancés (tenant, utilisateur, type d'événement, module, dates) et volet de détails JSON interactif propulsé par AlpineJS.
  - Capture automatique des accès refusés dans les middlewares `AdminOnly` et `EnsureRoleAccess`.
  - Branchement d'écouteurs d'événements d'authentification (`Login`, `Logout`, `Failed`) pour mettre à jour `last_login_at` et enregistrer l'activité.
  - Journalisation des actions sensibles (paiements, annulations, suppressions) dans les contrôleurs existants (chambres, réservations, restaurant, boutique, membres).
  - Ajout des actions de sécurité : activation/désactivation de compte utilisateur et génération de mot de passe temporaire fort avec banner d'alerte.
  - Écriture d'un ensemble complet de tests de régression (`AuditLogTest.php`) avec 100% de succès.
- **Correctif Bogue (Génération de Références de Paiement) :**
  - Résolution d'un bug de collision de clés uniques (`payments_reference_unique`) lors de la génération de références séquentielles (`PAY-YYYY-00XXXX`) en présence de références aléatoires (`PAY-YYYY-XXXX`). Le système calcule maintenant la séquence maximale réelle sur toutes les références de l'année.

### [2026-06-03]
- **Réservations (Acompte & Prix Négocié) :**
  - Ajout d'une nouvelle étape de paiement (Confirmation) obligatoire à la fin du tunnel de réservation.
  - Possibilité pour le réceptionniste d'ajuster le prix total du séjour (Prix Négocié TTC).
  - Calcul dynamique de l'acompte minimum basé sur un pourcentage configurable dans les paramètres.
  - Création automatique du paiement et insertion dans la facture (FolioItem) pour calculer le solde (Reste à payer).
  - Application du système d'acompte (optionnel) lors de la création d'un Dossier Groupe.
  - Correction des formats monétaires (centimes en base de données, affichage en FCFA).
- **Paramètres Système :**
  - Branchement du backend (SettingsController) pour sauvegarder les formulaires de configuration (ex: Hébergement) dans la colonne `settings` (JSON) du Tenant.
  - Ajout des notifications de succès lors de l'enregistrement.

### [2026-06-02]
- **Création du Journal de Bord :** Mise en place de ce fichier pour le suivi du développement.
- **Module Réservation (Mandataire / Booker) :**
  - Ajout du concept de "Mandataire" (Booker) pour les réservations individuelles et groupes.
  - Différenciation en base de données : `customer_id` (le séjournant final) et `booker_id` (la tierce personne qui réserve et mandate, e.g. parent, agence).
  - Modification des formulaires de réservation (Wizard Étape 1) pour inclure un toggle "Qui effectue la réservation ?" permettant de choisir ou créer un profil mandataire.
  - La facturation utilise toujours le nom du client final séjournant par défaut.

### [2026-05-22]
- **Interface Paramètres :** Création de la rubrique `Paramètres` accessible uniquement aux responsables de départements (`manager`, `reception`, `housekeeping_leader`, `restaurant_chief`, `shop_manager`).
- **Logique UI :** Système d'onglets dynamiques. Le `manager` voit tout, les autres ne voient que leur département.
- **Règles Hébergement :** Ajout des paramètres de Check-in/out (J et J+N), et d'une section Tarification & Réductions.
- **Fidélisation :** Création de la maquette pour configurer la fidélisation client (Montant dépensé = X points, 1 point = X FCFA).
- **Correctif Bogue :** Résolution de l'erreur 403 (Accès refusé) du Manager causée par un problème de syntaxe dans le middleware (`|` remplacé par `,`).

### [2026-05-21]
- **Évolution Kuété (IA) :** Transformation de l'assistant en Agent Autonome. 
  - Ajout des "Outils" (Mistral Function Calling) pour que l'IA exécute elle-même des requêtes et réponde sans inventer de données.
  - Ajout de la Mémoire (`ai_memory.json`) pour retenir des mots de passe ou des règles (outils `learn_fact` et `get_memories`).
- **Dashboard Housekeeping :** Injection correcte des données d'assignation des équipes pour éviter les hallucinations de l'IA (Comptage des `activeAssignments`).

### [Dates antérieures]
- Résolution des bugs sur les sources de réservation, formatage de la caisse, et scrollbar de la boutique.
- Refonte des permissions de la réceptionniste.
- Création de l'interface chat flottante de Kuété avec persistance dans le navigateur (`sessionStorage`).
