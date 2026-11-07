# Partenaires et comptes dépôt

Implémentation du 7 novembre 2026, dans `wetchah_app` (Laravel 12, Blade/Alpine,
PostgreSQL 16). Les entreprises et organismes peuvent avoir une affiliation sans
convention tarifaire. Leur compte dépôt est indépendant de cette convention.

## Parcours utilisateur

1. Dans **Paramètres → Partenaires**, créer ou modifier l'organisation. Cocher
   « Une convention tarifaire est signée » uniquement si elle existe. Sans
   convention, les membres conservent les tarifs particuliers et aucun privilège
   corporate n'est appliqué.
2. Joindre les scans de la convention : PDF, JPEG, PNG ou WebP, au maximum cinq
   fichiers de 10 Mo par enregistrement. Les ajouts conservent les versions
   antérieures ; les documents sont privés et téléchargés avec contrôle des droits.
   Dans le formulaire existant, **Appliquer**, puis **Enregistrer**, persiste les
   modifications et leurs pièces jointes.
3. Dans **Comptabilité → Comptes dépôt**, ou depuis l'onglet Partenaires, ouvrir
   le compte de l'organisation. Le RAF ou la comptabilité enregistre un versement
   effectivement reçu, son mode et sa référence. Le justificatif est facultatif.
   Les espèces sont comptées dans la trésorerie centrale, hors tiroir de réception.
4. Depuis un séjour en cours ou une facture émise, la réception ouvre
   **Régler avec le compte dépôt**. Saisir le montant, le nom de l'autorisateur
   chez le partenaire et la référence de son accord. Le justificatif est facultatif.
   L'affiliation du client ne déclenche jamais de règlement automatique.
5. Le solde et le journal sont disponibles dans le compte. **Éditions → Comptes
   dépôt partenaires** produit le relevé filtrable par partenaire et période, avec
   impression et exports existants. Pour un partenaire sélectionné, le relevé
   indique aussi les soldes initial et final de la période.

Un compte non encore approvisionné affiche zéro. Un partenaire sans convention ou
avec une convention expirée peut utiliser son compte ; un partenaire désactivé ne
peut plus être approvisionné ni régler de nouvelles factures. La comptabilité peut
toujours corriger son historique par contre-opération motivée. Un partenaire avec
compte ou documents archivés doit être désactivé, plutôt que supprimé.

## Règles et droits

| Opération | Droit | Attribution métier |
|---|---|---|
| Consulter comptes et relevés | `partners.wallet.voir` | Finance, comptabilité, contrôle, direction et réception |
| Approvisionner | `partners.wallet.credit` | RAF et comptabilité |
| Régler sur accord | `partners.wallet.debit` | Réception et responsable hébergement par héritage |
| Annuler par contre-opération | `partners.wallet.reverse` | RAF et comptabilité |
| Télécharger un justificatif | `partners.wallet.proof` | Personnel autorisé en consultation |
| Télécharger une convention | `settings.partners.attachments.voir` | Personnel autorisé en consultation |

La matrice centrale et ses niveaux de lecture seule restent la référence. Le
service métier vérifie également les droits d'écriture : un appel interne ne
contourne pas l'autorisation des routes.

Le serveur refuse un montant non entier ou non positif, un solde insuffisant, un
montant supérieur au reste à régler, une facture annulée ou brouillon, un client
appartenant à un autre partenaire et un accord incomplet. L'adaptateur hôtel
accepte les montants entiers en FCFA et les convertit dans l'unité interne du projet.

Une correction crée une écriture opposée et un règlement signé opposé, en gardant
l'original. Une seule annulation est autorisée par mouvement. Annuler un versement
déjà consommé est refusé si le disponible deviendrait négatif. Annuler une
utilisation rétablit le portefeuille et la dette du séjour ou de la facture,
y compris si la facture a été émise après le règlement initial. Une facture
complémentaire reste séparée du total payé de la facture du séjour.

## Trésorerie et grand livre

Exemple : versement de **10 000 000 FCFA**, puis utilisation de **500 000 FCFA** :
disponible **9 500 000 FCFA**, encaissements réels **10 000 000 FCFA**.

