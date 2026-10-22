<?php

namespace App\Services;

use App\Models\ExternalIssue;
use App\Models\GoodsReceipt;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\Concerns\HabilleLeClasseur;
use App\Support\ClasseurStock as C;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Le classeur Excel d'un magasin de l'économat — le magasin central ou un
 * dépôt : articles, mouvements, fournisseurs, catégories, synthèse.
 *
 * Il se suffit à lui-même : listes déroulantes, désignation retrouvée par la
 * référence, stock de chaque article recalculé par formule à partir des
 * mouvements. On peut gérer le magasin dans Excel seul, puis réimporter le
 * fichier (ClasseurStockImport) : les lignes exportées portent leur numéro,
 * elles ne seront pas rejouées.
 *
 * Seules des fonctions communes à Excel, LibreOffice et Google Sheets :
 * SUMIFS, COUNTIFS, INDEX/MATCH, IFERROR.
 */
class ClasseurStockExport
{
    use HabilleLeClasseur;

    /** Lignes vides préparées sous les données, formules et listes comprises. */
    private const LIGNES_ARTICLES_EN_PLUS   = 300;
    private const LIGNES_MOUVEMENTS_EN_PLUS = 1500;
    private const LIGNES_LISTES             = 500;

    /** Les données du classeur d'un magasin, lues en base. */
    public function donnees(?ServiceStore $depot): array
    {
        $mouvements = $depot ? $this->mouvementsDuDepot($depot) : $this->mouvementsDuMagasin();
        $ids = $depot
            ? $depot->stocks()->pluck('stock_item_id')->merge($depot->movements()->pluck('stock_item_id'))->unique()
            : null;

        $articles = StockItem::query()
            ->with('category', 'supplier')
            ->when($ids !== null, fn ($q) => $q->whereKey($ids))
            ->orderBy('name')
            ->get();

        $references = $articles->mapWithKeys(fn (StockItem $a) => [$a->id => $this->referenceDe($a)]);

        return [
            'magasin'      => $depot ? C::PREFIXE_DEPOT . $depot->name : C::MAGASIN_CENTRAL,
            'articles'     => $articles->map(fn (StockItem $a) => [
                'id'          => $a->id,
                'reference'   => $references[$a->id],
                'designation' => $a->name,
                'categorie'   => $a->category?->name,
                'unite'       => $a->unit,
                'fournisseur' => $a->supplier?->name,
                'description' => $a->description,
                'stock_min'   => (float) $a->min_stock ?: null,
                'actif'       => $a->is_active,
            ])->all(),
            'mouvements'   => $mouvements->map(fn (array $m) => [...$m, 'reference' => $references[$m['article']] ?? null])
                ->filter(fn (array $m) => $m['reference'] !== null)
                ->values()
                ->all(),
            'fournisseurs' => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $f) => [
                'nom' => $f->name, 'contact' => $f->contact_name, 'telephone' => $f->phone,
                'email' => $f->email, 'adresse' => $f->address, 'ville' => $f->city, 'notes' => $f->notes,
            ])->all(),
            'categories'   => StockCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name')->all(),
            'unites'       => StockUnit::choix(),
        ];
    }

    /** Référence de l'article dans le classeur ; sans référence, une tirée de son numéro. */
    public function referenceDe(StockItem $article): string
    {
        return trim((string) $article->reference) !== '' ? trim($article->reference) : sprintf('ART-%05d', $article->id);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function mouvementsDuMagasin(): Collection
    {
        $mouvements = StockMovement::query()
            ->where('type', '!=', StockMovement::TYPE_PACKAGING)
            ->orderBy('occurred_at')->orderBy('id')
            ->get();

        $receptions = GoodsReceipt::query()->with('supplier:id,name')
            ->whereKey($mouvements->where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)->pluck('source_id')->filter()->unique())
            ->get()->keyBy('id');
        $demandes = StockRequisition::query()->with('serviceStore:id,name')
            ->whereKey($mouvements->where('source_type', StockMovement::SOURCE_REQUISITION)->pluck('source_id')->filter()->unique())
            ->get()->keyBy('id');
        $sorties = ExternalIssue::query()
            ->whereKey($mouvements->where('source_type', StockMovement::SOURCE_EXTERNAL_ISSUE)->pluck('source_id')->filter()->unique())
            ->get()->keyBy('id');

        return $mouvements->map(function (StockMovement $m) use ($receptions, $demandes, $sorties) {
            $details = $m->details ?? [];
            $reception = $m->source_type === StockMovement::SOURCE_GOODS_RECEIPT ? $receptions->get($m->source_id) : null;
            $demande = $m->source_type === StockMovement::SOURCE_REQUISITION ? $demandes->get($m->source_id) : null;
            $sortie = $m->source_type === StockMovement::SOURCE_EXTERNAL_ISSUE ? $sorties->get($m->source_id) : null;

            $type = match (true) {
                $m->type === StockMovement::TYPE_ADJUSTMENT                                   => C::TYPE_AJUSTEMENT,
                $m->type === StockMovement::TYPE_OUT                                          => C::TYPE_SORTIE,
                $m->source_type === StockMovement::SOURCE_OPENING                             => C::TYPE_INITIAL,
                ($details['nature'] ?? null) === 'retour'                                     => C::TYPE_RETOUR,
                default                                                                       => C::TYPE_ENTREE,
            };

            return [
                'id'          => $m->id,
                'article'     => $m->stock_item_id,
                'date'        => $m->occurred_at,
                'type'        => $type,
                'quantite'    => $type === C::TYPE_AJUSTEMENT ? (float) $m->quantity : abs((float) $m->quantity),
                'cout'        => (int) round($m->unit_cost / 100),
                'fournisseur' => $details['fournisseur'] ?? $reception?->supplier?->name,
                'document'    => $details['document'] ?? $reception?->number ?? $demande?->number ?? $sortie?->number,
                'destination' => $details['beneficiaire']
                    ?? ($details['vers_depot'] ?? null)
                    ?? $demande?->serviceStore?->name
                    ?? ($demande ? (StockRequisition::DEPARTMENTS[$demande->department] ?? $demande->department) : null)
                    ?? $sortie?->beneficiary_name,
                'pointe'      => $this->pointe($details),
                'motif'       => $m->reason,
            ];
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function mouvementsDuDepot(ServiceStore $depot): Collection
    {
        $mouvements = $depot->movements()->orderBy('occurred_at')->orderBy('id')->get();
        $demandes = StockRequisition::query()
            ->whereKey($mouvements->where('source_type', ServiceStoreMovement::SOURCE_REQUISITION)->pluck('source_id')->filter()->unique())
            ->pluck('number', 'id');

        return $mouvements->map(function (ServiceStoreMovement $m) use ($demandes) {
            $details = $m->details ?? [];

            $type = match (true) {
                $m->type === ServiceStoreMovement::TYPE_ADJUSTMENT       => C::TYPE_AJUSTEMENT,
                $m->type === ServiceStoreMovement::TYPE_OUT              => C::TYPE_SORTIE,
                $m->source_type === ServiceStoreMovement::SOURCE_RETOUR  => C::TYPE_RETOUR,
                ($details['nature'] ?? null) === 'stock_initial'         => C::TYPE_INITIAL,
                default                                                  => C::TYPE_ENTREE,
            };

            return [
                'id'          => $m->id,
                'article'     => $m->stock_item_id,
                'date'        => $m->occurred_at,
                'type'        => $type,
                'quantite'    => $type === C::TYPE_AJUSTEMENT ? (float) $m->quantity : abs((float) $m->quantity),
                'cout'        => (int) round($m->unit_cost / 100),
                'fournisseur' => $details['fournisseur'] ?? null,
                'document'    => $details['document']
                    ?? ($m->source_type === ServiceStoreMovement::SOURCE_REQUISITION ? $demandes[$m->source_id] ?? null : null),
                'destination' => $details['beneficiaire'] ?? null,
                'pointe'      => $this->pointe($details),
                'motif'       => $m->reason,
            ];
        });
    }

    private function pointe(array $details): ?string
    {
        return match ($details['pointe'] ?? null) {
            true    => C::OUI,
            false   => C::NON,
            default => null,
        };
    }

    // ── Le classeur ──────────────────────────────────────────────────────────

    /**
     * @param  array{magasin: string, articles: list<array>, mouvements: list<array>,
     *               fournisseurs: list<array>, categories: list<string>, unites: list<string>}  $donnees
     */
    public function classeur(array $donnees, ?Tenant $tenant = null): Spreadsheet
    {
        $this->c = $this->palette($tenant);

        $classeur = new Spreadsheet();
        $this->proprietes($classeur, $tenant, 'Gestion de stock — ' . $donnees['magasin'], 'Classeur de stock');

        $lisezMoi = $classeur->getActiveSheet()->setTitle(C::ONGLET_LISEZ_MOI);
        $articles = $classeur->createSheet()->setTitle(C::ONGLET_ARTICLES);
        $mouvements = $classeur->createSheet()->setTitle(C::ONGLET_MOUVEMENTS);
        $fournisseurs = $classeur->createSheet()->setTitle(C::ONGLET_FOURNISSEURS);
        $categories = $classeur->createSheet()->setTitle(C::ONGLET_CATEGORIES);
        $synthese = $classeur->createSheet()->setTitle(C::ONGLET_SYNTHESE);
        $listes = $classeur->createSheet()->setTitle(C::ONGLET_LISTES);

        $nbArticles = count($donnees['articles']);
        $nbMouvements = count($donnees['mouvements']);
        $finArticles = C::PREMIERE_LIGNE + $nbArticles + self::LIGNES_ARTICLES_EN_PLUS - 1;
        $finMouvements = C::PREMIERE_LIGNE + $nbMouvements + self::LIGNES_MOUVEMENTS_EN_PLUS - 1;
        $finListes = C::PREMIERE_LIGNE + self::LIGNES_LISTES - 1;

        $plages = [
            'articles'   => $finArticles,
            'mouvements' => $finMouvements,
            'listes'     => $finListes,
        ];

        $this->listes($listes, $donnees['unites']);
        $this->fournisseurs($fournisseurs, $donnees['fournisseurs'], $tenant);
        $this->categories($categories, $donnees['categories'], $tenant);
        $this->articles($articles, $donnees['articles'], $plages, $tenant);
        $this->mouvements($mouvements, $donnees['mouvements'], $plages, $tenant);
        $this->synthese($synthese, $plages, $tenant);
        $this->lisezMoi($lisezMoi, $donnees, $tenant);

        $classeur->setActiveSheetIndex(0);

        return $classeur;
    }

    /** Colonne (lettre) d'une clé dans un onglet, dans l'ordre de ses colonnes. */
    private function col(array $colonnes, string $cle): string
    {
        return Coordinate::stringFromColumnIndex(array_search($cle, array_keys($colonnes), true) + 1);
    }

    private function plage(string $onglet, string $colonne, int $fin): string
    {
        return $this->reference($onglet) . '!$' . $colonne . '$' . C::PREMIERE_LIGNE . ':$' . $colonne . '$' . $fin;
    }

    private function enTete(Worksheet $ws, string $titre, string $consigne, array $colonnes, ?Tenant $tenant): void
    {
        $derniere = Coordinate::stringFromColumnIndex(count($colonnes));
        $this->bandeau($ws, "A1:{$derniere}1", $this->titreSigne($tenant, $titre), $this->c['titre'], $this->c['titre_encre'], 14, Alignment::HORIZONTAL_LEFT, 30);
        $this->ligneMarque($ws, 'A2', $tenant);
        $ws->setCellValue('A3', $consigne);
        $this->style($ws, 'A3', ['taille' => 10, 'italique' => true, 'couleur' => $this->c['gris']]);
        $this->enteteTableau($ws, C::LIGNE_ENTETE, array_map(fn ($c) => $c[0], array_values($colonnes)), 32, true);

        $i = 1;
        foreach ($colonnes as [, $largeur]) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i++))->setWidth($largeur);
        }

        $ws->freezePane('A' . C::PREMIERE_LIGNE);
    }

    private function liste(string $formule, bool $strict, string $titre, string $message): DataValidation
    {
        $dv = new DataValidation();
        $dv->setType(DataValidation::TYPE_LIST);
        $dv->setFormula1($formule);
        $dv->setAllowBlank(true);
        $dv->setShowDropDown(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorStyle($strict ? DataValidation::STYLE_STOP : DataValidation::STYLE_INFORMATION);
        $dv->setErrorTitle($titre);
        $dv->setError($message);

        return $dv;
    }

    private function nombre(string $type, bool $positif): DataValidation
    {
        $dv = new DataValidation();
        $dv->setType($type);
        $dv->setOperator($positif ? DataValidation::OPERATOR_GREATERTHANOREQUAL : DataValidation::OPERATOR_BETWEEN);
        $dv->setFormula1($positif ? '0' : '-1000000000');
        if (!$positif) {
            $dv->setFormula2('1000000000');
        }
        $dv->setAllowBlank(true);
        $dv->setShowErrorMessage(true);
        $dv->setErrorTitle('Nombre attendu');
        $dv->setError($positif ? 'Saisissez un nombre positif.' : 'Saisissez un nombre.');

        return $dv;
    }

    /** Colonne grisée : numéro de l'app, à ne pas modifier. */
    private function colonneTechnique(Worksheet $ws, string $colonne, int $fin): void
    {
        $this->style($ws, "{$colonne}" . C::PREMIERE_LIGNE . ":{$colonne}{$fin}", ['couleur' => $this->c['gris'], 'fond' => 'F1F5F9', 'taille' => 9]);
    }

    private function listes(Worksheet $ws, array $unites): void
    {
        $ws->setCellValue('A' . C::LIGNE_ENTETE, 'Unités');
        $ws->setCellValue('B' . C::LIGNE_ENTETE, 'Types de mouvement');
        $ws->setCellValue('C' . C::LIGNE_ENTETE, 'Oui / Non');
        foreach (array_values($unites) as $i => $unite) {
            $ws->setCellValueExplicit('A' . (C::PREMIERE_LIGNE + $i), $unite, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        foreach (C::TYPES as $i => $type) {
            $ws->setCellValue('B' . (C::PREMIERE_LIGNE + $i), $type);
        }
        $ws->setCellValue('C' . C::PREMIERE_LIGNE, C::OUI);
        $ws->setCellValue('C' . (C::PREMIERE_LIGNE + 1), C::NON);
        $ws->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    private function fournisseurs(Worksheet $ws, array $lignes, ?Tenant $tenant): void
    {
        $this->enTete($ws, 'Fournisseurs', 'Ajoutez ici un nouveau fournisseur : il sera créé à l\'import. Les fournisseurs déjà connus de l\'application ne sont pas modifiés.', C::FOURNISSEURS, $tenant);
        foreach ($lignes as $i => $ligne) {
            $n = C::PREMIERE_LIGNE + $i;
            foreach (array_keys(C::FOURNISSEURS) as $j => $cle) {
                $ws->setCellValueExplicit(Coordinate::stringFromColumnIndex($j + 1) . $n, (string) ($ligne[$cle] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
    }

    private function categories(Worksheet $ws, array $noms, ?Tenant $tenant): void
    {
        $this->enTete($ws, 'Catégories', 'Une catégorie ajoutée ici sera créée à l\'import (compte de stock : fournitures, à ajuster dans l\'application).', C::CATEGORIES, $tenant);
        foreach (array_values($noms) as $i => $nom) {
            $ws->setCellValueExplicit('A' . (C::PREMIERE_LIGNE + $i), $nom, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    }

    private function articles(Worksheet $ws, array $lignes, array $plages, ?Tenant $tenant): void
    {
        $colonnes = C::ARTICLES + C::ARTICLES_CALCULS;
        $this->enTete($ws, 'Articles', 'Une ligne par article. La référence relie l\'article à ses mouvements : elle doit être unique. Les colonnes à droite d\'« Actif » se calculent seules.', $colonnes, $tenant);

        $fin = $plages['articles'];
        $finMvt = $plages['mouvements'];
        $col = fn (string $cle) => $this->col($colonnes, $cle);
        $mvt = fn (string $cle) => $this->plage(C::ONGLET_MOUVEMENTS, $this->col(C::MOUVEMENTS, $cle), $finMvt);

        foreach ($lignes as $i => $a) {
            $n = C::PREMIERE_LIGNE + $i;
            $ws->setCellValue($col('id') . $n, $a['id'] ?? null);
            $ws->setCellValueExplicit($col('reference') . $n, (string) $a['reference'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValueExplicit($col('designation') . $n, (string) $a['designation'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValue($col('categorie') . $n, $a['categorie'] ?? null);
            $ws->setCellValue($col('unite') . $n, $a['unite'] ?? null);
            $ws->setCellValue($col('fournisseur') . $n, $a['fournisseur'] ?? null);
            $ws->setCellValue($col('description') . $n, $a['description'] ?? null);
            $ws->setCellValue($col('stock_min') . $n, $a['stock_min'] ?? null);
            $ws->setCellValue($col('actif') . $n, ($a['actif'] ?? true) ? C::OUI : C::NON);
        }

        $ref = $col('reference');
        $type = $mvt('type');
        $quantite = $mvt('quantite');
        $valeurMvt = $mvt('valeur');
        $refMvt = $mvt('reference');
        $somme = fn (int $n, string $libelle) => "SUMIFS({$quantite},{$refMvt},\${$ref}{$n},{$type},\"{$libelle}\")";

        for ($n = C::PREMIERE_LIGNE; $n <= $fin; $n++) {
            $vide = "\${$ref}{$n}=\"\"";
            $ws->setCellValue($col('stock_initial') . $n, "=IF({$vide},\"\"," . $somme($n, C::TYPE_INITIAL) . ')');
            $ws->setCellValue($col('entrees') . $n, "=IF({$vide},\"\"," . $somme($n, C::TYPE_ENTREE) . ')');
            $ws->setCellValue($col('sorties') . $n, "=IF({$vide},\"\"," . $somme($n, C::TYPE_SORTIE) . ')');
            $ws->setCellValue($col('retours') . $n, "=IF({$vide},\"\"," . $somme($n, C::TYPE_RETOUR) . ')');
            $ws->setCellValue($col('ajustements') . $n, "=IF({$vide},\"\"," . $somme($n, C::TYPE_AJUSTEMENT) . ')');
            $ws->setCellValue($col('stock') . $n, sprintf('=IF(%s,"",%s%d+%s%d-%s%d+%s%d+%s%d)', $vide,
                $col('stock_initial'), $n, $col('entrees'), $n, $col('sorties'), $n, $col('retours'), $n, $col('ajustements'), $n));
            // Coût moyen des arrivées (stock initial et entrées), pondéré par les quantités.
            $ws->setCellValue($col('cout') . $n, sprintf(
                '=IF(%s,"",IFERROR((SUMIFS(%s,%s,$%s%d,%s,"%s")+SUMIFS(%s,%s,$%s%d,%s,"%s"))/(%s%d+%s%d),0))',
                $vide, $valeurMvt, $refMvt, $ref, $n, $type, C::TYPE_INITIAL, $valeurMvt, $refMvt, $ref, $n, $type, C::TYPE_ENTREE,
                $col('stock_initial'), $n, $col('entrees'), $n
            ));
            $ws->setCellValue($col('valeur') . $n, sprintf('=IF(%s,"",%s%d*%s%d)', $vide, $col('stock'), $n, $col('cout'), $n));
            $ws->setCellValue($col('alerte') . $n, sprintf('=IF(%s,"",IF(AND(N(%s%d)>0,%s%d<=%s%d),"À commander",""))',
                $vide, $col('stock_min'), $n, $col('stock'), $n, $col('stock_min'), $n));
        }

        $premiere = C::PREMIERE_LIGNE;
        $this->colonneTechnique($ws, $col('id'), $fin);
        $this->style($ws, $col('stock_initial') . $premiere . ':' . $col('alerte') . $fin, ['fond' => $this->c['total']]);
        $this->style($ws, $col('stock') . $premiere . ':' . $col('stock') . $fin, ['gras' => true, 'fond' => $this->c['cle']]);
        $this->style($ws, $col('stock_min') . $premiere . ':' . $col('stock_min') . $fin, ['format' => self::FMT_QUANTITE]);
        $this->style($ws, $col('stock_initial') . $premiere . ':' . $col('stock') . $fin, ['format' => self::FMT_QUANTITE]);
        $this->style($ws, $col('cout') . $premiere . ':' . $col('valeur') . $fin, ['format' => '#,##0']);

        $ws->setDataValidation($col('categorie') . "{$premiere}:" . $col('categorie') . $fin,
            $this->liste($this->plage(C::ONGLET_CATEGORIES, 'A', $plages['listes']), false, 'Catégorie', 'Catégorie absente de l\'onglet Catégories : elle sera créée à l\'import.'));
        $ws->setDataValidation($col('unite') . "{$premiere}:" . $col('unite') . $fin,
            $this->liste($this->plage(C::ONGLET_LISTES, 'A', $plages['listes']), true, 'Unité', 'Choisissez une unité de la liste (réglée dans Paramètres › Économat).'));
        $ws->setDataValidation($col('fournisseur') . "{$premiere}:" . $col('fournisseur') . $fin,
            $this->liste($this->plage(C::ONGLET_FOURNISSEURS, 'A', $plages['listes']), false, 'Fournisseur', 'Fournisseur absent de l\'onglet Fournisseurs : il sera créé à l\'import.'));
        $ws->setDataValidation($col('actif') . "{$premiere}:" . $col('actif') . $fin,
            $this->liste('"' . C::OUI . ',' . C::NON . '"', true, 'Actif', 'Oui ou Non.'));
        $ws->setDataValidation($col('stock_min') . "{$premiere}:" . $col('stock_min') . $fin, $this->nombre(DataValidation::TYPE_DECIMAL, true));

        // En alerte : la ligne se voit.
        $alerte = new Conditional();
        $alerte->setConditionType(Conditional::CONDITION_CONTAINSTEXT)
            ->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)
            ->setText('À commander');
        $alerte->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FF' . $this->c['rouge']);
        $alerte->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
        $ws->setConditionalStyles($col('alerte') . "{$premiere}:" . $col('alerte') . $fin, [$alerte]);

        $ws->setAutoFilter('A' . C::LIGNE_ENTETE . ':' . $col('alerte') . $fin);
    }

    private function mouvements(Worksheet $ws, array $lignes, array $plages, ?Tenant $tenant): void
    {
        $colonnes = C::MOUVEMENTS;
        $this->enTete($ws, 'Mouvements de stock', 'Ajoutez les mouvements à la suite, dans l\'ordre des dates. Choisissez la référence et le type dans les listes ; la désignation, l\'unité et le stock après mouvement se calculent seuls.', $colonnes, $tenant);

        $fin = $plages['mouvements'];
        $finArt = $plages['articles'];
        $col = fn (string $cle) => $this->col($colonnes, $cle);
        $art = fn (string $cle) => $this->plage(C::ONGLET_ARTICLES, $this->col(C::ARTICLES + C::ARTICLES_CALCULS, $cle), $finArt);

        foreach ($lignes as $i => $m) {
            $n = C::PREMIERE_LIGNE + $i;
            $ws->setCellValue($col('id') . $n, $m['id'] ?? null);
            if ($m['date'] ?? null) {
                $ws->setCellValue($col('date') . $n, DateExcel::PHPToExcel($m['date']->copy()->startOfDay()));
            }
            $ws->setCellValueExplicit($col('reference') . $n, (string) $m['reference'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $ws->setCellValue($col('type') . $n, $m['type']);
            $ws->setCellValue($col('quantite') . $n, $m['quantite']);
            $ws->setCellValue($col('cout') . $n, ($m['cout'] ?? null) ?: null);
            $ws->setCellValue($col('fournisseur') . $n, $m['fournisseur'] ?? null);
            if (($m['document'] ?? null) !== null) {
                $ws->setCellValueExplicit($col('document') . $n, (string) $m['document'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $ws->setCellValue($col('destination') . $n, $m['destination'] ?? null);
            $ws->setCellValue($col('pointe') . $n, $m['pointe'] ?? null);
            $ws->setCellValue($col('motif') . $n, $m['motif'] ?? null);
        }

        $ref = $col('reference');
        $premiere = C::PREMIERE_LIGNE;
        for ($n = $premiere; $n <= $fin; $n++) {
            $vide = "\${$ref}{$n}=\"\"";
            $ws->setCellValue($col('designation') . $n, sprintf('=IF(%s,"",IFERROR(INDEX(%s,MATCH($%s%d,%s,0)),"Référence inconnue"))',
                $vide, $art('designation'), $ref, $n, $art('reference')));
            $ws->setCellValue($col('unite') . $n, sprintf('=IF(%s,"",IFERROR(INDEX(%s,MATCH($%s%d,%s,0)),""))',
                $vide, $art('unite'), $ref, $n, $art('reference')));
            $q = $col('quantite') . $n;
            $t = $col('type') . $n;
            $ws->setCellValue($col('signe') . $n, sprintf('=IF(OR(%s,%s=""),"",IF(%s="%s",-ABS(%s),IF(%s="%s",%s,ABS(%s))))',
                $vide, $q, $t, C::TYPE_SORTIE, $q, $t, C::TYPE_AJUSTEMENT, $q, $q));
            $s = $col('signe');
            $ws->setCellValue($col('stock_apres') . $n, sprintf('=IF(OR(%s,%s%d=""),"",SUMIFS($%s$%d:%s%d,$%s$%d:%s%d,$%s%d))',
                $vide, $s, $n, $s, $premiere, $s, $n, $ref, $premiere, $ref, $n, $ref, $n));
            $ws->setCellValue($col('valeur') . $n, sprintf('=IF(OR(%s,%s="",%s%d=""),"",ABS(%s)*%s%d)',
                $vide, $q, $col('cout'), $n, $q, $col('cout'), $n));
        }

        $this->colonneTechnique($ws, $col('id'), $fin);
        foreach (['designation', 'unite', 'signe', 'stock_apres', 'valeur'] as $calcule) {
            $this->style($ws, $col($calcule) . "{$premiere}:" . $col($calcule) . $fin, ['fond' => $this->c['total']]);
        }
        $this->style($ws, $col('stock_apres') . "{$premiere}:" . $col('stock_apres') . $fin, ['gras' => true]);
        $this->style($ws, $col('date') . "{$premiere}:" . $col('date') . $fin, ['format' => 'dd/mm/yyyy']);
        $this->style($ws, $col('quantite') . "{$premiere}:" . $col('stock_apres') . $fin, ['format' => self::FMT_QUANTITE]);
        $this->style($ws, $col('cout') . "{$premiere}:" . $col('valeur') . $fin, ['format' => '#,##0']);

        $date = new DataValidation();
        $date->setType(DataValidation::TYPE_DATE);
        $date->setOperator(DataValidation::OPERATOR_BETWEEN);
        $date->setFormula1('36526');   // 01/01/2000
        $date->setFormula2('73051');   // 31/12/2099
        $date->setAllowBlank(true);
        $date->setShowErrorMessage(true);
        $date->setErrorTitle('Date');
        $date->setError('Saisissez une date (jj/mm/aaaa).');
        $ws->setDataValidation($col('date') . "{$premiere}:" . $col('date') . $fin, $date);

        $ws->setDataValidation("{$ref}{$premiere}:{$ref}{$fin}",
            $this->liste($art('reference'), true, 'Référence', 'Référence absente de l\'onglet Articles : ajoutez d\'abord l\'article.'));
        $ws->setDataValidation($col('type') . "{$premiere}:" . $col('type') . $fin,
            $this->liste($this->plage(C::ONGLET_LISTES, 'B', $premiere + count(C::TYPES) - 1), true, 'Type', 'Stock initial, Entrée, Sortie, Retour ou Ajustement.'));
        $ws->setDataValidation($col('quantite') . "{$premiere}:" . $col('quantite') . $fin, $this->nombre(DataValidation::TYPE_DECIMAL, false));
        $ws->setDataValidation($col('cout') . "{$premiere}:" . $col('cout') . $fin, $this->nombre(DataValidation::TYPE_DECIMAL, true));
        $ws->setDataValidation($col('fournisseur') . "{$premiere}:" . $col('fournisseur') . $fin,
            $this->liste($this->plage(C::ONGLET_FOURNISSEURS, 'A', $plages['listes']), false, 'Fournisseur', 'Fournisseur absent de l\'onglet Fournisseurs : il sera créé à l\'import.'));
        $ws->setDataValidation($col('pointe') . "{$premiere}:" . $col('pointe') . $fin,
            $this->liste('"' . C::OUI . ',' . C::NON . '"', true, 'Pointé', 'Oui ou Non.'));

        // Stock négatif : une sortie de trop se voit tout de suite.
        $negatif = new Conditional();
        $negatif->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0');
        $negatif->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FF' . $this->c['rouge']);
        $negatif->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
        $ws->setConditionalStyles($col('stock_apres') . "{$premiere}:" . $col('stock_apres') . $fin, [$negatif]);

        $ws->setAutoFilter('A' . C::LIGNE_ENTETE . ':' . $col('motif') . $fin);
    }

    private function synthese(Worksheet $ws, array $plages, ?Tenant $tenant): void
    {
        $colonnes = ['categorie' => ['Catégorie', 30], 'articles' => ['Articles', 12], 'valeur' => ['Valeur du stock (FCFA)', 22], 'alertes' => ['À commander', 14]];
        $this->enTete($ws, 'Synthèse', 'Valeur du stock par catégorie et articles à commander. Tout se recalcule à partir des onglets Articles et Mouvements.', $colonnes, $tenant);

        $artCols = C::ARTICLES + C::ARTICLES_CALCULS;
        $art = fn (string $cle) => $this->plage(C::ONGLET_ARTICLES, $this->col($artCols, $cle), $plages['articles']);
        $nbLignes = 60;
        $premiere = C::PREMIERE_LIGNE;

        // La n-ième catégorie de la liste, lue par INDEX sur toute la plage :
        // supprimer des lignes de l'onglet Catégories raccourcit la plage sans
        // casser la formule. Une référence cellule par cellule ('Catégories'!A5)
        // tournait en #REF! dès qu'on supprimait la ligne qu'elle visait.
        $categories = $this->plage(C::ONGLET_CATEGORIES, 'A', $plages['listes']);

        for ($i = 0; $i < $nbLignes; $i++) {
            $n = $premiere + $i;
            $source = "INDEX({$categories},ROWS(\$A\${$premiere}:A{$n}))";
            $ws->setCellValue("A{$n}", "=IF({$source}=\"\",\"\",{$source})");
            $ws->setCellValue("B{$n}", "=IF(A{$n}=\"\",\"\",COUNTIFS({$art('categorie')},A{$n},{$art('reference')},\"<>\"))");
            $ws->setCellValue("C{$n}", "=IF(A{$n}=\"\",\"\",SUMIFS({$art('valeur')},{$art('categorie')},A{$n}))");
            $ws->setCellValue("D{$n}", "=IF(A{$n}=\"\",\"\",COUNTIFS({$art('categorie')},A{$n},{$art('alerte')},\"À commander\"))");
        }

        $total = $premiere + $nbLignes;
        $ws->setCellValue("A{$total}", 'Total du magasin');
        $ws->setCellValue("B{$total}", "=COUNTIFS({$art('reference')},\"<>\")");
        $ws->setCellValue("C{$total}", "=SUM({$art('valeur')})");
        $ws->setCellValue("D{$total}", "=COUNTIFS({$art('alerte')},\"À commander\")");
        $this->style($ws, "A{$total}:D{$total}", ['gras' => true, 'fond' => $this->c['cle']]);
        $this->style($ws, "C{$premiere}:C{$total}", ['format' => '#,##0']);
        $this->bordures($ws, "A{$total}:D{$total}");

        // Mouvements par type.
        $mvt = fn (string $cle) => $this->plage(C::ONGLET_MOUVEMENTS, $this->col(C::MOUVEMENTS, $cle), $plages['mouvements']);
        $ligne = $total + 2;
        $this->enteteTableau($ws, $ligne, ['Mouvements', 'Lignes', 'Quantité', 'Valeur (FCFA)']);
        foreach (C::TYPES as $type) {
            $ligne++;
            $ws->setCellValue("A{$ligne}", $type);
            $ws->setCellValue("B{$ligne}", "=COUNTIFS({$mvt('type')},A{$ligne},{$mvt('reference')},\"<>\")");
            $ws->setCellValue("C{$ligne}", "=SUMIFS({$mvt('quantite')},{$mvt('type')},A{$ligne})");
            $ws->setCellValue("D{$ligne}", "=SUMIFS({$mvt('valeur')},{$mvt('type')},A{$ligne})");
        }
        $this->style($ws, 'C' . ($total + 3) . ":D{$ligne}", ['format' => '#,##0.###']);
        $this->bordures($ws, 'A' . ($total + 3) . ":D{$ligne}");
    }

    private function lisezMoi(Worksheet $ws, array $donnees, ?Tenant $tenant): void
    {
        $ws->getColumnDimension('A')->setWidth(24);
        $ws->getColumnDimension('B')->setWidth(110);
        $this->bandeau($ws, 'A1:B1', $this->titreSigne($tenant, 'Classeur de stock'), $this->c['titre'], $this->c['titre_encre'], 16, Alignment::HORIZONTAL_LEFT, 34);
        $this->logo($ws, $tenant);
        $this->ligneMarque($ws, 'A2', $tenant);
        $ws->mergeCells('A2:B2');

        $ws->setCellValue('A4', C::ETIQUETTE_MAGASIN);
        $ws->setCellValue('B4', $donnees['magasin']);
        $this->style($ws, 'A4:B4', ['gras' => true, 'taille' => 12, 'fond' => $this->c['cle']]);

        $ligne = 6;
        $paragraphe = function (string $titre, array $lignes) use ($ws, &$ligne) {
            $this->section($ws, "A{$ligne}:B{$ligne}", $titre);
            $ligne++;
            foreach ($lignes as $cle => $texte) {
                $ws->setCellValue("A{$ligne}", is_string($cle) ? $cle : '');
                $ws->setCellValue("B{$ligne}", $texte);
                $this->style($ws, "A{$ligne}", ['gras' => true]);
                $this->style($ws, "B{$ligne}", ['retour' => true]);
                $ws->getStyle("A{$ligne}:B{$ligne}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $ligne++;
            }
            $ligne++;
        };

        $paragraphe('Gérer le stock dans ce classeur', [
            '1. Articles'     => 'Une ligne par article : référence unique, désignation, catégorie, unité (liste), fournisseur habituel, stock minimum. Pour un nouvel article, remplissez une ligne vide et laissez « N° » vide.',
            '2. Mouvements'   => 'Une ligne par mouvement, à la suite, dans l\'ordre des dates : date, référence (liste), type (liste), quantité. La désignation, l\'unité et le stock après mouvement se calculent seuls.',
            '3. Stock'        => 'L\'onglet Articles recalcule le stock de chaque article (stock initial + entrées − sorties + retours ± ajustements), sa valeur et l\'alerte « À commander » sous le stock minimum.',
            '4. Synthèse'     => 'Valeur du stock par catégorie, articles à commander, totaux par type de mouvement.',
            'Fournisseurs'    => 'Les fournisseurs et catégories nouveaux s\'ajoutent dans leurs onglets ; l\'import les crée.',
            'Cases vides'     => 'Pour un article déjà connu, une case vide (catégorie, fournisseur, description…) laisse sa fiche telle quelle.',
        ]);

        $paragraphe('Types de mouvement', C::TYPES_EXPLIQUES);

        $paragraphe('Importer dans l\'application', [
            'Où'              => 'Économat › Classeur Excel : choisissez le magasin, déposez ce fichier. Un aperçu montre ce qui sera créé et les erreurs ligne par ligne ; rien n\'est écrit avant votre confirmation.',
            'Ne pas rejouer'  => 'Une ligne qui porte un « N° » vient de l\'application : elle est déjà enregistrée et ne sera pas importée à nouveau. Une ligne déjà importée une fois est reconnue, même sans N°.',
            'Corriger'        => 'Un mouvement enregistré ne se modifie pas dans le fichier : ajoutez un Ajustement (ou une Entrée, une Sortie) qui corrige.',
            'Dates'           => 'Pas de date future. Un mouvement daté d\'une journée déjà clôturée en comptabilité est enregistré à la date du jour, sa date d\'origine reste dans le motif. Rien avant le dernier inventaire du magasin.',
            'Coût'            => 'Le coût unitaire est facultatif : vide, la marchandise entre à 0 FCFA (l\'aperçu le signale). Les sorties et retours sont valorisés au coût moyen.',
            'Après l\'import' => 'Téléchargez à nouveau le classeur depuis l\'application pour continuer sur une version à jour.',
        ]);

        $paragraphe('Unités possibles', ['' => implode(', ', $donnees['unites'])]);
    }
}
