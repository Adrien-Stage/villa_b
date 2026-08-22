<?php

namespace App\Services;

use App\Models\RoomCostItem;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\Concerns\HabilleLeClasseur;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Classeur des fiches techniques, sur le modèle du document de référence de
 * l'hôtellerie : un onglet de synthèse, un onglet de coûts unitaires, puis
 * une fiche complète par type de chambre.
 *
 * Le classeur est vivant, pas figé : les totaux, marges et taux sont des
 * formules qui pointent les cellules de la fiche. Le contrôleur de gestion
 * peut donc modifier une quantité ou un tarif dans Excel et voir la
 * rentabilité se recalculer, ce qu'un export de valeurs mortes interdit.
 *
 * L'identité de l'établissement porte le document : sa palette habille les
 * bandeaux, son nom et ses coordonnées coiffent chaque onglet, son logo
 * signe le tableau de bord.
 */
class RoomCostSheetWorkbook
{
    use HabilleLeClasseur;

    /** Feuille de fiche : lignes fixes du haut, avant les postes variables. */
    private const LIGNE_NOM         = 4;
    private const LIGNE_CODE        = 5;
    private const LIGNE_CAPACITE    = 6;
    private const LIGNE_SEJOUR      = 7;
    private const LIGNE_TARIF       = 8;
    private const LIGNE_ENTETE_VAR  = 11;
    private const PREMIERE_VARIABLE = 12;

    public function __construct(private RoomCostingService $costing)
    {
    }

    /**
     * @param  Collection<int,RoomType>  $types
     */
    public function build(Collection $types, ?Tenant $tenant = null): Spreadsheet
    {
        $this->c = $this->palette($tenant);

        $classeur = new Spreadsheet();
        $classeur->removeSheetByIndex(0);

        $this->proprietes($classeur, $tenant, 'Fiches techniques & coûts de revient', 'Coût de revient par type de chambre');

        // Les fiches d'abord : le tableau de bord ne peut pointer que des
        // cellules dont il connaît déjà l'adresse exacte.
        $reperes = [];
        $unitaires = [];

        foreach ($types as $type) {
            $reperes[] = $this->feuilleFiche($classeur, $type, $tenant, $unitaires);
        }

        $this->feuilleTableauDeBord($classeur, $reperes, $tenant);
        $this->feuilleParametres($classeur, $unitaires, $tenant);

        // Le lecteur arrive sur la synthèse, pas sur la dernière fiche écrite.
        $classeur->setActiveSheetIndex(0);
        $classeur->getActiveSheet()->setSelectedCell('A1');

        return $classeur;
    }

    // ── Onglet « fiche technique » ───────────────────────────────────────────

    /**
     * Une fiche complète pour un type de chambre.
     *
     * @param  array<string,array{categorie:string,poste:string,cout:float,unite:string}>  $unitaires
     * @return array<string,string>  adresses utiles au tableau de bord
     */
    private function feuilleFiche(Spreadsheet $classeur, RoomType $type, ?Tenant $tenant, array &$unitaires): array
    {
        $fiche = $this->costing->sheetFor($type);
        $ws = $classeur->createSheet();
        $ws->setTitle($this->nomOnglet($classeur, $type->name));

        $this->largeurs($ws, ['A' => 25, 'B' => 32, 'C' => 22, 'D' => 18, 'E' => 18, 'F' => 22, 'G' => 35]);

        // 0. Bandeau de titre, aux couleurs de l'établissement
        $this->bandeau($ws, 'A1:G1', $this->titreFiche($type, $tenant), $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 38);
        $this->ligneMarque($ws, 'A2', $tenant);

        // 1. Caractéristiques
        $this->bandeau($ws, 'A3:C3', "1. CARACTÉRISTIQUES DE L'HÉBERGEMENT", $this->c['section'], $this->c['section_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);

        $a = $fiche['assumptions'];
        $caracteristiques = [
            [self::LIGNE_NOM,      'Catégorie de chambre',              $type->name,                                null,                 null],
            [self::LIGNE_CODE,     'Code Type / Référence',             $type->code,                                null,                 null],
            [self::LIGNE_CAPACITE, 'Capacité nominale de référence',    (int) $a['reference_occupants'],            self::FMT_ENTIER,     'personne(s)'],
            [self::LIGNE_SEJOUR,   'Durée moyenne de séjour',           (float) $a['avg_length_of_stay'],           self::FMT_DECIMAL,    'nuitée(s)'],
            [self::LIGNE_TARIF,    'Tarif de vente moyen affiché (TTC)', round($fiche['reference_price'] / 100, 2), self::FMT_FCFA,       'FCFA / nuitée'],
        ];

        foreach ($caracteristiques as [$ligne, $libelle, $valeur, $format, $unite]) {
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
                    'gras' => $ligne === self::LIGNE_TARIF,
                    'format' => $format,
                    'align' => Alignment::HORIZONTAL_RIGHT,
                    'vertical' => true,
                ]);
            }

            $ws->setCellValue("C{$ligne}", $unite ?? '');
            $this->style($ws, "C{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:C{$ligne}");
        }

