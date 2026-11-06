# Spécifications Exhaustives — Lot 2 : Création, Administration des Utilisateurs, Sécurité RBAC & MailerSend

Ce document constitue la référence absolue pour l'implémentation du **Lot 2**. Il intègre l'ensemble des règles de sécurité, de contrôle d'accès et d'anti-escalade de l'audit ainsi que les spécifications du nouveau workflow d'authentification et de notification par e-mail.

---

## 1. Règles Strictes de Contrôle d'Accès, Sécurité et Anti-Escalade

Ces exigences constituent le cœur non négociable de la sécurité des utilisateurs :

### 1.1. Interdiction formelle d'auto-élévation de privilèges (Anti-Escalade)
- **Principe** : Un utilisateur ne peut en aucun cas s'attribuer un rôle supérieur à son niveau hiérarchique, ni étendre ses propres privilèges, ni modifier son propre niveau d'autorisation.
- **Règles d'application (`UserManagementController`)** :
  - Un utilisateur ne peut pas modifier ses propres rôles ni son affectation (filtrage strict dans `ensureManageableByCurrentManager` : `$user->is($request->user())` interdit pour les modifications de privilèges).
  - Un responsable (ex: `reception_chief`, `restaurant_manager`, `manager`) ne peut attribuer que des rôles de niveau strictement inférieur ou égal aux rôles assignables autorisés de son périmètre (`assignableRoles()`).

### 1.2. Canal exclusif de création d'administrateur
- **Principe** : **Le rôle `admin` ne peut JAMAIS être créé ni attribué depuis l'application hôtelière (APP).**
- **Verrouillage technique** :
  - Le rôle `admin` est formellement exclu de la liste `assignableRoles()` de l'APP.
  - Même un `manager` ou une personne disposant du droit d'écriture sur les utilisateurs ne peut pas sélectionner ni poster le rôle `admin`.
  - **Canal unique** : Tout compte administrateur doit obligatoirement provenir de la console centrale d'orchestration (ERP) via l'API sécurisée (`POST /api/administrateurs`), gardée par le jeton d'orchestration `ORCHESTRATION_SECRET`.

### 1.3. Validation stricte des affectations requises
- **Principe** : Aucun compte ne peut être enregistré dans le vide lorsque sa fonction exige un périmètre opérationnel.
- **Règles métier** :
  - **Département Restauration (`FNB`)** : Pour tout collaborateur dont le département est Restauration ou portant un rôle de restauration (`restaurant_manager`, `restaurant_chief`, `restaurant_cook`, `restaurant_staff`, `cashier`), la sélection d'au moins un restaurant d'affectation (`restaurant_ids`) est **strictement obligatoire**.
  - Si aucun restaurant n'est sélectionné, la requête est rejetée avec une erreur de validation 422 explicite : *« Un collaborateur du service restauration doit être affecté à au moins un restaurant. »*.
  - Les départements exigeant un rattachement d'équipe ou de spécialité technique appliquent la même validation stricte.

### 1.4. Atomicité transactionnelle intégrale (`DB::transaction`)
- **Principe** : La création ou modification d'un compte ne doit jamais laisser la base de données dans un état partiel ou incohérent.
- **Périmètre de la transaction atomique** :
  1. Insertion / mise à jour de l'enregistrement `User`.
  2. Synchronisation des rôles pivots (`role_user` avec niveaux).
  3. Synchronisation des points de vente / restaurants rattachés (`restaurant_user`).
  4. Enregistrement obligatoire de la trace d'audit dans `AuditLog`.
- **Garantie** : Si l'une de ces 4 étapes échoue (ex: contrainte de clé, erreur de validation rôle, indisponibilité), un `rollback` immédiat annule l'ensemble. Aucun compte orphelin sans rôle ni log d'audit ne peut exister.