| Mouvement | Débit comptable | Crédit comptable | Registre des encaissements |
|---|---|---|---|
| Versement partenaire | Trésorerie selon le mode | 419000, auxiliaire partenaire | Dépôts partenaires |
| Règlement sur dépôt | 419000, auxiliaire partenaire | 411000, auxiliaire client | Aucun nouvel encaissement |
| Annulation | Écriture opposée | Écriture opposée | Opposé du versement si retour de fonds |

Le compte 419000 existe déjà dans le plan SYSCOHADA de l'application. Le versement
est une avance disponible ; il n'est pas classé dans les recettes d'hébergement.
L'utilisation du dépôt ne double ni la trésorerie ni les encaissements. Elle reste
un règlement du client et entre dans les indicateurs de prestations réglées
(analytics et reporting), alors que le versement initial en est exclu. Dans
l'exemple, ces indicateurs affichent 500 000 FCFA de prestations et le registre
10 000 000 FCFA d'argent reçu. Les écritures du grand livre suivent
le mécanisme existant de comptabilisation des paiements et de clôture journalière.

## Réutiliser le moteur

`App\Services\WalletService` ne dépend ni des partenaires, ni des séjours, ni des
factures, ni de `Payment`. Un portefeuille appartient à un modèle Eloquent
persisté, identifié par un entier, via `owner_type` / `owner_id`. Un même titulaire
peut avoir un portefeuille par devise. Le moteur n'effectue aucune conversion.

```php
use App\Services\WalletService;
use Illuminate\Support\Str;

$wallets = app(WalletService::class);
$wallet = $wallets->forOwner($customer, 'XAF'); // Ou un autre modèle métier.

// Unités entières internes : dans cette application, 100 unités = 1 FCFA.
$credit = $wallets->credit(
    $wallet, 100_000, (string) Str::uuid(),
    ['context' => 'autre_usage', 'reference' => 'VERSEMENT-001'], $user->id,
);
$debit = $wallets->debit(
    $wallet, 25_000, (string) Str::uuid(),
    ['context' => 'autre_usage', 'reference' => 'OPERATION-001'], $user->id,
);
$correction = $wallets->reverse(
    $debit, (string) Str::uuid(), ['reason' => 'Erreur de saisie'], $user->id,
);
$solde = $wallet->fresh()->balance;
```

Le moteur travaille exclusivement en entiers, sans unité d'affichage implicite.
La convention « centimes FCFA » est celle du projet ; un nouvel adaptateur définit
la conversion d'entrée et d'affichage adaptée à sa devise. Un transfert ou une
conversion entre portefeuilles n'est pas exposé par cette première version.

Chaque nouvelle intention reçoit un UUID. Conserver et réutiliser **le même UUID**
pour la répétition d'une même requête : portefeuille, montant, type, acteur,
mouvement annulé et métadonnées doivent être identiques. Le moteur renvoie alors
l'écriture existante. Réutiliser la clé pour une autre intention produit une
`DomainException`. Ne pas créer un nouvel UUID à chaque tentative réseau.

Le solde est verrouillé en transaction avant écriture. Solde et journal sont
enregistrés ensemble, avec montant signé et solde après mouvement. PostgreSQL
ajoute les contraintes de non-négativité et les clés uniques d'idempotence et
d'annulation. Les modifications et suppressions via le modèle `WalletEntry` sont
refusées. Les écritures SQL directes ou par query builder peuvent contourner les
événements Eloquent : les opérations applicatives doivent passer par le service.

Le moteur générique ne choisit aucun droit métier et ne crée aucun règlement.
Pour un nouveau contexte, créer un adaptateur analogue à `PartnerWalletService` :

- vérifier les droits, l'appartenance et l'état de la cible ;
- inclure mouvement et écritures métier dans **la même transaction**, sur la même
  connexion ; en cas de répétition, ne pas rejouer les effets déjà enregistrés ;
- stocker les justificatifs sur un disque privé et les servir par une route
  autorisée ; nettoyer les fichiers si la transaction échoue ;
- protéger la suppression du titulaire et conserver son historique ;
- traiter les `DomainException` comme des refus métier affichables à l'utilisateur.

L'adaptateur hôtel stocke les informations d'accord dans les métadonnées et relie
chaque écriture à un unique `Payment`. Il associe une empreinte du justificatif à
l'intention pour détecter une répétition avec une preuve différente. Les modèles
et le service constituent une API PHP interne réutilisable ; aucune API publique
supplémentaire ni dépendance externe n'est ajoutée.

