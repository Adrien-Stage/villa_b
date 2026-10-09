<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\DocumentExporter;
use App\Services\StockMovementJournal;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Les mouvements de stock de l'économat : tous les articles, ou la fiche de
 * stock d'un seul (stock au début, entrées, sorties, ajustements, stock en
 * fin). Chaque ligne garde le stock avant et le stock après : un ajustement
 * d'inventaire montre ainsi le stock initial et le stock compté.
 */
class StockMovementController extends Controller
{
    private const PAR_PAGE = 50;

    public const MAX_EXPORT = 5000;

    public function __construct(private StockMovementJournal $journal)
    {
    }

    public function index(Request $request): View
    {
        $filtres = $this->filtres($request);
        $article = $filtres['article'] ? StockItem::find($filtres['article']) : null;

        // La fiche d'un article se lit dans l'ordre du temps ; le journal
        // global, du plus récent au plus ancien.
        $mouvements = $this->journal->query($filtres)
            ->when($article, fn ($q) => $q->orderBy('occurred_at')->orderBy('id'),
                fn ($q) => $q->orderByDesc('occurred_at')->orderByDesc('id'))
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        $documents = $this->journal->documents($mouvements->items());

        return view('economat.movements.index', [
            'mouvements' => $mouvements,
            'lignes'     => collect($mouvements->items())->map(fn (StockMovement $m) => $this->journal->ligne($m, $documents)),
            'synthese'   => $this->journal->synthese($filtres),
            'article'    => $article,
            'fiche'      => $article ? $this->journal->fiche($article, $filtres['du'], $filtres['au']) : null,
            'filtres'    => $filtres,
            'articles'   => StockItem::query()->orderBy('name')->get(['id', 'name', 'reference', 'unit', 'is_active']),
            'categories' => StockCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request, DocumentExporter $exporteur)
    {
        $format = (string) $request->query('format', DocumentExporter::FORMAT_IMPRESSION);
        abort_unless(DocumentExporter::formatValide($format), 404);

        $filtres = $this->filtres($request);
        $article = $filtres['article'] ? StockItem::find($filtres['article']) : null;

        $mouvements = $this->journal->query($filtres)->orderBy('occurred_at')->orderBy('id')->limit(self::MAX_EXPORT)->get();
        $documents = $this->journal->documents($mouvements);
        $lignes = $mouvements->map(fn (StockMovement $m) => $this->journal->ligne($m, $documents));

        AuditLog::record(Auth::id(), 'export', 'Export des mouvements de stock (' . $format . ') — '
            . $lignes->count() . ' ligne(s)', 'economat', ['format' => $format, 'filtres' => $request->query()]);

        $document = Document::intitule($article ? 'Fiche de stock — ' . $article->name : 'Mouvements de stock')
            ->sousTitre($article
                ? 'Entrées, sorties et ajustements de l\'article, avec le stock avant et après chaque mouvement'
                : 'Entrées, sorties et ajustements du magasin central')
            ->periode($filtres['du'], $filtres['au'])
            ->filtres($this->filtresImprimes($filtres, $article))
            ->colonnes(array_values(array_filter([
                Colonne::dateHeure('date', 'Date'),
                $article ? null : Colonne::texte('article', 'Article'),
                Colonne::texte('nature', 'Mouvement'),
                Colonne::texte('origine', 'Document'),
                Colonne::nombre('avant', 'Stock avant'),
                Colonne::nombre('entree', 'Entrée'),
                Colonne::nombre('sortie', 'Sortie'),
                Colonne::nombre('apres', 'Stock après'),
                Colonne::montant('cout', 'Coût unitaire', false),
                Colonne::montant('valeur', 'Valeur'),
                Colonne::texte('motif', 'Motif'),
                Colonne::texte('par', 'Par'),
            ])))
            ->lignes($lignes);

        if ($article) {
            $fiche = $this->journal->fiche($article, $filtres['du'], $filtres['au']);
            $document->note('Stock au début : ' . $this->qte($fiche['debut']) . ' ' . $article->unit
                . ' · entrées : ' . $this->qte($fiche['entrees']) . ' · sorties : ' . $this->qte($fiche['sorties'])
                . ' · ajustements : ' . $this->qte($fiche['ajustements'], true)
                . ' · stock en fin : ' . $this->qte($fiche['fin']) . ' ' . $article->unit . '.');
        } elseif ($lignes->count() >= self::MAX_EXPORT) {
            $document->note('Export limité aux ' . self::MAX_EXPORT . ' premiers mouvements. Affinez la période pour le reste.');
        }

        return $exporteur->rendre($document, $format);
    }

    /**
     * Filtres de l'écran. Sans période demandée, le mois en cours ; une période
     * vidée à la main montre tout l'historique.
     */
    private function filtres(Request $request): array
    {
        $parDefaut = !$request->has('du') && !$request->has('au');

        return [
            'du'        => $parDefaut ? now()->startOfMonth() : $this->date($request->query('du')),
            'au'        => $parDefaut ? now() : $this->date($request->query('au')),
            'article'   => (int) $request->query('article') ?: null,
            'categorie' => (int) $request->query('categorie') ?: null,
            'type'      => array_key_exists((string) $request->query('type'), StockMovement::TYPES) ? $request->query('type') : null,
            'source'    => array_key_exists((string) $request->query('source'), StockMovement::SOURCES) ? $request->query('source') : null,
            'recherche' => trim((string) $request->query('recherche')) ?: null,
        ];
    }

    private function filtresImprimes(array $filtres, ?StockItem $article): array
    {
        return array_filter([
            'Article'   => $article?->name,
            'Catégorie' => $filtres['categorie'] ? StockCategory::find($filtres['categorie'])?->name : null,
            'Mouvement' => $filtres['type'] ? StockMovement::TYPES[$filtres['type']] : null,
            'Origine'   => $filtres['source'] ? StockMovement::SOURCES[$filtres['source']] : null,
            'Motif'     => $filtres['recherche'],
        ]);
    }

    private function date(?string $valeur): ?Carbon
    {
        if (!$valeur) {
            return null;
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            return null;
        }
    }

    private function qte(float $valeur, bool $signe = false): string
    {
        $texte = rtrim(rtrim(number_format(abs($valeur), 3, ',', ' '), '0'), ',');

        return ($signe && $valeur > 0 ? '+' : ($valeur < 0 ? '−' : '')) . $texte;
    }
}
