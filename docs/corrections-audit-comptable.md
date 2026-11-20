# Corrections du module comptable WeTchah

## Parcours de recette pour l’hôtel

1. Activer le module Grand livre et ouvrir un exercice. Dans **Comptabilité générale → Plan de comptes**, vérifier les comptes proposés, ajouter les subdivisions et les centres propres à l’hôtel. Les montants sont conservés en centimes de FCFA. Les fichiers d’import demandent cette unité.
2. Dans **Paramètres → Taxes**, renseigner le classement, le NIU et les règles de retenue applicables avec leur date d’effet. Activer la taxe de séjour après avoir validé les éléments du dossier de l’établissement. Le barème livré applique la taxe par personne hébergée et par nuitée.
3. Réceptionner une commande en indiquant les lots des produits périssables. Sortir une partie du stock, saisir la facture fournisseur liée au bon, puis enregistrer un règlement partiel et son justificatif. Contrôler le reste dû et le mouvement de stock dans le grand livre. Une annulation de réception est refusée quand le lot a déjà été utilisé.
4. Au départ d’un client, comparer le détail facture, le solde du séjour et l’écriture. Vérifier la TVA, les centimes additionnels et la taxe de séjour sur les comptes distincts prévus. Pour vérifier un report au folio, rapprocher la pièce POS de l’écriture de vente et tracer l’encaissement sur la facture du séjour.
5. Dans **Échanges**, importer le relevé de banque ou de Mobile Money. Rapprocher une ligne puis retirer le pointage pour contrôler l’historique. Vérifier ensuite un versement opérateur en distinguant le brut, la commission et le net.
6. Dans **Livres et arrêté**, sortir le journal, la balance et le grand livre sur une période. Comparer les montants du CSV et du classeur. Pour un exercice de test terminé, verrouiller les mois, renseigner les notes et joindre les justificatifs d’inventaire avant de clôturer. Relire le dossier archivé.
7. Dans **Fiscalité**, rechercher une facture émise avec son numéro, préparer le dossier puis consigner un accusé obtenu sur le canal fiscal applicable. Les statuts d’acceptation et de rejet reposent sur la pièce déposée par le comptable.

## Suivi des 27 constats

