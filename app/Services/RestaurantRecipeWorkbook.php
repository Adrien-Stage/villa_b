<?php

namespace App\Services;

use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Models\Tenant;
use App\Services\Concerns\HabilleLeClasseur;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Classeur des fiches techniques de cuisine, sur le modèle du document de
 * référence de la restauration : un tableau de bord de la carte, la mercuriale
 * des ingrédients, puis une fiche complète par préparation de base et par plat.
 *
 * Même principe que le classeur de l'hébergement, dont il partage l'habillage :
 * le document est vivant, pas figé. Les quantités nettes, les coûts de ligne,
 * le food cost et les marges sont des formules qui pointent les cellules de la
 * fiche. Le chef peut donc corriger une quantité ou un prix de vente dans Excel
 * et voir la rentabilité du plat se recalculer sous ses yeux.
 *
 * Les coûts unitaires, eux, restent des valeurs : ce sont les coûts moyens
 * pondérés du garde-manger, la vérité de la plateforme. La colonne des
 * commentaires nomme leur origine pour qu'on ne les corrige pas dans Excel en
 * croyant agir sur le stock.
 */
class RestaurantRecipeWorkbook
{
    use HabilleLeClasseur;

    /** Feuille de fiche : lignes fixes du haut, avant la composition. */
    private const LIGNE_NOM      = 4;
    private const LIGNE_CODE     = 5;
    private const LIGNE_RENDEMENT = 6;
    private const LIGNE_PORTION  = 7;
    private const LIGNE_PORTIONS = 8;

    // Fiche de plat : l'identification tient sur six lignes au lieu de cinq.
    private const LIGNE_PLAT_NOM       = 4;
    private const LIGNE_PLAT_CATEGORIE = 5;
    private const LIGNE_PLAT_PORTIONS  = 6;
    private const LIGNE_PLAT_PRIX      = 7;
    private const LIGNE_PLAT_TVA       = 8;
    private const LIGNE_PLAT_CIBLE     = 9;

    /**
     * Food cost cible, part du prix de vente hors taxes.
     *
     * La norme de la profession tient entre 25 % et 35 % ; 30 % est la valeur
     * du document de référence. C'est un point de départ, pas un dogme : la
     * cellule est vivante, et le prix de vente théorique se recalcule dès que
     * le chef y inscrit sa propre cible.
     */
    private const CIBLE_FOOD_COST = 0.30;

    /** Codes de mercuriale attribués aux articles du garde-manger. @var array<int,string> */
    private array $codes = [];

    public function __construct(private TaxationService $taxation)
    {
    }

    /**
     * @param  Collection<int,RestaurantRecipe>  $recettes
     * @param  Collection<int,RestaurantPantryItem>  $gardeManger
     */
    public function build(Collection $recettes, Collection $gardeManger, ?Tenant $tenant = null): Spreadsheet
    {
        $this->c = $this->palette($tenant);

        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);

        $this->proprietes($classeur, $tenant, 'Fiches techniques de cuisine', 'Food cost et rentabilité de la carte');

        $ingredients = $this->mercuriale($recettes, $gardeManger);
        $this->codes = $this->codes($ingredients);

        // Les préparations avant les plats : un plat consomme une préparation,
        // et le lecteur doit pouvoir remonter la chaîne dans l'ordre des onglets.
        $preparations = $recettes->filter(fn (RestaurantRecipe $r) => $r->isPreparation())->values();
        $plats        = $recettes->reject(fn (RestaurantRecipe $r) => $r->isPreparation())->values();

        // Portions de référence des préparations : ce que les plats en tirent
        // réellement. Calculé avant les fiches, qui l'affichent.
        $portions = $this->portionsDeReference($plats);

        $ongletsPrep = [];
        foreach ($preparations as $index => $preparation) {
            $ongletsPrep[$preparation->id] = $this->feuillePreparation($classeur, $preparation, $tenant, $index + 1, $portions);
        }

        $reperes = [];
        foreach ($plats as $plat) {
            $reperes[] = $this->feuillePlat($classeur, $plat, $tenant, $ongletsPrep);
        }

        // Insérées en tête une fois les fiches écrites : elles ne peuvent
        // pointer que des cellules dont elles connaissent l'adresse exacte.
        $this->feuilleTableauDeBord($classeur, $reperes, $tenant);
        $this->feuilleMercuriale($classeur, $ingredients, $tenant);

        $classeur->setActiveSheetIndex(0);
        $classeur->getActiveSheet()->setSelectedCell('A1');