### 1.5. Gestion sécurisée et radicale de la désactivation
- **Interdiction d'auto-désactivation** : Un utilisateur ne peut pas désactiver son propre compte (`if ($user->is($request->user())) return back()->with('error', ...)`).
- **Révocation immédiate à la désactivation** :
  - Révocation et rotation instantanée du jeton `remember_token` (`Str::random(60)`).
  - Purge immédiate de **toutes les sessions actives** de l'utilisateur dans la table `sessions` (`DB::table('sessions')->where('user_id', $user->id)->delete()`).
  - Blocage immédiat de tout accès en cours à la prochaine requête HTTP (vérification continue dans `EnsureRoleAccess` et `TrackUserOnlineStatus`).

### 1.6. Ségrégation des tâches explicite (SoD)
- **Principe** : Détection et blocage des cumuls de fonctions incompatibles.
- **Règle** : Lors de la soumission des rôles d'un utilisateur, le système vérifie les incompatibilités via `DutySegregation::conflictsFor($roleSlugs)`. Si un cumul interdit est détecté (ex: `econome` + `accountant`, `cashier` + `accountant`), la création/modification est refusée sauf dérogation motivée expresse enregistrée au journal d'audit.

---

## 2. Infrastructure E-mail & Intégration MailerSend

### 2.1. Driver officiel MailerSend
- Dépendance Composer : `mailersend/laravel-driver`.
- Déclaration dans `config/mail.php` (`'mailersend' => ['transport' => 'mailersend']`).
- Variables d'environnement normalisées (`MAIL_MAILER=mailersend`, `MAILERSEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`).
- Prise en charge de la configuration d'expéditeur dynamique via `MailIdentity`.

### 2.2. Service & Contrôleur Centralisé
- **`App\Services\MailNotificationService`** : Service unique orchestrant l'envoi de tous les e-mails transactionnels du PMS.
- **`App\Http\Controllers\MailNotificationController`** : Contrôleur pour le déclenchement et suivi des envois.
- **Mailables standards** :
  1. `App\Mail\UserWelcomeMail` : E-mail de création de compte avec identifiants, mot de passe par défaut et consignes.
  2. `App\Mail\UserPasswordResetMail` : E-mail de mot de passe temporaire actif 5 heures.
  3. `App\Mail\PurchaseOrderMail` : Bon de commande officiel transmis au fournisseur.

---

## 3. Nouveau Workflow de Création d'Utilisateur

### 3.1. Suppression des champs mot de passe dans le formulaire
- Les champs `password` et `password_confirmation` sont supprimés du formulaire de création (`resources/views/users/index.blade.php`, `resources/views/users/create.blade.php`).
- L'administrateur ou manager ne saisit plus de mot de passe à la création d'un collaborateur.

### 3.2. Mot de passe par défaut sécurisé et configurable
- **Zéro mot de passe en dur** : Le mot de passe par défaut est résolu dynamiquement via `config('auth.default_user_password', env('DEFAULT_USER_PASSWORD', 'Wetchah@' . date('Y')))`.
- Haché avec Bcrypt à l'enregistrement.
- Marqué avec `must_change_password = true`.
- Envoi automatique de l'e-mail `UserWelcomeMail` contenant :
  - L'adresse e-mail de connexion.
  - Le mot de passe par défaut attribué.
  - Le lien vers la page de connexion.
  - L'instruction explicite indiquant qu'un modal bloquant imposera la création de son mot de passe personnel dès sa connexion.

---

## 4. Première Connexion & Modal Bloquant Obligatoire

### 4.1. Affichage non-contournable
- Tout utilisateur connecté ayant `must_change_password === true` se voit afficher un **modal bloquant non dismissible** (composant `force-password-change-modal`) sur toutes les pages de l'application.
- Le modal empêche tout clic en arrière-plan, masque la navigation et n'offre aucune croix de fermeture.

### 4.2. Saisie et validation du mot de passe
- Champs : `Nouveau mot de passe` et `Confirmer le mot de passe`.
- Validation stricte : min 8 caractères, confirmation identique, mot de passe différent du mot de passe par défaut ou temporaire (`Hash::check`).
- À la validation réussie :
  - Mise à jour du mot de passe haché.
  - Bascule de `must_change_password = false`.
  - Réinitialisation de `temporary_password_expires_at = null`.
  - Régénération de la session utilisateur.
  - Audit log : `AuditLog::record(..., "L'utilisateur a défini son mot de passe personnel")`.
  - Fermeture du modal et accès immédiat aux fonctionnalités autorisées.