| Constat | Traitement dans l’application | Vérification ou complément au pilote |
| --- | --- | --- |
| C01 · Plan SYSCOHADA | Comptes de complément, ajout de subdivisions et comptes collectifs, conservation du plan en vigueur sur le site. | Valider les subdivisions et les imputations propres à l’hôtel. |
| C02 · Partie double | Validation de l’équilibre à l’application et en base. Rejeu par source et schéma, y compris pour des dates de rejeu différentes. | Comparer les schémas aux pièces de l’hôtel. |
| C03 · Inaltérabilité | L’en-tête et les lignes validés sont protégés en SQLite et PostgreSQL. Identifiant du validateur conservé. Les liens d’extourne et événements de lettrage restent tracés. | Examiner l’historique avant la migration. Les anciennes validations restent consultables avec les champs historiquement disponibles. |
| C04 · Clôtures et corrections | Même verrou pour écritures, journal de nuit et périodes. Refus d’une extourne à date close, date d’origine conservée. Refus de verrouiller un mois qui contient des brouillons. | Planifier le contrôle des périodes échues et vérifier les dates de valeur lors de la reprise. |
| C05 · Livres | Éditions par période en PDF et Excel. Journal CSV en centimes. Numéros d’écriture stables, références, dates et soldes. | Comparer les éditions et les états existants sur un mois complet. |
| C06 · Inventaire annuel | Livre d’inventaire issu des soldes de patrimoine, justificatifs joints et archivés à l’arrêté. | Contrôler l’inventaire physique, le détail des tiers et les justifications de chaque solde. |
| C07 · Conservation | Arrêté et justificatifs chiffrés, empreintes vérifiées à la lecture, échéance de conservation à dix ans. | Assurer la disponibilité de la clé et des copies pendant la durée de conservation. Les pièces courantes hors base relèvent aussi de la sauvegarde. |
| C08 · États du système normal | Projet de bilan et résultat par compte, comparatif disponible, flux à ventiler et notes annexes. Archive figée après validation. | Revoir la ventilation, les reclassements, la présentation des annexes et les totaux avec le cabinet. |
| C09 · SMT et DSF | Projet SMT et préremplissage d’un classeur DGI fourni, à partir d’une balance d’exercice archivée et de correspondances explicites. | Valider l’éligibilité au SMT, le modèle du millésime, les correspondances et les contrôles du portail. |
| C10 · Fournisseurs | Règlement partiel ou total par pièce, avances en 409100 et imputation d’une avance sur facture. Solde historique et journal de lettrage, avec garde-fous sur le dépassement. | Vérifier les échéances et règles d’affectation du pilote. |
| C11 · TVA et CAC | Ventilation conservée à la facturation et sur les ventes POS couvertes. Taux particuliers datés et justification par ligne du folio. | Examiner remises, avoirs et anciens documents créés avant la capture de la ventilation. |
| C12 · Taxe de séjour | Barème 2026 et assiette par personne et nuitée. Calcul dans le parcours de réception et à la facture. Dette distincte en 447100 et état périodique. | Vérifier les canaux de réservation, les cas de séjour variables et les pièces de reversement. |
| C13 · Retenues | Les anciens taux provisoires sont retirés. La saisie exige une règle en vigueur et son texte de référence. NIU fournisseur exigible selon la règle. | Valider les catégories, les taux, les seuils et la périodicité propres à l’hôtel. |
| C14 · Timbre | Comptabilisation distincte d’un timbre perçu, rattachée au paiement, au texte applicable et à la pièce de perception. | Déterminer l’assujettissement et le montant sur les pièces réelles. L’application en caisse dépend des règles et exceptions validées. |
| C15 · Facturation électronique | Préparation d’un dossier de facture avec NIU, ventilation, empreinte, suivi des réponses et conservation de l’accusé. | Raccorder un prestataire ou le canal DGI officiel et vérifier émission, rejet, certification et avoir en environnement de recette. |
| C16 · Déclarations | Exports préparatoires avec comptes, mouvements et références pour TVA/CAC, retenues, taxe de séjour, impôts et cotisations paramétrés sur les comptes dédiés. | Valider les bases, crédits, impositions, rubriques et formats selon le régime de l’établissement. |
| C17 · Paie | Import d’écritures issues d’une paie validée, équilibrées et rejouables. | Relier l’outil de paie et tester la paie permanente, saisonnière et les déclarations CNPS. |
| C18 · Flux hôteliers | Vente et règlement distingués pour réception, buffet et banquet. Les acomptes de banquet sont imputés quand la vente est reconnue. Rapprochement des reports au folio. | Tester remboursements, annulations, avances historiques et écarts du dossier journalier sur des pièces représentatives. |
| C19 · Analytique | Centres paramétrables et centres hôteliers dédiés. Reflet analytique corrigé par extourne. Ventilation via les lignes d’OD et rapprochement par centre. | Valider la règle d’affectation des charges partagées avec le contrôle de gestion. |
| C20 · Stocks et périssables | Comptabilisation par mouvement avec compléments. Lots datés à la réception et sortie FEFO au magasin central. | Vérifier la provenance des anciens lots journaliers. Étendre le suivi fin des lots dans les dépôts aval, le garde-manger et les mini-bars selon le pilote. |
| C21 · Immobilisations | Registre lié à la ligne d’acquisition, mise en service, durée, dotation linéaire, sortie et valeur comptable. | Vérifier les plans, les comptes et la reprise des amortissements antérieurs. La cession saisie suppose un encaissement par banque. |
| C22 · Moyens de paiement | Moyens existants conservés. Référence de paiement, justificatif et versement opérateur rapprochables. | Valider les sous-comptes souhaités pour chaque prestataire. |
| C23 · Commissions et change | Justificatif de change et contrôle décimal de la conversion XAF. Comptabilisation du net et des frais par paiement. | Étendre le rapprochement aux remises groupées et aux écarts de change entre dates de transaction et de valeur. |
| C24 · Échanges et banque | Imports CSV/XLSX, export du journal, vérification par compte et montant, sélection et annulation de pointage tracées. | Faire correspondre le format de la banque de l’hôtel. Valider le traitement des écritures groupées et des écarts. |
| C25 · Protection | Droits au sein du module, réponses privées sans cache. Purge des anciens caches de navigation. Dossiers d’arrêté et pièces fiscales chiffrés. | Vérifier TLS, chiffrement des volumes et sauvegardes, et rotation des clés sur l’installation réelle. |
| C26 · Restauration | Essai isolé PostgreSQL avec la base, un fichier externe et une archive d’arrêté. La commande `php artisan ledger:verify-archive ANNEE` a relu les huit éditions et le justificatif d’inventaire. | Mesurer le délai de reprise et la perte de données observée lors d’une restauration de l’installation de l’hôtel. |
| C27 · Hors connexion | Brouillon d’OD chiffré localement. À la reconnexion, même contrôle d’accès, de dates, d’équilibre et de doublons à l’import. | Ouvrir Échanges sur le poste avant de travailler hors ligne. Tester la coupure et la reprise dans le même onglet et navigateur. |