        // 2. Charges variables
        $this->bandeau($ws, 'A10:G10', "2. CHARGES VARIABLES DIRECTES D'EXPLOITATION (PAR NUITÉE OCCUPÉE)", $this->c['section'], $this->c['section_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);
        $this->enteteTableau($ws, self::LIGNE_ENTETE_VAR, [
            'Catégorie', 'Poste de dépense', "Base d'imputation", 'Quantité / Base',
            'Coût Unitaire', 'Coût / Nuitée (FCFA)', 'Commentaires',
        ]);

        $ligne = self::PREMIERE_VARIABLE;
        $lignes = collect($fiche['groups'])->flatMap(fn (array $groupe) => $groupe['lines']);

        if ($lignes->isEmpty()) {
            // Une fiche vide se dit : sinon le total à zéro se lit comme une
            // chambre qui ne coûte rien à exploiter.
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->mergeCells("A{$ligne}:E{$ligne}");
            $ws->setCellValue("A{$ligne}", 'Aucun poste variable saisi pour cette fiche');
            $this->style($ws, "A{$ligne}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $ws->setCellValue("F{$ligne}", 0);
            $this->style($ws, "F{$ligne}", ['taille' => 10, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $ws->setCellValue("G{$ligne}", 'À compléter depuis la plateforme');
            $this->style($ws, "G{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:G{$ligne}");
            $ligne++;
        } else {
            foreach ($lignes as $poste) {
                $this->lignePosteVariable($ws, $ligne, $poste);
                $this->collecteUnitaire($unitaires, $poste);
                $ligne++;
            }
        }

        $derniereVariable = $ligne - 1;
        $ligneTotalVar = $ligne;

        $this->ligneTotal(
            $ws,
            $ligneTotalVar,
            'TOTAL CHARGES VARIABLES / NUITÉE',
            "=SUM(F" . self::PREMIERE_VARIABLE . ":F{$derniereVariable})",
            $this->c['rouge']
        );

        // 3. Charges fixes
        $ligneSection3 = $ligneTotalVar + 2;
        $this->bandeau($ws, "A{$ligneSection3}:G{$ligneSection3}", "3. CHARGES FIXES D'HÉBERGEMENT (STRUCTURE & AMORTISSEMENTS)", $this->c['section'], $this->c['section_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);

        $ligneEnteteFixe = $ligneSection3 + 1;
        $this->enteteTableau($ws, $ligneEnteteFixe, [
            'Catégorie', 'Poste de dépense', "Base d'imputation", 'Détail / Clé',
            'Mode de calcul', 'Montant / Nuitée (FCFA)', 'Commentaires',
        ]);

        $premiereFixe = $ligneEnteteFixe + 1;
        $ws->getRowDimension($premiereFixe)->setRowHeight(20);
        $ws->setCellValue("A{$premiereFixe}", 'Structure & Amortissement');
        $ws->setCellValue("B{$premiereFixe}", "Charges fixes d'hébergement");
        $ws->setCellValue("C{$premiereFixe}", 'Quote-part par nuitée');
        $ws->setCellValue("D{$premiereFixe}", 'Forfait de la fiche');
        $ws->setCellValue("E{$premiereFixe}", 'Forfait nuitée');
        $ws->setCellValue("F{$premiereFixe}", round($fiche['fixed_cost'] / 100, 2));
        $ws->setCellValue("G{$premiereFixe}", $a['notes'] ?: 'Amortissements, maintenance, abonnements et assurances');

        $this->style($ws, "A{$premiereFixe}:C{$premiereFixe}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->style($ws, "D{$premiereFixe}:E{$premiereFixe}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "F{$premiereFixe}", ['taille' => 10, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "G{$premiereFixe}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$premiereFixe}:G{$premiereFixe}");

        $ligneTotalFixe = $premiereFixe + 1;
        $this->ligneTotal(
            $ws,
            $ligneTotalFixe,
            'TOTAL CHARGES FIXES / NUITÉE',
            "=SUM(F{$premiereFixe}:F{$premiereFixe})",
            $this->c['encre']
        );

        // 4. Synthèse
        $ligneSection4 = $ligneTotalFixe + 2;
        $this->bandeau($ws, "A{$ligneSection4}:G{$ligneSection4}", '4. SYNTHÈSE DE GESTION & RENTABILITÉ PAR NUITÉE', $this->c['titre'], $this->c['titre_encre'], 11, Alignment::HORIZONTAL_LEFT, 24);

        $tv = "F{$ligneTotalVar}";
        $tf = "F{$ligneTotalFixe}";
        $prix = 'B' . self::LIGNE_TARIF;

        $syntheses = [
            ['COÛT DE REVIENT COMPLET PAR NUITÉE (CPOR)', "={$tv}+{$tf}", self::FMT_FCFA, 'Charges Variables + Charges Fixes', 'Coût global de mise à disposition', true],
            ['PRIX DE VENTE CONSEILLÉ / NUITÉE', "={$prix}", self::FMT_FCFA, 'Tarif affiché', 'Prix de vente public TTC', false],
            ['MARGE SUR COÛT VARIABLE (MCV)', "={$prix}-{$tv}", self::FMT_FCFA, 'Prix de vente - Charges variables', 'Contribution à la couverture des frais fixes', false],
            ['TAUX DE MARGE SUR COÛT VARIABLE', "=IFERROR(({$prix}-{$tv})/{$prix},0)", self::FMT_TAUX, 'MCV / Prix de Vente', 'Doit idéalement dépasser 70%', false],
            ["RÉSULTAT NET D'EXPLOITATION PAR NUITÉE", "={$prix}-({$tv}+{$tf})", self::FMT_FCFA, 'Prix de vente - Coût de revient', 'Bénéfice net généré par nuitée vendue', true],
            ['TAUX DE MARGE NETTE', "=IFERROR(({$prix}-({$tv}+{$tf}))/{$prix},0)", self::FMT_TAUX, 'Résultat net / Prix de Vente', 'Rentabilité finale par nuitée', false],
        ];

        $ligne = $ligneSection4 + 1;
        foreach ($syntheses as [$libelle, $formule, $format, $calcul, $commentaire, $cle]) {
            $this->ligneSynthese($ws, $ligne, $libelle, $formule, $format, $calcul, $commentaire, $cle);
            $ligne++;
        }

        $ws->setSelectedCell('A1');

        return [
            'onglet'      => $ws->getTitle(),
            'nom'         => 'B' . self::LIGNE_NOM,
            'code'        => 'B' . self::LIGNE_CODE,
            'capacite'    => 'B' . self::LIGNE_CAPACITE,
            'sejour'      => 'B' . self::LIGNE_SEJOUR,
            'prix'        => $prix,
            'variable'    => $tv,
            'fixe'        => $tf,
            'revient'     => 'D' . ($ligneSection4 + 1),
            'marge_nette' => 'D' . ($ligneSection4 + 6),
        ];
    }

    /** Un poste variable et sa formule : la base d'imputation reste vivante. */
    private function lignePosteVariable(Worksheet $ws, int $ligne, array $poste): void
    {
        $ws->getRowDimension($ligne)->setRowHeight(20);

        $ws->setCellValue("A{$ligne}", RoomCostItem::CATEGORIES[$poste['category']] ?? 'Autre');
        $ws->setCellValue("B{$ligne}", $poste['label']);
        $ws->setCellValue("C{$ligne}", $poste['basis_label']);
        $ws->setCellValue("D{$ligne}", (float) $poste['quantity']);
        $ws->setCellValue("E{$ligne}", round($poste['unit_cost'] / 100, 2));

        // La base d'imputation passe dans la formule plutôt que d'être fondue
        // dans la quantité : le lecteur voit d'où sort le coût par nuitée, et
        // corriger la capacité ou la durée de séjour recalcule toute la fiche.
        $capacite = '$B$' . self::LIGNE_CAPACITE;
        $sejour   = '$B$' . self::LIGNE_SEJOUR;

        $ws->setCellValue("F{$ligne}", match ($poste['basis']) {
            RoomCostItem::BASIS_PER_GUEST_NIGHT => "=D{$ligne}*E{$ligne}*{$capacite}",
            RoomCostItem::BASIS_PER_STAY        => "=IFERROR(D{$ligne}*E{$ligne}/{$sejour},D{$ligne}*E{$ligne})",
            default                             => "=D{$ligne}*E{$ligne}",
        });

        $commentaire = trim((string) ($poste['notes'] ?? ''));
        if (!empty($poste['linked']) && $poste['stock_name']) {
            // Le coût suit l'économat : le préciser évite qu'on corrige le
            // prix unitaire dans Excel en croyant agir sur la plateforme.
            $suffixe = "Coût moyen économat — {$poste['stock_name']}";
            $commentaire = $commentaire === '' ? $suffixe : "{$commentaire} · {$suffixe}";
        }
        $ws->setCellValue("G{$ligne}", $commentaire);

        $this->style($ws, "A{$ligne}:C{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->style($ws, "D{$ligne}", ['taille' => 10, 'format' => self::FMT_QUANTITE, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "E{$ligne}:F{$ligne}", ['taille' => 10, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "G{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:G{$ligne}");
    }

    /** Ligne de total d'une section : libellé fondu de A à E, montant en F. */
    private function ligneTotal(Worksheet $ws, int $ligne, string $libelle, string $formule, string $couleurMontant): void
    {
        $ws->getRowDimension($ligne)->setRowHeight(22);
        $ws->mergeCells("A{$ligne}:E{$ligne}");
        $ws->setCellValue("A{$ligne}", $libelle);
        $ws->setCellValue("F{$ligne}", $formule);

        $this->style($ws, "A{$ligne}:E{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['total'], 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "F{$ligne}", ['taille' => 11, 'gras' => true, 'couleur' => $couleurMontant, 'fond' => $this->c['total'], 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
        $this->style($ws, "G{$ligne}", ['fond' => $this->c['total']]);

        $this->bordures($ws, "A{$ligne}:G{$ligne}");
        $ws->getStyle("A{$ligne}:G{$ligne}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
    }

    /** Ligne de la synthèse : indicateur, valeur calculée, mode de calcul, lecture. */
    private function ligneSynthese(Worksheet $ws, int $ligne, string $libelle, string $formule, string $format, string $calcul, string $commentaire, bool $cle): void
    {
        $fond = $cle ? $this->c['cle'] : $this->c['blanc'];

        $ws->getRowDimension($ligne)->setRowHeight(22);
        $ws->mergeCells("A{$ligne}:C{$ligne}");
        $ws->mergeCells("F{$ligne}:G{$ligne}");

        $ws->setCellValue("A{$ligne}", $libelle);
        $ws->setCellValue("D{$ligne}", $formule);
        $ws->setCellValue("E{$ligne}", $calcul);
        $ws->setCellValue("F{$ligne}", $commentaire);

        $this->style($ws, "A{$ligne}:C{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $fond, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->style($ws, "D{$ligne}", [
            'taille' => $cle ? 12 : 10,
            'gras' => true,
            'couleur' => $cle ? $this->c['section'] : $this->c['encre'],
            'fond' => $fond,
            'format' => $format,
            'align' => Alignment::HORIZONTAL_RIGHT,
            'vertical' => true,
        ]);
        $this->style($ws, "E{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "F{$ligne}:G{$ligne}", ['taille' => 9, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:G{$ligne}");
    }

    // ── Onglet « tableau de bord » ───────────────────────────────────────────

    /**
     * @param  array<int,array<string,string>>  $reperes
     */
    private function feuilleTableauDeBord(Spreadsheet $classeur, array $reperes, ?Tenant $tenant): void
    {
        $ws = $classeur->createSheet(0);
        $ws->setTitle('📊 Tableau de Bord Général');

        $this->largeurs($ws, ['A' => 12, 'B' => 30, 'C' => 16, 'D' => 20, 'E' => 24, 'F' => 22, 'G' => 24, 'H' => 24, 'I' => 18]);

        $titre = $tenant?->name
            ? mb_strtoupper($tenant->name) . ' — COMPARAISON DES COÛTS DE REVIENT'
            : 'TABLEAU DE BORD GÉNÉRAL — COMPARAISON DES COÛTS DE REVIENT HÔTELIERS';

        $this->bandeau($ws, 'A1:I1', $titre, $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 42);
        $this->ligneMarque($ws, 'A2', $tenant, 'Ce tableau récapitule les indicateurs clés de chaque fiche technique.');
        $this->logo($ws, $tenant);

        $this->enteteTableau($ws, 4, [
            'Code', 'Catégorie de Chambre', 'Capacité (Pers)', 'Séjour Moyen (Nuits)',
            'Coût Variable / Nuitée', 'Charge Fixe / Nuitée', 'Coût de Revient Total',
            'Prix Vente Conseillé', 'Taux Marge Nette',
        ], 30, true);

        $ligne = 5;
        foreach ($reperes as $repere) {
            $onglet = $this->reference($repere['onglet']);
            $ws->getRowDimension($ligne)->setRowHeight(22);

            $ws->setCellValue("A{$ligne}", "={$onglet}!{$repere['code']}");
            $ws->setCellValue("B{$ligne}", "={$onglet}!{$repere['nom']}");
            $ws->setCellValue("C{$ligne}", "={$onglet}!{$repere['capacite']}");
            $ws->setCellValue("D{$ligne}", "={$onglet}!{$repere['sejour']}");
            $ws->setCellValue("E{$ligne}", "={$onglet}!{$repere['variable']}");
            $ws->setCellValue("F{$ligne}", "={$onglet}!{$repere['fixe']}");
            $ws->setCellValue("G{$ligne}", "={$onglet}!{$repere['revient']}");
            $ws->setCellValue("H{$ligne}", "={$onglet}!{$repere['prix']}");
            $ws->setCellValue("I{$ligne}", "={$onglet}!{$repere['marge_nette']}");

            $this->style($ws, "A{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "B{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "C{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'format' => self::FMT_ENTIER, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "D{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'format' => '0.0', 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->style($ws, "E{$ligne}:F{$ligne}", ['taille' => 11, 'fond' => $this->c['blanc'], 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "G{$ligne}:H{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['blanc'], 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "I{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['blanc'], 'format' => self::FMT_TAUX, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:I{$ligne}");

            $ligne++;
        }

        // Moyenne du parc : la dernière ligne se lit comme un repère, pas comme
        // une fiche de plus — d'où le fond gris et le trait double.
        $premiere = 5;
        $derniere = $ligne - 1;
        $ws->getRowDimension($ligne)->setRowHeight(25);
        $ws->setCellValue("A{$ligne}", 'MOYENNE');
        $ws->setCellValue("B{$ligne}", 'Moyenne globale du parc');

        foreach (['C' => self::FMT_ENTIER, 'D' => '0.0', 'E' => self::FMT_FCFA, 'F' => self::FMT_FCFA, 'G' => self::FMT_FCFA, 'H' => self::FMT_FCFA, 'I' => self::FMT_TAUX] as $col => $format) {
            $ws->setCellValue("{$col}{$ligne}", $derniere >= $premiere ? "=IFERROR(AVERAGE({$col}{$premiere}:{$col}{$derniere}),0)" : 0);
            $this->style($ws, "{$col}{$ligne}", [
                'taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'format' => $format,
                'align' => in_array($col, ['C', 'D'], true) ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_RIGHT,
                'vertical' => true,
            ]);
        }

        $this->style($ws, "A{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
        $this->style($ws, "B{$ligne}", ['taille' => 10, 'gras' => true, 'fond' => $this->c['moyenne'], 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
        $this->bordures($ws, "A{$ligne}:I{$ligne}");
        $ws->getStyle("A{$ligne}:I{$ligne}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);

        $ws->freezePane('A5');
        $ws->setSelectedCell('A1');
    }

    // ── Onglet « paramètres » ────────────────────────────────────────────────

    /**
     * @param  array<string,array{categorie:string,poste:string,cout:float,unite:string}>  $unitaires
     */
    private function feuilleParametres(Spreadsheet $classeur, array $unitaires, ?Tenant $tenant): void
    {
        $ws = $classeur->createSheet(1);
        $ws->setTitle('⚙️ Paramètres Généraux');

        $this->largeurs($ws, ['A' => 25, 'B' => 45, 'C' => 24, 'D' => 22]);

        $this->bandeau($ws, 'A1:D1', 'PARAMÈTRES & COÛTS UNITAIRES DE RÉFÉRENCE', $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_CENTER, 40);
        $this->ligneMarque($ws, 'A2', $tenant, 'Coûts unitaires effectivement appliqués dans les fiches techniques.');

        $this->enteteTableau($ws, 4, ['Catégorie', 'Poste de dépense', 'Coût Unitaire (FCFA)', 'Unité de mesure'], 25);

        ksort($unitaires);
        $ligne = 5;

        foreach ($unitaires as $parametre) {
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->setCellValue("A{$ligne}", $parametre['categorie']);
            $ws->setCellValue("B{$ligne}", $parametre['poste']);
            $ws->setCellValue("C{$ligne}", $parametre['cout']);
            $ws->setCellValue("D{$ligne}", $parametre['unite']);

            $this->style($ws, "A{$ligne}:B{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_LEFT, 'vertical' => true]);
            $this->style($ws, "C{$ligne}", ['taille' => 10, 'format' => self::FMT_FCFA, 'align' => Alignment::HORIZONTAL_RIGHT, 'vertical' => true]);
            $this->style($ws, "D{$ligne}", ['taille' => 10, 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:D{$ligne}");

            $ligne++;
        }

        if ($ligne === 5) {
            $ws->getRowDimension($ligne)->setRowHeight(20);
            $ws->mergeCells("A{$ligne}:D{$ligne}");
            $ws->setCellValue("A{$ligne}", 'Aucun poste de coût saisi à ce jour');
            $this->style($ws, "A{$ligne}", ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris'], 'align' => Alignment::HORIZONTAL_CENTER, 'vertical' => true]);
            $this->bordures($ws, "A{$ligne}:D{$ligne}");
        }

        $ws->setSelectedCell('A1');
    }

    /**
     * Coûts unitaires vus dans les fiches, dédoublonnés par poste et montant :
     * l'onglet donne le barème réellement appliqué, pas une table théorique.
     *
     * @param  array<string,array{categorie:string,poste:string,cout:float,unite:string}>  $unitaires
     */
    private function collecteUnitaire(array &$unitaires, array $poste): void
    {
        $categorie = RoomCostItem::CATEGORIES[$poste['category']] ?? 'Autre';
        $cout = round($poste['unit_cost'] / 100, 2);
        $cle = mb_strtolower("{$categorie}|{$poste['label']}|{$cout}");

        $unitaires[$cle] = [
            'categorie' => $categorie,
            'poste'     => $poste['label'],
            'cout'      => $cout,
            'unite'     => match ($poste['basis']) {
                RoomCostItem::BASIS_PER_GUEST_NIGHT => 'FCFA / personne / nuitée',
                RoomCostItem::BASIS_PER_STAY        => 'FCFA / séjour',
                default                             => 'FCFA / nuitée',
            },
        ];
    }

    /** Titre de la fiche, signé par l'établissement. */
    private function titreFiche(RoomType $type, ?Tenant $tenant): string
    {
        return $this->titreSigne($tenant, 'FICHE TECHNIQUE & COÛT DE REVIENT — ' . mb_strtoupper($type->name));
    }
}