## Livraison et compatibilité

La migration `2026_11_07_120000_create_partner_deposit_accounts` ajoute les tables
de portefeuille et de pièces jointes, le statut de convention et les liens aux
paiements. Les organisations existantes gardent `has_convention = true`, pour
préserver leurs conditions. Le nouveau formulaire propose par défaut une
organisation sans convention. Aucun solde initial n'est importé automatiquement.

L'export CSV ajoute une colonne facultative `convention`. Les anciens imports
restent acceptés ; un import ne modifie jamais le solde ni le journal du compte.

Livrer par le [circuit habituel des images et de la console](developpement.md#le-cycle-de-livraison).
L'entrypoint applique la migration lors de la mise à jour autorisée. Le nouveau
build embarque les limites PHP de `docker/uploads.ini` (10 Mo par fichier, 60 Mo
par requête) et la limite nginx de 60 Mo. Le stockage privé doit rester persistant
et être sauvegardé avec la base. Les configurations externes de proxy doivent
accepter ces tailles si elles imposent leur propre limite.

Ne pas revenir en arrière par suppression de ces tables après des mouvements
réels. Sauvegarder base et documents avant mise à jour ; conserver les données
financières et corriger vers l'avant. Le `down()` sert aux bases de développement
jetables. Aucun établissement en service n'a été migré ou déployé pendant le
développement de cette fonctionnalité.

## Vérification

Résultats du 7 novembre 2026 : **24 tests métier, 126 assertions, tous réussis
sur PostgreSQL** ; avec les sept tests des conventions existantes, **31 tests et
157 assertions réussissent sur PostgreSQL**. **101 tests réussissent** dans la sélection de régression SQLite,
avec les trois échecs antérieurs détaillés ci-dessous. Les trois scénarios de
concurrence PostgreSQL passent. La compilation Vite et le contrôle Pint des
nouveaux fichiers PHP passent également.
La migration a aussi été annulée puis réappliquée sur les bases jetables SQLite
et PostgreSQL ; les routes et vues ont été mises en cache sans erreur.

Les tests métier sont dans `tests/Feature/PartnerWalletTest.php`. Ils couvrent le
scénario 10 millions / 500 000, les droits des routes et du service, le refus des
opérations invalides, les répétitions, les contre-opérations, les preuves privées,
les erreurs de stockage, les conventions, le CSV, le grand livre et les factures
complémentaires. Ils exercent aussi un titulaire client hors contexte corporate.

```sh
php artisan test tests/Feature/PartnerWalletTest.php
npm run build
```

Pour vérifier les verrous réels, utiliser une **base PostgreSQL de test jetable**
déjà migrée, avec `APP_ENV=testing` et les variables `DB_*` correspondantes :

```sh
WALLET_CONCURRENCY_ALLOWED=1 php tests/Integration/WalletConcurrency.php
```

Ce script crée des données de test et lance des processus parallèles : deux
débits de 8 000 sur un solde de 10 000, deux crédits identiques simultanés et une
clé partagée entre deux portefeuilles. Il refuse une exécution sans environnement
de test PostgreSQL et sans autorisation explicite.

Recette navigateur effectuée sur des données fictives isolées : versement de
10 millions par la finance, règlement de 500 000 par la réception, relecture du
solde de 9,5 millions, ajout de deux versions de convention via la barre
d'enregistrement existante, inspection sur ordinateur et mobile.

Trois échecs de régression ont été reproduits sur l'état antérieur à ces
changements : un test de recherche de bon de commande dans `EditionsTest` et deux
tests de référence des droits dans `PermissionBaselineTest`. Ils concernent la
matrice déjà modifiée dans le dépôt. Les anciens droits de référence n'ont pas
été réécrits pour masquer ces écarts ; seules les nouvelles routes ont été ajoutées.

Références techniques officielles consultées le 7 novembre 2026 :
[verrous transactionnels Laravel 12](https://laravel.com/docs/12.x/queries#pessimistic-locking),
[stockage des fichiers](https://laravel.com/docs/12.x/filesystem#file-uploads),
[validation des fichiers](https://laravel.com/docs/12.x/validation#validating-files),
[affichage conditionnel Alpine](https://alpinejs.dev/directives/show).