## Formats d’échange

- **Relevé**. `date;reference;montant_centimes_xaf;libelle`. Un crédit reçu sur le compte vaut un montant positif. Un débit vaut un montant négatif. Un solde de début ou de fin se conserve sur le relevé justificatif.
- **Opérations diverses et paie**. `date;piece;compte;libelle;debit_centimes_xaf;credit_centimes_xaf;date_origine;centre`. Les lignes d’une même pièce partagent la date comptable et la même date d’origine. La date d’origine et le centre peuvent rester vides. Le lot entier est rejeté si une pièce est déséquilibrée ou si la période est close.
- **Correspondances DSF**. `feuille;cellule;prefixe_compte;valeur`. Le champ valeur peut prendre `debit`, `credit`, `opening`, `closing` ou `closing_credit`. Le préfixe sélectionne les comptes de la balance archivée. Les valeurs sont écrites en FCFA entiers. Les cellules qui contiennent une formule ou un libellé sont protégées.

Les dates suivent le format AAAA-MM-JJ. Le CSV utilise des points-virgules et l’encodage UTF-8. Pour un import XLSX, garder une seule feuille contenant les données de l’échange.

## Avant mise en service

- Vérifier les extournes historiques répétées qui pointent vers la même écriture. La migration d’inaltérabilité signale ce cas avant de modifier le schéma. Rapprocher au préalable les lots journaliers historiques dont le détail n’était pas enregistré.
- Vérifier le paramètre de taxe de séjour sur les établissements existants avant la mise en service. Une taxe activée alimente désormais le montant dû et la facture dans le parcours de réception.
- Conserver la clé de chiffrement Laravel et les clés de lecture précédentes requises avec la procédure de reprise. Restaurer la base et les pièces privées dans un environnement séparé, puis relire les archives avec `ledger:verify-archive`.
- Essayer les états financiers et les formats fiscaux avec l’expert-comptable, sur un exercice et des opérations représentatives de l’hôtel. Le modèle DSF, les annexes et le connecteur agréé dépendent du régime, du millésime et du canal de transmission applicables.

## Vérifications effectuées

- `vendor/bin/pest --parallel --processes=4` sur SQLite. 1 526 tests réussis, 1 ignoré et 8 987 assertions.
- Tests ciblés sur PostgreSQL 16. 76 tests réussis dont les protections du journal, le pointage, les lots de stock, les traitements fiscaux et les permissions. La modification et la suppression d’un brouillon ont aussi été vérifiées sur une base isolée.
- `npm run build`, contrôle de syntaxe des fichiers modifiés et `git diff --check`.
- Sauvegarde et restauration d’un dossier fictif dans une autre base PostgreSQL. Les huit éditions, le justificatif archivé, le bon de réception externe et l’égalité débit/crédit ont été vérifiés après la reprise.
- Vérification du script de brouillon avec Web Crypto. Chiffrement, rejet d’une phrase secrète incorrecte, séparation de deux comptes et réinjection du CSV dans le formulaire.

## Références

- [Acte uniforme OHADA relatif au droit comptable et à l’information financière](https://www.ohada.org/acte-uniforme-relatif-au-droit-comptable-et-a-linformation-financiere-audcif/).
- [Code général des impôts 2026 · DGI Cameroun](https://www.impots.cm/index.php/en/node/1333).
- [Déclarations statistiques et fiscales · formats et annexes DGI](https://www.impots.cm/fr/declarations-statistiques-et-fiscalesdsf).
- [CNPS Cameroun · obligations de l’employeur](https://www.cnps.cm/fr/component/content/article/employeurs.html?Itemid=203&catid=25).
- [Guide logiciel de comptabilité OHADA · SangoCompta](https://sangocompta.com/guides/logiciel-comptabilite-ohada/).
- [Guide comptabilité OHADA · SynkriaOps](https://www.synkriaops.com/blog/articles/comptabilite-ohada-guide-complet/).