        return $classeur;
    }

    // ── Onglet « fiche de préparation » ──────────────────────────────────────

    /**
     * Une fiche de production pour une préparation de base (sauce, fond, marinade).
     *
     * @param  array<int,float>  $portions
     * @return string  nom de l'onglet
     */
    private function feuillePreparation(Spreadsheet $classeur, RestaurantRecipe $recette, ?Tenant $tenant, int $rang, array $portions): string
    {
        $ws = $classeur->createSheet();
        $ws->setTitle($this->nomOnglet($classeur, '🥣 Prep - ' . $recette->name));

        $this->largeursFiche($ws);

        $unite = $recette->producedItem?->unit ?? 'g';
        $rendement = $recette->yield();
        $portion = $portions[$recette->producedItem?->id] ?? null;
        $origine = $portion !== null
            ? 'Portion moyenne consommée par les plats'
            : "Aucun plat n'en consomme encore — portion = batch entier";
        $portion ??= $rendement;

        $this->bandeau(
            $ws, 'A1:I1',
            $this->titreSigne($tenant, 'FICHE TECHNIQUE DE PRODUCTION (BASE / SOUS-RECETTE) — ' . mb_strtoupper($recette->name)),
            $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 38
        );
        $this->ligneMarque($ws, 'A2', $tenant);

        // 1. Informations générales
        $this->section($ws, 'A3:C3', '1. INFORMATIONS GÉNÉRALES & RENDEMENT');

        $this->ligneEntete($ws, self::LIGNE_NOM, 'Désignation de la préparation', $recette->name, null, null);
        $this->ligneEntete($ws, self::LIGNE_CODE, 'Code Article / Référence', $this->codePreparation($recette, $rang), null, null);
        $this->ligneEntete($ws, self::LIGNE_RENDEMENT, 'Rendement total brut produit', $rendement, $this->formatQuantite($rendement), $this->uniteLongue($unite, $rendement), true);
        $this->ligneEntete($ws, self::LIGNE_PORTION, 'Portion de référence', $portion, $this->formatQuantite($portion), $unite . ' / portion — ' . $origine);
        $this->ligneEntete(
            $ws, self::LIGNE_PORTIONS, 'Nombre de portions obtenues',
            '=IFERROR(B' . self::LIGNE_RENDEMENT . '/B' . self::LIGNE_PORTION . ',0)',
            '#,##0.0', 'Portions de ' . $this->nombre($portion) . ' ' . $unite
        );

        // 2. Composition
        $this->section($ws, 'A10:I10', '2. COMPOSITION MATIÈRE PREMIÈRE & COÛT DE PRODUCTION');
        $this->enteteTableau($ws, 11, [
            'Réf Ingrédient', 'Ingrédient / Matière', 'Quantité Brute', 'Unité', 'Perte / Freinte (%)',
            'Quantité Nette', 'Coût Unitaire (FCFA)', 'Coût Total Ligne (FCFA)', 'Commentaires',
        ], 30, true);

        $premiere = 12;
        $ligne = $this->composition($ws, $recette, $premiere, 1.0, false, []);
        $derniere = $ligne - 1;

        $ligneTotal = $ligne;
        $brutes = $recette->lines->map(fn (RestaurantRecipeLine $l) => $l->grossQuantity())->all();
        $this->ligneTotalComposition(
            $ws, $ligneTotal, 'TOTAL DE LA PRÉPARATION', $premiere, $derniere, $unite, true,
            $this->formatQuantite(array_sum($brutes), ...$brutes)
        );

        // 3. Ratios de coût
        $ligneSection3 = $ligneTotal + 2;
        $this->section($ws, "A{$ligneSection3}:I{$ligneSection3}", '3. RATIOS DE COÛT DE LA PRÉPARATION');

        $batch = "H{$ligneTotal}";
        $rend  = '$B$' . self::LIGNE_RENDEMENT;
        $port  = '$B$' . self::LIGNE_PORTION;

        $ratios = [
            [
                'COÛT TOTAL DU BATCH (' . $this->nombre($rendement) . ' ' . $unite . ')',
                "={$batch}", self::FMT_FCFA,
                'Coût matière pour la totalité du rendement produit',
                true,
            ],
        ];

        // Le coût au kilo ou au litre ne veut rien dire pour un article compté
        // à la pièce : on ne l'affiche que là où il se lit.
        if ($multiple = $this->multiple($unite)) {
            [$facteur, $libelle, $lecture] = $multiple;
            $ratios[] = [
                $libelle, "=IFERROR({$batch}/{$rend}*{$facteur},0)", self::FMT_FCFA,
                $lecture, false,
            ];
        }

        $ratios[] = [
            "COÛT À L'UNITÉ (1 " . $unite . ')',
            "=IFERROR({$batch}/{$rend},0)", self::FMT_FCFA_FIN,
            "Base d'imputation dans les fiches de plats",
            false,
        ];
        $ratios[] = [
            'COÛT PAR PORTION DE ' . $this->nombre($portion) . ' ' . $unite,
            "=IFERROR({$batch}/{$rend}*{$port},0)", self::FMT_FCFA,
            'Coût de la préparation pour un plat servi',
            true,
        ];

        $ligne = $ligneSection3 + 1;
        foreach ($ratios as [$libelle, $formule, $format, $lecture, $cle]) {
            $this->ligneRatio($ws, $ligne, $libelle, $formule, $format, $lecture, $cle);
            $ligne++;
        }

        // 4. Mode opératoire — seulement s'il y a quelque chose à dire.
        $this->modeOperatoire($ws, $ligne + 1, $recette, 'MODE OPÉRATOIRE & NOTES DE PRODUCTION', false);

        $ws->setSelectedCell('A1');

        return $ws->getTitle();
    }

    // ── Onglet « fiche de plat » ─────────────────────────────────────────────

    /**
     * Une fiche complète pour un plat de la carte.
     *
     * @param  array<int,string>  $ongletsPrep
     * @return array<string,mixed>  adresses utiles au tableau de bord
     */
    private function feuillePlat(Spreadsheet $classeur, RestaurantRecipe $recette, ?Tenant $tenant, array $ongletsPrep): array
    {
        $ws = $classeur->createSheet();
        $ws->setTitle($this->nomOnglet($classeur, '🍽️ Plat - ' . $recette->name));

        $this->largeursFiche($ws);

        $plat = $recette->menuItem;
        $portions = $recette->yield();
        $prix = round((int) ($plat?->price ?? 0) / 100, 2);

        $this->bandeau(
            $ws, 'A1:I1',
            $this->titreSigne($tenant, 'FICHE TECHNIQUE DE METS — ' . mb_strtoupper($recette->name)),
            $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 38
        );
        $this->ligneMarque($ws, 'A2', $tenant);

        // 1. Identification
        $this->section($ws, 'A3:C3', '1. IDENTIFICATION DU PLAT & SERVICE');

        $this->ligneEntete($ws, self::LIGNE_PLAT_NOM, 'Nom commercial du plat', $plat?->name ?: $recette->name, null, null);
        $this->ligneEntete($ws, self::LIGNE_PLAT_CATEGORIE, 'Catégorie Menu', $plat?->category?->name ?: 'Non classé', null, null);
        $this->ligneEntete($ws, self::LIGNE_PLAT_PORTIONS, 'Nombre de portions / Rendement', $portions, $this->formatQuantite($portions), 'Couverts / Assiette');
        $this->ligneEntete($ws, self::LIGNE_PLAT_PRIX, 'Prix de Vente Conseillé (TTC)', $prix, self::FMT_FCFA, 'FCFA / portion', true);
        $this->ligneEntete($ws, self::LIGNE_PLAT_TVA, 'Taux de TVA / Taxes applicables', $this->tauxTva(), self::FMT_TAUX, $this->mentionTva());
        $this->ligneEntete($ws, self::LIGNE_PLAT_CIBLE, 'Coût matière cible (Food Cost %)', self::CIBLE_FOOD_COST, self::FMT_TAUX, 'Objectif professionnel : entre 25 % et 35 %');

        // 2. Composition de la portion
        $this->section($ws, 'A11:I11', '2. COMPOSITION DE LA PORTION & CALCUL DU COÛT MATIÈRE (FOOD COST)');
        $this->enteteTableau($ws, 12, [
            'Composant', 'Ingrédient / Sous-ensemble', 'Quantité Brute', 'Unité', 'Perte / Déchet (%)',
            'Quantité Nette (Servie)', 'Coût Unitaire (FCFA)', 'Coût Portion (FCFA)', 'Commentaires',
        ], 30, true);

        $premiere = 13;
        $ligne = $this->composition($ws, $recette, $premiere, $portions, true, $ongletsPrep);
        $derniere = $ligne - 1;

        $ligneTotal = $ligne;
        $this->ligneTotalComposition($ws, $ligneTotal, 'TOTAL COÛT DE REVIENT MATIÈRE (FOOD COST)', $premiere, $derniere, '', false);

        // 3. Synthèse
        $ligneSection3 = $ligneTotal + 2;
        $this->section($ws, "A{$ligneSection3}:I{$ligneSection3}", '3. SYNTHÈSE DE GESTION, RATIOS & RENTABILITÉ DU PLAT');

        $fc    = "H{$ligneTotal}";
        $pv    = '$B$' . self::LIGNE_PLAT_PRIX;
        $cible = '$B$' . self::LIGNE_PLAT_CIBLE;

        $syntheses = [
            ['COÛT DE REVIENT MATIÈRE (FOOD COST)', "={$fc}", self::FMT_FCFA, 'Somme des coûts ingrédients', 'Coût direct des ingrédients par portion', true],
            ['PRIX DE VENTE CONSEILLÉ (TTC)', "={$pv}", self::FMT_FCFA, 'Prix de vente affiché au menu', 'Prix payé par le client au restaurant', false],
            ['MARGE BRUTE RESTAURANT (PAR COUVERT)', "={$pv}-{$fc}", self::FMT_FCFA, 'Prix de vente TTC - Food Cost', 'Contribution brute à la couverture des frais et bénéfice', true],
            ['RATIO FOOD COST RÉEL (%)', "=IFERROR({$fc}/{$pv},0)", self::FMT_TAUX, 'Food Cost / Prix de Vente', 'Objectif professionnel : entre 25 % et 35 %', false],
            ['TAUX DE MARGE BRUTE (%)', "=IFERROR(({$pv}-{$fc})/{$pv},0)", self::FMT_TAUX, 'Marge Brute / Prix de Vente', 'Part du prix restant après coût matière', false],
            ['COEFFICIENT MULTIPLICATEUR (PRIX/COÛT)', "=IFERROR({$pv}/{$fc},0)", '0.00', 'Prix de Vente / Food Cost', 'Norme hôtellerie/restauration : 3.0 à 4.5', false],
            ['PRIX DE VENTE THÉORIQUE (RATIO CIBLE)', "=IFERROR({$fc}/{$cible},0)", self::FMT_FCFA, 'Food Cost / Ratio cible', 'Prix recommandé selon la norme cible', false],
        ];

        $ligne = $ligneSection3 + 1;
        foreach ($syntheses as [$libelle, $formule, $format, $calcul, $lecture, $cle]) {
            $this->ligneSynthese($ws, $ligne, $libelle, $formule, $format, $calcul, $lecture, $cle);
            $ligne++;
        }

        // 4. Dressage
        $this->modeOperatoire($ws, $ligne + 1, $recette, 'INSTRUCTIONS DE DRESSAGE & BONNES PRATIQUES EN CUISINE', true);

        $ws->setSelectedCell('A1');

        return [
            'onglet'    => $ws->getTitle(),
            'nom'       => 'B' . self::LIGNE_PLAT_NOM,
            'categorie' => 'B' . self::LIGNE_PLAT_CATEGORIE,
            'portions'  => 'B' . self::LIGNE_PLAT_PORTIONS,
            'couverts'  => $portions,
            'prix'      => 'B' . self::LIGNE_PLAT_PRIX,
            'food_cost' => 'D' . ($ligneSection3 + 1),
            'marge'     => 'D' . ($ligneSection3 + 3),
            'ratio'     => 'D' . ($ligneSection3 + 4),
            'coeff'     => 'D' . ($ligneSection3 + 6),
        ];
    }

    // ── Corps commun des deux fiches ─────────────────────────────────────────

    /**
     * Le tableau de composition, identique pour une préparation et pour un plat :
     * seule change la première colonne — une référence de mercuriale d'un côté,
     * la nature du composant de l'autre — et la division par le rendement, qui
     * ramène un plat à la portion.
     *
     * @param  array<int,string>  $ongletsPrep
     * @return int  première ligne libre après le tableau
     */
    private function composition(Worksheet $ws, RestaurantRecipe $recette, int $premiere, float $diviseur, bool $parPortion, array $ongletsPrep): int
    {
        $lignes = $recette->lines;

        if ($lignes->isEmpty()) {
            // Une fiche vide se dit : sinon un food cost à zéro se lit comme un
            // plat qui ne coûte rien à produire.
            $ws->getRowDimension($premiere)->setRowHeight(20);
            $ws->mergeCells("A{$premiere}:G{$premiere}");
            $ws->setCellValue("A{$premiere}", 'Aucun ingrédient saisi pour cette fiche');
            $this->style($ws, "A{$premiere}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $ws->setCellValue("H{$premiere}", 0);
            $this->style($ws, "H{$premiere}", ['taille' => 10, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $ws->setCellValue("I{$premiere}", 'À compléter depuis la plateforme');
            $this->style($ws, "I{$premiere}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $this->bordures($ws, "A{$premiere}:I{$premiere}");

            return $premiere + 1;
        }

        $ligne = $premiere;
        foreach ($lignes as $composant) {
            $this->ligneComposant($ws, $ligne, $composant, $diviseur, $parPortion, $ongletsPrep);
            $ligne++;
        }

        return $ligne;
    }

    /**
     * Un composant et ses formules.
     *
     * La quantité brute est celle qui sort réellement du stock : la quantité
     * nette de la recette, majorée de la perte au parage. La quantité servie
     * s'en déduit par formule, si bien que corriger une freinte dans Excel
     * corrige aussi le poids annoncé à l'assiette.
     *
     * @param  array<int,string>  $ongletsPrep
     */
    private function ligneComposant(Worksheet $ws, int $ligne, RestaurantRecipeLine $composant, float $diviseur, bool $parPortion, array $ongletsPrep): void
    {
        $article = $composant->item;
        $diviseur = $diviseur > 0 ? $diviseur : 1.0;

        // La freinte n'est reprise que dans sa plage utile : hors de [0, 100[,
        // le modèle lui-même l'ignore et la brute vaut la nette.
        $perte = (float) $composant->waste_percent;
        $perte = ($perte > 0 && $perte < 100) ? $perte : 0.0;

        $brute = $composant->grossQuantity() / $diviseur;
        $cout  = round((float) ($article?->average_cost ?? 0) / 100, 4);

        $ws->getRowDimension($ligne)->setRowHeight(20);

        $ws->setCellValue("A{$ligne}", $parPortion ? $this->composantLabel($article) : ($this->codes[$article?->id] ?? '—'));
        $ws->setCellValue("B{$ligne}", $article?->name ?? 'Ingrédient supprimé');
        $ws->setCellValue("C{$ligne}", round($brute, 4));
        $ws->setCellValue("D{$ligne}", $article?->unit ?? '');
        $ws->setCellValue("E{$ligne}", $perte / 100);
        $ws->setCellValue("F{$ligne}", "=C{$ligne}*(1-E{$ligne})");
        $ws->setCellValue("G{$ligne}", $cout);
        $ws->setCellValue("H{$ligne}", "=C{$ligne}*G{$ligne}");
        $ws->setCellValue("I{$ligne}", $this->commentaireComposant($composant, $article, $ongletsPrep));

        $this->style($ws, "A{$ligne}:B{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $format = $this->formatQuantite($brute, $brute * (1 - $perte / 100));
        $this->style($ws, "C{$ligne}", ['taille' => 10, 'format' => $format, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "D{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "E{$ligne}", ['taille' => 10, 'format' => self::FMT_TAUX, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "F{$ligne}", ['taille' => 10, 'format' => $format, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "G{$ligne}", ['taille' => 10, 'format' => self::FMT_FCFA_FIN, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "H{$ligne}", ['taille' => 10, 'gras' => true, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "I{$ligne}", ['taille' => 9, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:I{$ligne}");
    }

    /** Ligne de total du tableau de composition. */
    private function ligneTotalComposition(Worksheet $ws, int $ligne, string $libelle, int $premiere, int $derniere, string $unite, bool $quantites, string $format = '#,##0'): void
    {
        $ws->getRowDimension($ligne)->setRowHeight(22);

        // Une préparation totalise aussi ses masses — c'est le contrôle de
        // rendement du chef. Un plat ne totalise que de l'argent : additionner
        // des grammes et des pièces dans une assiette n'aurait aucun sens.
        if ($quantites) {
            $ws->mergeCells("A{$ligne}:B{$ligne}");
            $ws->setCellValue("A{$ligne}", $libelle);
            $ws->setCellValue("C{$ligne}", "=SUM(C{$premiere}:C{$derniere})");
            $ws->setCellValue("D{$ligne}", $unite);
            $ws->setCellValue("F{$ligne}", "=SUM(F{$premiere}:F{$derniere})");

            $this->style($ws, "A{$ligne}:B{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "C{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'format' => $format, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "D{$ligne}:E{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "F{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'format' => $format, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "G{$ligne}", ['fond' => $this->c['total']]);
        } else {
            $ws->mergeCells("A{$ligne}:G{$ligne}");
            $ws->setCellValue("A{$ligne}", $libelle);
            $this->style($ws, "A{$ligne}:G{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        }

        $ws->setCellValue("H{$ligne}", "=SUM(H{$premiere}:H{$derniere})");
        $this->style($ws, "H{$ligne}", [
            'taille' => 11, 'gras' => true, 'couleur' => $this->c['rouge'], 'fond' => $this->c['total'],
            'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true,
        ]);
        $this->style($ws, "I{$ligne}", ['fond' => $this->c['total']]);

        $this->bordures($ws, "A{$ligne}:I{$ligne}");
        $ws->getStyle("A{$ligne}:I{$ligne}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
    }

    /** Ligne d'en-tête de fiche : libellé, valeur, unité de lecture. */
    private function ligneEntete(Worksheet $ws, int $ligne, string $libelle, mixed $valeur, ?string $format, ?string $unite, bool $gras = false): void
    {
        $ws->getRowDimension($ligne)->setRowHeight(20);
        $ws->setCellValue("A{$ligne}", $libelle);
        $this->style($ws, "A{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'vertical' => true]);

        if ($format === null) {
            $ws->setCellValueExplicit("B{$ligne}", (string) $valeur, DataType::TYPE_STRING);
            $this->style($ws, "B{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        } else {
            $ws->setCellValue("B{$ligne}", $valeur);
            $this->style($ws, "B{$ligne}", [
                'taille' => 10,
                'gras' => $gras,
                'format' => $format,
                'align' => Alignment::HORIZONTAL_RIGHT,
                'vertical' => true,
            ]);
        }

        $ws->setCellValue("C{$ligne}", $unite ?? '');
        $this->style($ws, "C{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:C{$ligne}");
    }

    /** Ligne de la synthèse d'un plat : indicateur, valeur, mode de calcul, lecture. */
    private function ligneSynthese(Worksheet $ws, int $ligne, string $libelle, string $formule, string $format, string $calcul, string $lecture, bool $cle): void
    {
        $fond = $cle ? $this->c['cle'] : $this->c['blanc'];

        $ws->getRowDimension($ligne)->setRowHeight(22);
        $ws->mergeCells("A{$ligne}:C{$ligne}");
        $ws->mergeCells("F{$ligne}:I{$ligne}");

        $ws->setCellValue("A{$ligne}", $libelle);
        $ws->setCellValue("D{$ligne}", $formule);
        $ws->setCellValue("E{$ligne}", $calcul);
        $ws->setCellValue("F{$ligne}", $lecture);

        $this->style($ws, "A{$ligne}:C{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $fond, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->style($ws, "D{$ligne}", [
            'taille' => $cle ? 12 : 10, 'gras' => true,
            'couleur' => $cle ? $this->c['section'] : $this->c['encre'],
            'fond' => $fond, 'format' => $format,
            'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true,
        ]);
        $this->style($ws, "E{$ligne}", ['taille' => 9, 'fond' => $fond, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "F{$ligne}:I{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:I{$ligne}");
    }

    /** Ligne de ratio d'une préparation : indicateur, valeur, lecture. */
    private function ligneRatio(Worksheet $ws, int $ligne, string $libelle, string $formule, string $format, string $lecture, bool $cle): void
    {
        $fond = $cle ? $this->c['cle'] : $this->c['blanc'];

        $ws->getRowDimension($ligne)->setRowHeight(22);
        $ws->mergeCells("A{$ligne}:C{$ligne}");
        $ws->mergeCells("E{$ligne}:I{$ligne}");

        $ws->setCellValue("A{$ligne}", $libelle);
        $ws->setCellValue("D{$ligne}", $formule);
        $ws->setCellValue("E{$ligne}", $lecture);

        $this->style($ws, "A{$ligne}:C{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $fond, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->style($ws, "D{$ligne}", [
            'taille' => $cle ? 12 : 10, 'gras' => true,
            'couleur' => $cle ? $this->c['section'] : $this->c['encre'],
            'fond' => $fond, 'format' => $format,
            'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true,
        ]);
        $this->style($ws, "E{$ligne}:I{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:I{$ligne}");
    }

    /**
     * Le mode opératoire, découpé en étapes à partir des notes de la fiche.
     * Sur une fiche de plat la section existe toujours, même vide : c'est un
     * rappel permanent qu'il reste à la remplir. Sur une préparation, elle
     * n'apparaît que si le chef a effectivement noté quelque chose.
     */
    private function modeOperatoire(Worksheet $ws, int $ligne, RestaurantRecipe $recette, string $titre, bool $toujours): void
    {
        $etapes = collect(preg_split('/\r\n|\r|\n/', (string) $recette->notes))
            ->map(fn (string $etape) => trim($etape))
            ->filter()
            ->values();

        if ($etapes->isEmpty() && !$toujours) {
            return;
        }

        $this->bandeau($ws, "A{$ligne}:I{$ligne}", '4. ' . $titre, $this->c['titre'], $this->c['titre_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);
        $ligne++;

        if ($etapes->isEmpty()) {
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->mergeCells("A{$ligne}:I{$ligne}");
            $ws->setCellValue("A{$ligne}", 'Aucune instruction saisie — complétez les notes de la fiche depuis la plateforme.');
            $this->style($ws, "A{$ligne}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:I{$ligne}");

            return;
        }

        foreach ($etapes as $index => $etape) {
            $ws->getRowDimension($ligne)->setRowHeight(22);
            $ws->mergeCells("A{$ligne}:B{$ligne}");
            $ws->mergeCells("C{$ligne}:I{$ligne}");

            $ws->setCellValue("A{$ligne}", 'Étape ' . ($index + 1));
            $ws->setCellValue("C{$ligne}", $etape);

            $this->style($ws, "A{$ligne}:B{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "C{$ligne}:I{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true, 'retour' => true]);
            $this->bordures($ws, "A{$ligne}:I{$ligne}");

            $ligne++;
        }
    }

    // ── Onglet « tableau de bord » ───────────────────────────────────────────

    /**
     * @param  array<int,array<string,mixed>>  $reperes
     */
    private function feuilleTableauDeBord(Spreadsheet $classeur, array $reperes, ?Tenant $tenant): void
    {
        $ws = $classeur->createSheet(0);
        $ws->setTitle('📊 Carte & Rentabilité Menu');

        $this->largeurs($ws, ['A' => 32, 'B' => 42, 'C' => 12, 'D' => 20, 'E' => 20, 'F' => 22, 'G' => 18, 'H' => 22]);

        $this->bandeau(
            $ws, 'A1:H1',
            $this->titreSigne($tenant, 'ANALYSE DU FOOD COST & MARGES DU MENU'),
            $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 42
        );
        $this->ligneMarque($ws, 'A2', $tenant, 'Suivi de la rentabilité des plats de la carte, food cost unitaire et coefficients multiplicateurs.');
        $this->logo($ws, $tenant);

        $this->enteteTableau($ws, 4, [
            'Catégorie', 'Intitulé du Plat', 'Portions', 'Food Cost (FCFA)', 'Prix Vente TTC',
            'Marge Brute (FCFA)', 'Food Cost (%)', 'Coeff Multiplicateur',
        ], 30, true);

        $premiere = 5;
        $ligne = $premiere;

        foreach ($reperes as $repere) {
            $onglet = $this->reference($repere['onglet']);
            $ws->getRowDimension($ligne)->setRowHeight(22);

            $ws->setCellValue("A{$ligne}", "={$onglet}!{$repere['categorie']}");
            $ws->setCellValue("B{$ligne}", "={$onglet}!{$repere['nom']}");
            $ws->setCellValue("C{$ligne}", "={$onglet}!{$repere['portions']}");
            $ws->setCellValue("D{$ligne}", "={$onglet}!{$repere['food_cost']}");
            $ws->setCellValue("E{$ligne}", "={$onglet}!{$repere['prix']}");
            $ws->setCellValue("F{$ligne}", "={$onglet}!{$repere['marge']}");
            $ws->setCellValue("G{$ligne}", "={$onglet}!{$repere['ratio']}");
            $ws->setCellValue("H{$ligne}", "={$onglet}!{$repere['coeff']}");

            $this->style($ws, "A{$ligne}:B{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "C{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'format' => $this->formatQuantite((float) $repere['couverts']), 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "D{$ligne}:E{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "F{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['blanc'], 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "G{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['blanc'], 'format' => self::FMT_TAUX, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "H{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['blanc'], 'format' => '0.00', 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:H{$ligne}");

            $ligne++;
        }

        if ($reperes === []) {
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->mergeCells("A{$ligne}:H{$ligne}");
            $ws->setCellValue("A{$ligne}", 'Aucune fiche de plat à ce jour — les préparations de base figurent dans leurs propres onglets.');
            $this->style($ws, "A{$ligne}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:H{$ligne}");

            $ws->freezePane('A5');
            $ws->setSelectedCell('A1');

            return;
        }

        // Moyenne de la carte : la dernière ligne se lit comme un repère, pas
        // comme un plat de plus — d'où le fond gris et le trait double.
        $derniere = $ligne - 1;
        $ws->getRowDimension($ligne)->setRowHeight(25);
        $ws->setCellValue("A{$ligne}", 'MOYENNE');
        $ws->setCellValue("B{$ligne}", 'Moyenne globale de la carte');

        $couverts = array_map(fn (array $repere) => (float) $repere['couverts'], $reperes);
        $formats = ['C' => $this->formatQuantite(...$couverts), 'D' => self::FMT_FCFA, 'E' => self::FMT_FCFA, 'F' => self::FMT_FCFA, 'G' => self::FMT_TAUX, 'H' => '0.00'];
        foreach ($formats as $col => $format) {
            $ws->setCellValue("{$col}{$ligne}", "=IFERROR(AVERAGE({$col}{$premiere}:{$col}{$derniere}),0)");
            $this->style($ws, "{$col}{$ligne}", [
                'taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'format' => $format,
                'align' => $col === 'C' ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_RIGHT,
                'vertical' => true,
            ]);
        }

        $this->style($ws, "A{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "B{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:H{$ligne}");
        $ws->getStyle("A{$ligne}:H{$ligne}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $ws->freezePane('A5');
        $ws->setSelectedCell('A1');
    }

    // ── Onglet « mercuriale » ────────────────────────────────────────────────

    /**
     * @param  Collection<int,RestaurantPantryItem>  $ingredients
     */
    private function feuilleMercuriale(Spreadsheet $classeur, Collection $ingredients, ?Tenant $tenant): void
    {
        $ws = $classeur->createSheet(1);
        $ws->setTitle('🛒 Mercuriale & Ingrédients');

        $this->largeurs($ws, ['A' => 18, 'B' => 42, 'C' => 28, 'D' => 28, 'E' => 22, 'F' => 26]);

        $this->bandeau(
            $ws, 'A1:F1',
            $this->titreSigne($tenant, "MERCURIALE CENTRALE — PRIX D'ACHAT & UNITÉS MATIÈRES PREMIÈRES"),
            $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 40
        );
        $this->ligneMarque($ws, 'A2', $tenant, "Coûts moyens pondérés du garde-manger, effectivement appliqués dans les fiches techniques.");

        $this->enteteTableau($ws, 4, [
            'Code Ingrédient', 'Désignation Ingrédient', 'Catégorie',
            'Conditionnement / Unité', "Prix d'Achat (FCFA)", 'Coût au g / ml / unité (FCFA)',
        ], 30, true);

        $ligne = 5;

        foreach ($ingredients as $article) {
            [$conditionnement, $conversion] = $this->conditionnement($article);

            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->setCellValue("A{$ligne}", $this->codes[$article->id] ?? '—');
            $ws->setCellValue("B{$ligne}", $article->name);
            $ws->setCellValue("C{$ligne}", $this->categorieMercuriale($article));
            $ws->setCellValue("D{$ligne}", $conditionnement);
            // Le prix d'achat se déduit du coût unitaire : c'est ce dernier que
            // la plateforme tient à jour à chaque réception.
            $ws->setCellValue("E{$ligne}", "=F{$ligne}*" . $this->nombreFormule($conversion));
            $ws->setCellValue("F{$ligne}", round((float) $article->average_cost / 100, 4));

            $this->style($ws, "A{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "B{$ligne}:D{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "E{$ligne}", ['taille' => 10, 'gras' => true, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "F{$ligne}", ['taille' => 10, 'format' => self::FMT_FCFA_FIN, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:F{$ligne}");

            $ligne++;
        }

        if ($ligne === 5) {
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->mergeCells("A{$ligne}:F{$ligne}");
            $ws->setCellValue("A{$ligne}", 'Aucun ingrédient au garde-manger à ce jour');
            $this->style($ws, "A{$ligne}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:F{$ligne}");
        }

        $ws->freezePane('A5');
        $ws->setSelectedCell('A1');
    }

    // ── Données de référence ─────────────────────────────────────────────────

    /**
     * Le catalogue de la mercuriale : les articles actifs du garde-manger, plus
     * tout ingrédient consommé par une fiche exportée — même désactivé, sinon
     * sa ligne de fiche pointerait un code introuvable.
     *
     * @param  Collection<int,RestaurantRecipe>  $recettes
     * @param  Collection<int,RestaurantPantryItem>  $gardeManger
     * @return Collection<int,RestaurantPantryItem>
     */
    private function mercuriale(Collection $recettes, Collection $gardeManger): Collection
    {
        $utilises = $recettes
            ->flatMap(fn (RestaurantRecipe $recette) => $recette->lines->map(fn (RestaurantRecipeLine $l) => $l->item))
            ->filter();

        return $gardeManger
            ->merge($utilises)
            ->unique('id')
            ->sortBy([
                fn (RestaurantPantryItem $a, RestaurantPantryItem $b) => $this->cleDeTri($a->category?->name) <=> $this->cleDeTri($b->category?->name),
                fn (RestaurantPantryItem $a, RestaurantPantryItem $b) => $this->cleDeTri($a->name) <=> $this->cleDeTri($b->name),
            ])
            ->values();
    }

    /**
     * Codes de mercuriale, attribués dans l'ordre d'affichage : le lecteur qui
     * suit un ING-007 depuis une fiche le trouve à la septième ligne.
     *
     * @param  Collection<int,RestaurantPantryItem>  $ingredients
     * @return array<int,string>
     */
    private function codes(Collection $ingredients): array
    {
        $codes = [];
        foreach ($ingredients->values() as $rang => $article) {
            $codes[$article->id] = sprintf('ING-%03d', $rang + 1);
        }

        return $codes;
    }

    /**
     * Portion de référence de chaque préparation : ce qu'un plat en consomme
     * pour une assiette. Une préparation dont plusieurs plats tirent des
     * quantités différentes est ramenée à leur moyenne.
     *
     * @param  Collection<int,RestaurantRecipe>  $plats
     * @return array<int,float>
     */
    private function portionsDeReference(Collection $plats): array
    {
        $usages = [];

        foreach ($plats as $plat) {
            $rendement = $plat->yield();

            foreach ($plat->lines as $composant) {
                $article = $composant->item;
                if (!$article?->is_prepared) {
                    continue;
                }

                $usages[$article->id][] = $composant->grossQuantity() / $rendement;
            }
        }

        return array_map(fn (array $quantites) => array_sum($quantites) / count($quantites), $usages);
    }

    // ── Habillage propre à la cuisine ────────────────────────────────────────

    private function largeursFiche(Worksheet $ws): void
    {
        $this->largeurs($ws, [
            'A' => 24, 'B' => 36, 'C' => 16, 'D' => 10, 'E' => 16,
            'F' => 20, 'G' => 20, 'H' => 20, 'I' => 34,
        ]);
    }

    /** Nature du composant dans une assiette : une préparation se distingue. */
    private function composantLabel(?RestaurantPantryItem $article): string
    {
        if ($article === null) {
            return 'Ingrédient';
        }

        if ($article->is_prepared) {
            return 'Préparation de base';
        }

        return $article->category?->name ?: 'Ingrédient';
    }

    /**
     * Commentaire d'une ligne : les notes du chef, et l'origine du coût.
     *
     * Nommer la source évite qu'on corrige un coût unitaire dans Excel en
     * croyant agir sur le garde-manger — le même soin que sur les fiches de
     * chambres, où les postes liés à l'économat s'annoncent.
     *
     * @param  array<int,string>  $ongletsPrep
     */
    private function commentaireComposant(RestaurantRecipeLine $composant, ?RestaurantPantryItem $article, array $ongletsPrep): string
    {
        $morceaux = array_filter([trim((string) $composant->notes)]);

        if ($article?->is_prepared) {
            $onglet = $ongletsPrep[$article->recipe?->id] ?? null;
            $morceaux[] = $onglet
                ? "Préparation maison — voir l'onglet « {$onglet} »"
                : 'Préparation maison — coût moyen pondéré du garde-manger';
        } elseif ($article) {
            $morceaux[] = 'Coût moyen économat — ' . $article->name;
        }

        return implode(' · ', $morceaux);
    }

    /** Référence d'une préparation, sur le modèle PREP-NDOL-01. */
    private function codePreparation(RestaurantRecipe $recette, int $rang): string
    {
        $racine = preg_replace('/[^A-Z]/', '', mb_strtoupper($this->sansAccents($recette->name)));

        return sprintf('PREP-%s-%02d', mb_substr($racine ?: 'BASE', 0, 4), $rang);
    }

    /**
     * Clé de classement d'un libellé français : accents repliés sur leur
     * lettre, casse ignorée. Un article sans catégorie ferme la marche.
     */
    private function cleDeTri(?string $libelle): string
    {
        $propre = trim((string) $libelle);

        return $propre === ''
            ? "\u{FFFF}"
            : mb_strtolower($this->sansAccents($propre));
    }
    private function sansAccents(string $texte): string
    {
        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ç' => 'C',
        ]);
    }

    /** Unité écrite en toutes lettres, pour la ligne de rendement. */
    private function uniteLongue(string $unite, float $quantite): string
    {
        $libelle = match ($unite) {
            'g'   => 'Grammes',
            'kg'  => 'Kilogrammes',
            'ml'  => 'Millilitres',
            'l'   => 'Litres',
            'pcs' => 'Pièces',
            default => $unite,
        };

        // Un rendement en grammes se relit plus vite en kilos : on le rappelle.
        return match ($unite) {
            'g'  => $libelle . ' (' . $this->nombre($quantite / 1000) . ' kg)',
            'ml' => $libelle . ' (' . $this->nombre($quantite / 1000) . ' L)',
            default => $libelle,
        };
    }

    /**
     * Multiple usuel d'une unité de suivi : on chiffre au kilo ce qui se compte
     * au gramme, au litre ce qui se compte au millilitre.
     *
     * @return array{0:int,1:string,2:string}|null
     */
    private function multiple(string $unite): ?array
    {
        return match ($unite) {
            'g'  => [1000, 'COÛT AU KILOGRAMME (1 KG)', 'Coût matière ramené au kilogramme'],
            'ml' => [1000, 'COÛT AU LITRE (1 L)', 'Coût matière ramené au litre'],
            default => null,
        };
    }

    /**
     * Conditionnement d'achat, et nombre d'unités de suivi qu'il contient.
     *
     * Sans conditionnement déclaré, on retombe sur le multiple commercial usuel :
     * ce qui se suit au gramme s'achète au kilo, et c'est ce prix-là qui parle à
     * l'acheteur — pas les 3,50 FCFA du gramme, qu'aucun fournisseur ne cote.
     *
     * @return array{0:string,1:float}
     */
    private function conditionnement(RestaurantPantryItem $article): array
    {
        $achat = trim((string) $article->purchase_unit);
        $conversion = $article->conversion();

        if ($achat !== '') {
            return [
                $conversion > 1
                    ? $achat . ' (' . $this->nombre($conversion) . ' ' . $article->unit . ')'
                    : $achat,
                $conversion,
            ];
        }

        return match ($article->unit) {
            'g'     => ['Kg', 1000.0],
            'ml'    => ['Litre', 1000.0],
            'kg'    => ['Kg', 1.0],
            'l'     => ['Litre', 1.0],
            'pcs'   => ['Pièce', 1.0],
            default => [$article->unit, 1.0],
        };
    }

    /**
     * Catégorie affichée à la mercuriale. Une préparation maison s'annonce,
     * sans se répéter quand sa catégorie le dit déjà.
     */
    private function categorieMercuriale(RestaurantPantryItem $article): string
    {
        $categorie = trim((string) $article->category?->name);

        if (!$article->is_prepared) {
            return $categorie ?: 'Non classé';
        }

        if ($categorie !== '' && str_contains($this->cleDeTri($categorie), 'prepar')) {
            return $categorie;
        }

        return $categorie === '' ? 'Préparation de base' : 'Préparation de base · ' . $categorie;
    }

    /**
     * Format d'une quantité : entier tant que les valeurs le sont, décimal dès
     * que l'une ne l'est pas.
     *
     * Un format décimal fixe afficherait « 2 000, » sur toute une colonne de
     * grammes ; un format entier arrondirait une demi-pièce à une pièce. On
     * choisit donc au vu des valeurs réellement écrites.
     */
    private function formatQuantite(float ...$valeurs): string
    {
        foreach ($valeurs as $valeur) {
            if (abs($valeur - round($valeur)) > 0.0005) {
                return '#,##0.###';
            }
        }

        return '#,##0';
    }

    /** Nombre lisible : pas de zéros décimaux inutiles (2,500 → 2,5). */
    private function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 3, ',', ' '), '0'), ',');
    }

    /** Le même nombre, mais destiné à une formule : point décimal, pas de séparateur. */
    private function nombreFormule(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 4, '.', ''), '0'), '.') ?: '1';
    }

    // ── Fiscalité ────────────────────────────────────────────────────────────

    /** Taux de TVA en vigueur sur les ventes, en fraction. */
    private function tauxTva(): float
    {
        if (!$this->taxation->vatEnabled()) {
            return 0.0;
        }

        return (int) $this->taxation->defaultRate()->rate_basis_points / 10000;
    }

    private function mentionTva(): string
    {
        return $this->taxation->vatEnabled()
            ? 'Prix de vente TTC — TVA comprise « en dedans »'
            : 'Établissement non assujetti — prix nets';
    }
}
