# Spécifications Fonctionnelles et Techniques — Lot 6 : Pilotage RAF & Contrôle de gestion

**Document de référence pour l'implémentation du Lot 6**  
*Date : 6 novembre 2026*  
*Périmètre : `wetchah_app` (APP)*  
*Conformité : Audit Métier & Sécurité du 5 novembre 2026 (Section 2, 3 et 4 - Lot 6)*

---

## 1. Objectifs Métier et Exigences d'Audit

Selon le plan d'amélioration de l'audit (§2, §3 et §4 - Lot 6) :
1. **Comparaisons N vs N−1 et N vs M−1 homogènes :**
   - Le tableau de bord de gestion actuel compare le mois en cours au mois précédent ($M-1$). L'audit constate l'absence de comparaison avec l'année précédente ($N-1$).
   - Nécessité d'intégrer la comparaison N vs N-1 sur l'hébergement, la restauration, la boutique et la trésorerie.
   - Prise en compte rigoureuse des cas limites : mois incomplet en cours (comparaison sur le même nombre exact de jours écoulés en $N-1$), années bissextiles (29 février comparé au 28 février), et absence totale de données sur l'année $N-1$ sans provoquer d'erreur ni de division par zéro.
2. **Distinction formelle entre Budget et Prévision (Forecast) :**
   - Le budget annuel (`BudgetLine`) représente les objectifs initiaux fixés par l'établissement.
   - La prévision (`ForecastLine`) représente l'atterrissage révisable en cours d'exercice.
   - Les deux outils doivent être distingués dans les modèles, services et interfaces.
3. **Ségrégation des tâches (SoD) & Droits de préparation et de validation :**
   - Le **Contrôleur de gestion** (`controller`) est seul habilité à préparer et saisir les budgets et les prévisions.
   - Le **RAF / Directeur** (`finance_manager`, `manager`) est seul habilité à valider formellement le budget et la prévision.
   - Le budget et la prévision validés sont scellés et portent la signature de validation (`validated_at`, `validated_by`).
4. **Même définition des ventes, taxes et périodes & Rapprochement pièces / écritures :**
   - Les ventes doivent être uniformément exprimées en FCFA nets (centimes d'euros/FCFA stockés, hors gratuités `is_complimentary`, hors annulations).
   - Rapprochement explicite entre le chiffre d'affaires d'exploitation issu des modules métiers (hébergement, restauration, boutique) et les écritures comptables enregistrées dans le grand livre / journal des ventes.

---

## 2. Architecture Technique & Base de Données

### 2.1 Évolution de la table `budget_lines`
Ajout des colonnes de validation et de traçabilité SoD :
- `status` (`varchar(20)`, défaut `'draft'`, valeurs: `'draft'`, `'validated'`)
- `validated_by` (`foreignId` vers `users`, nullable)
- `validated_at` (`timestamp`, nullable)

### 2.2 Création de la table `forecast_lines`
Même maille que le budget (centre $\times$ nature $\times$ 12 mois) :
- `id`
- `year` (`unsignedSmallInteger`)
- `center` (`varchar(20)`) : `hebergement`, `restaurant`, `boutique`, `economat`, `structure`
- `nature` (`varchar(10)`) : `revenue`, `cost`
- `m1` à `m12` (`bigInteger`, centimes FCFA)
- `status` (`varchar(20)`, défaut `'draft'`, valeurs: `'draft'`, `'validated'`)
- `updated_by` (`foreignId` vers `users`, nullable)
- `validated_by` (`foreignId` vers `users`, nullable)
- `validated_at` (`timestamp`, nullable)
- `created_at`, `updated_at`
- Index unique : `['year', 'center', 'nature']`

---

## 3. Services Métier

### 3.1 `TableauDeBordGestion`
- Extension de la méthode `pour(CarbonImmutable $mois)` :
  - Calcul de la période N-1 :
    - `$n1Debut = $debut->subYearNoOverflow()`
    - Pour un mois incomplet en cours, `$n1Fin` prend le même nombre de jours écoulés en N-1 : `$n1Debut->addDays(min($jours, $n1Debut->daysInMonth) - 1)->endOfDay()`.
  - Calcul des métriques hébergement N-1 via `$this->hebergement($n1Debut, $n1Fin)`.
  - Calcul des métriques restauration N-1 via `$this->restaurant($n1Debut, $n1Fin)`.
  - Calcul des métriques trésorerie N-1 (recettes et dépenses).
  - Calcul des variations en % :
    - `variation_m1` : vs mois précédent
    - `variation_n1` : vs même période année N-1
  - Rapprochement exploitation vs comptabilité :
    - Calcul de l'écart entre le chiffre d'affaires des séjours et les écritures du journal de vente sur la période.

### 3.2 `PrevisionDeGestion`
- Gestion des lignes de prévision (`ForecastLine`) :
  - `grille(int $annee, ?array $forecastSaisi = null)`
  - `enregistrer(int $annee, array $saisie, User $auteur)` (réservé au contrôleur de gestion)
  - `valider(int $annee, User $raf)` (réservé au RAF)
  - `depuisLeBudget(int $annee)` : initialisation d'une prévision à partir du budget validé
  - `depuisLeRealiseEtBudget(int $annee)` : atterrissage combinant les mois écoulés réalisés + les mois futurs budgétés

### 3.3 `BudgetDeGestion`
- Ajout de la validation par le RAF :
  - `valider(int $annee, User $raf)`
  - Verrouillage de la saisie si le budget est validé, sauf réouverture explicite.

---

## 4. Matrice des Droits & Permissions

Dans `PermissionCatalog` :
- `pilotage.voir` : `['controller', 'finance_manager', 'manager']`
- `pilotage.budget.voir` : `['controller', 'finance_manager', 'manager']`
- `pilotage.budget.modifier` : `['controller']` (préparation / saisie)
- `pilotage.budget.valider` : `['finance_manager', 'manager']` (validation RAF)
- `pilotage.forecast.voir` : `['controller', 'finance_manager', 'manager']`
- `pilotage.forecast.modifier` : `['controller']` (préparation / saisie)
- `pilotage.forecast.valider` : `['finance_manager', 'manager']` (validation RAF)
- `pilotage.seuils.modifier` : `['controller', 'finance_manager']`

Actes exclusifs et non dérogeables :
- `pilotage.budget.modifier`, `pilotage.forecast.modifier` $\rightarrow$ Exclusivité Contrôleur de gestion
- `pilotage.budget.valider`, `pilotage.forecast.valider` $\rightarrow$ Exclusivité RAF

---

## 5. Interface Utilisateur (Vues Blade)

- **`resources/views/pilotage/index.blade.php` :**
  - Ajout des colonnes et badges comparatifs $N-1$ aux côtés de $M-1$.
  - Affichage clair des variations N vs N-1 avec code couleur (vert si progression du CA/RevPAR/TO ou baisse des coûts, rouge si inverse).
  - Indicateur de rapprochement d'intégrité (PMS vs Comptabilité).
- **`resources/views/pilotage/budget.blade.php` :**
  - Statut du budget (Brouillon / Validé).
  - Bouton de validation par le RAF si l'utilisateur a le droit `pilotage.budget.valider`.
  - Bouton de passage vers l'écran Prévisions (Forecast).
- **`resources/views/pilotage/forecast.blade.php` (Nouvelle vue) :**
  - Grille prévisionnelle annuelle par centre et par nature.
  - Outil de projection automatique (atterrissage Réalisé des mois écoulés + Budget des mois restants).
  - Bouton d'enregistrement pour le contrôleur et bouton de validation pour le RAF.