---

## 5. Workflow de Réinitialisation par l'Admin (Oubli de mot de passe)

### 5.1. Procédure et validité stricte de 5 heures
1. L'utilisateur signale son oubli de mot de passe à son manager ou administrateur.
2. L'administrateur clique sur **« Réinitialiser le mot de passe »** depuis la gestion des utilisateurs (`UserManagementController@resetPassword`).
3. Le système :
   - Génère un mot de passe temporaire aléatoire sécurisé et lisible.
   - Ajoute/met à jour `temporary_password_expires_at = now()->addHours(5)` (colonne dédiée via migration).
   - Met à jour `password = Hash::make($temporaryPassword)` et `must_change_password = true`.
   - Révoque immédiatement le `remember_token`.
   - **Purge instantanément toutes les sessions actives de l'utilisateur** dans la table `sessions` (déconnexion immédiate de toute session suspecte ou oubliée).
   - Déclenche l'envoi de `UserPasswordResetMail` avec le mot de passe temporaire et la mention claire de sa validité de **5 heures**.

### 5.2. Contrôle de l'expiration à la connexion
- Lors de la tentative de connexion (`LoginRequest`) :
  - Si l'utilisateur tente de se connecter avec le mot de passe temporaire :
    - **Si `temporary_password_expires_at < now()`** : Refus de connexion avec message : *« Votre mot de passe temporaire a expiré (validité 5h). Veuillez contacter votre administrateur pour une nouvelle réinitialisation. »*.
    - **Si `temporary_password_expires_at >= now()`** : Connexion autorisée ➔ le modal bloquant apparaît immédiatement pour lui imposer de créer son mot de passe définitif.

---

## 6. Matrice des Critères d'Acceptation et Points de Vérification

| Réf | Exigence de Sécurité / Fonctionnelle | Point de contrôle / Méthode de validation |
|:---|:---|:---|
| **V1** | **Anti-escalade** | Impossible d'élever son propre niveau ou de s'attribuer un rôle supérieur. Requête POST directe rejetée. |
| **V2** | **Canal exclusif Admin** | Rôle `admin` non assignable dans l'APP ; tentative d'injection directe de `role: admin` rejetée avec 403/422. Création possible uniquement via API ERP. |
| **V3** | **Affectations obligatoires** | Création d'un agent F&B / restaurant sans `restaurant_ids` rejetée avec erreur 422. |
| **V4** | **Atomicité transactionnelle** | Transaction `DB::transaction` intégrale : test avec exception simulée vérifiant l'absence d'utilisateur, de rôle ou de log orphelin. |
| **V5** | **Gestion de la désactivation** | Impossibilité de se désactiver soi-même ; désactivation d'un tiers purge immédiatement ses sessions et révoque son token. |
| **V6** | **Ségrégation des tâches (SoD)** | Détection des incompatibilités de rôles (`DutySegregation`) et blocage des cumuls sans dérogation. |
| **V7** | **Driver MailerSend** | Configuration du transport `mailersend` dans `config/mail.php` et `.env.example`. |
| **V8** | **Hub d'e-mails centralisé** | `MailNotificationService` fonctionnel et injecté pour tous les envois (bienvenue, reset, commandes). |
| **V9** | **Formulaire sans mot de passe** | Champs `password` retirés du formulaire ; mot de passe par défaut issu de `config('auth.default_user_password')`. |
| **V10** | **Envoi du mail de bienvenue** | E-mail `UserWelcomeMail` intercepté et vérifié avec identifiants et consignes. |
| **V11** | **Modal bloquant 1ère connexion** | Modal non-dismissible forçant le changement de mot de passe dès `must_change_password === true`. |
| **V12** | **Mot de passe temporaire 5h** | Mot de passe temporaire actif 5 heures ; refus net à 5h01 (`Carbon::setTestNow`) avec message explicite. |
| **V13** | **Zéro Régression Lot 0 & 1** | Maintien du 100% PASS sur les 145 tests existants de sécurité et de droits. |
