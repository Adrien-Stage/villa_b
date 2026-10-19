<?php

namespace App\Support;

/**
 * Le format du classeur Excel de l'économat, partagé par l'export et
 * l'import : noms d'onglets, colonnes, types de mouvement.
 *
 * Un onglet de données porte son titre en ligne 1, la ligne de marque en 2,
 * une consigne en 3, les en-têtes en 4 ; les lignes commencent en 5.
 * L'import retrouve les colonnes par leur en-tête : les déplacer ne gêne pas.
 */
final class ClasseurStock
{
    public const ONGLET_LISEZ_MOI    = 'Lisez-moi';
    public const ONGLET_ARTICLES     = 'Articles';
    public const ONGLET_MOUVEMENTS   = 'Mouvements';
    public const ONGLET_FOURNISSEURS = 'Fournisseurs';
    public const ONGLET_CATEGORIES   = 'Catégories';
    public const ONGLET_SYNTHESE     = 'Synthèse';
    public const ONGLET_LISTES       = 'Listes';

    public const LIGNE_ENTETE  = 4;
    public const PREMIERE_LIGNE = 5;

    /** Libellé de la cellule du Lisez-moi qui nomme le magasin du classeur. */
    public const ETIQUETTE_MAGASIN = 'Magasin';
    public const MAGASIN_CENTRAL   = 'Magasin central (économat)';
    public const PREFIXE_DEPOT     = 'Dépôt : ';

    public const TYPE_INITIAL    = 'Stock initial';
    public const TYPE_ENTREE     = 'Entrée';
    public const TYPE_SORTIE     = 'Sortie';
    public const TYPE_RETOUR     = 'Retour';
    public const TYPE_AJUSTEMENT = 'Ajustement';

    public const TYPES = [self::TYPE_INITIAL, self::TYPE_ENTREE, self::TYPE_SORTIE, self::TYPE_RETOUR, self::TYPE_AJUSTEMENT];

    /** Ce que chaque type fait au stock, pour le Lisez-moi. */
    public const TYPES_EXPLIQUES = [
        self::TYPE_INITIAL    => "Marchandise déjà en magasin au démarrage. Seulement avant tout autre mouvement de l'article.",
        self::TYPE_ENTREE     => 'Arrivée de marchandise (achat, livraison, colis). Coût unitaire et fournisseur conseillés.',
        self::TYPE_SORTIE     => 'Remise à un bénéficiaire (chantier, artisan, service). Destination / bénéficiaire obligatoire.',
        self::TYPE_RETOUR     => "Retour en stock de ce qui n'a pas servi.",
        self::TYPE_AJUSTEMENT => 'Correction après comptage : quantité positive (excédent) ou négative (manquant).',
    ];

    public const OUI = 'Oui';
    public const NON = 'Non';

    /** Colonnes saisies de l'onglet Articles, puis les colonnes calculées. */
    public const ARTICLES = [
        'id'          => ['N° (ne pas modifier)', 10],
        'reference'   => ['Référence', 16],
        'designation' => ['Désignation', 46],
        'categorie'   => ['Catégorie', 20],
        'unite'       => ['Unité', 10],
        'fournisseur' => ['Fournisseur habituel', 24],
        'description' => ['Description', 30],
        'stock_min'   => ['Stock minimum', 11],
        'actif'       => ['Actif', 8],
    ];

    public const ARTICLES_CALCULS = [
        'stock_initial' => ['Stock initial', 11],
        'entrees'       => ['Entrées', 10],
        'sorties'       => ['Sorties', 10],
        'retours'       => ['Retours', 10],
        'ajustements'   => ['Ajustements', 12],
        'stock'         => ['Stock actuel', 12],
        'cout'          => ['Coût moyen (FCFA)', 14],
        'valeur'        => ['Valeur (FCFA)', 15],
        'alerte'        => ['Alerte', 14],
    ];

    /** Colonnes de l'onglet Mouvements, saisies et calculées mêlées, dans l'ordre. */
    public const MOUVEMENTS = [
        'id'          => ['N° (ne pas modifier)', 10],
        'date'        => ['Date', 12],
        'reference'   => ['Référence', 16],
        'designation' => ['Désignation', 40],
        'unite'       => ['Unité', 9],
        'type'        => ['Type', 13],
        'quantite'    => ['Quantité', 10],
        'signe'       => ['Mouvement (±)', 12],
        'stock_apres' => ['Stock après', 11],
        'cout'        => ['Coût unitaire (FCFA)', 14],
        'valeur'      => ['Valeur (FCFA)', 14],
        'fournisseur' => ['Fournisseur', 22],
        'document'    => ['Document (BL, facture, colis)', 22],
        'destination' => ['Destination / bénéficiaire', 26],
        'pointe'      => ['Pointé', 8],
        'motif'       => ['Motif / observations', 36],
    ];

    /** Colonnes des mouvements calculées par formule : l'import les ignore. */
    public const MOUVEMENTS_CALCULS = ['designation', 'unite', 'signe', 'stock_apres', 'valeur'];

    public const FOURNISSEURS = [
        'nom'       => ['Nom', 30],
        'contact'   => ['Contact', 22],
        'telephone' => ['Téléphone', 16],
        'email'     => ['Email', 26],
        'adresse'   => ['Adresse', 30],
        'ville'     => ['Ville', 16],
        'notes'     => ['Notes', 30],
    ];

    public const CATEGORIES = [
        'nom' => ['Catégorie', 34],
    ];

    /** Clé de comparaison d'un en-tête : sans accents, casse ni ponctuation. */
    public static function cle(?string $texte): string
    {
        $texte = mb_strtolower(trim((string) $texte));
        $texte = strtr($texte, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '°' => '']);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $texte));
    }

    /** Le type de mouvement d'une saisie (« sortie », « ENTREE »…), ou null. */
    public static function type(?string $saisie): ?string
    {
        $cle = self::cle($saisie);

        foreach (self::TYPES as $type) {
            if (self::cle($type) === $cle) {
                return $type;
            }
        }

        return match ($cle) {
            'initial', 'stock de depart', 'reprise'         => self::TYPE_INITIAL,
            'entree en stock', 'achat', 'reception'          => self::TYPE_ENTREE,
            'sortie de stock'                                => self::TYPE_SORTIE,
            'retour en stock'                                => self::TYPE_RETOUR,
            'correction', 'inventaire'                       => self::TYPE_AJUSTEMENT,
            default                                          => null,
        };
    }
}
