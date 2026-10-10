<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Services\PermissionResolver;
use App\Services\StockCountImportService;
use App\Services\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class StockCountController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;
    use \App\Http\Controllers\Concerns\HandlesCsv;

    public function __construct(private StockCountService $stockCountService)
    {
    }

    public function index(Request $request): View
    {
        $query = StockCount::with(['category', 'openedBy', 'closedBy'])->withCount('lines');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('stock_category_id', $request->input('category'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('count_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('count_date', '<=', $request->input('date_to'));
        }

        $counts = $query->orderByDesc('count_date')->orderByDesc('id')->paginate(self::PAR_PAGE)->withQueryString();

        $openCount = StockCount::where('status', StockCount::STATUS_DRAFT)->latest('id')->first();
        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();

        // Statistiques globales d'inventaire
        $stats = [
            'total_counts'  => StockCount::count(),
            'closed_counts' => StockCount::where('status', StockCount::STATUS_CLOSED)->count(),
            'total_losses'  => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('loss_value'),
            'total_surplus' => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('surplus_value'),
            'net_variance'  => (int) StockCount::where('status', StockCount::STATUS_CLOSED)->sum('variance_value'),
        ];

        return view('economat.stock_counts.index', [
            'counts'     => $counts,
            'openCount'  => $openCount,
            'categories' => $categories,
            'stats'      => $stats,
            'canManage'  => app(\App\Services\PermissionResolver::class)->allows(Auth::user(), 'economat.stock_counts.close'),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $existing = StockCount::where('status', StockCount::STATUS_DRAFT)->first();
        if ($existing) {
            return redirect()
                ->route('economat.stock_counts.show', $existing)
                ->with('error', "L'inventaire {$existing->reference} est déjà en cours. Veuillez le clôturer ou l'annuler avant d'en ouvrir un nouveau.");
        }

        $categories = StockCategory::orderBy('sort_order')->orderBy('name')->get();

        return view('economat.stock_counts.create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'count_date'        => ['nullable', 'date'],
            'stock_category_id' => ['nullable', 'exists:stock_categories,id'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            // Le comptage déjà fait sur papier peut s'importer dès l'ouverture.
            'fichier'           => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'cloturer'          => ['nullable', 'boolean'],
        ]);

        try {
            $count = $this->stockCountService->open($validated, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        if ($request->hasFile('fichier')) {
            return $this->importer($count, $request->file('fichier'), $request->boolean('cloturer'));
        }

        return redirect()
            ->route('economat.stock_counts.show', $count)
            ->with('success', "Feuille d'inventaire {$count->reference} ouverte. Vous pouvez saisir les quantités constatées en rayon.");
    }

    /**
     * Le fichier de comptage (Excel) : les articles de l'inventaire avec leur
     * stock théorique, et les colonnes « stock compté », « motif » et « note »
     * à remplir. Hors inventaire, les articles actifs avec leur stock du moment.
     */
    public function fichier(Request $request, StockCountImportService $import)
    {
        $inventaire = $request->filled('inventaire') ? StockCount::findOrFail((int) $request->query('inventaire')) : null;
        $categorie = $inventaire ? null : ((int) $request->query('categorie') ?: null);

        return $this->streamXlsx(
            'comptage_' . ($inventaire?->reference ?? 'economat_' . now()->format('Ymd')) . '.xlsx',
            'Comptage',
            StockCountImportService::COLONNES,
            $import->modele($inventaire, $categorie),
        );
    }

    /**
     * Import du fichier de comptage dans un inventaire en cours : les quantités
     * comptées, motifs et notes remplacent la saisie à la main. Sur demande, et
     * pour qui peut clôturer, l'inventaire se clôture aussitôt : le stock est
     * ajusté.
     */
    public function import(Request $request, StockCount $stockCount): RedirectResponse
    {
        $request->validate([
            'fichier'  => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'cloturer' => ['nullable', 'boolean'],
        ], [
            'fichier.required' => 'Choisissez le fichier de comptage à importer.',
            'fichier.mimes'    => 'Le fichier doit être un classeur Excel (.xlsx, .xls) ou un CSV.',
        ]);

        return $this->importer($stockCount, $request->file('fichier'), $request->boolean('cloturer'));
    }

    private function importer(StockCount $stockCount, UploadedFile $fichier, bool $cloturer): RedirectResponse
    {
        $retour = redirect()->route('economat.stock_counts.show', $stockCount);

        if (!$stockCount->isDraft()) {
            return $retour->with('error', "L'inventaire {$stockCount->reference} n'est plus en cours de comptage : rien n'a été importé.");
        }

        [$rows, $erreurLecture] = $this->parseSpreadsheet($fichier->getRealPath(), StockCountImportService::COLONNES_REQUISES);
        if ($erreurLecture) {
            return $retour->with('error', $erreurLecture);
        }

        $import = app(StockCountImportService::class);
        [$saisies, $erreurs] = $import->saisies($stockCount, $rows);

        try {
            if ($saisies !== []) {
                $this->stockCountService->updateCounts($stockCount, $saisies);
            }
        } catch (RuntimeException $e) {
            return $retour->with('error', $e->getMessage());
        }

        $message = count($saisies) . ' article(s) compté(s) importé(s) dans l\'inventaire ' . $stockCount->reference . '.';

        // Une ligne refusée laisse l'inventaire ouvert : on corrige, puis on clôture.
        $peutCloturer = app(PermissionResolver::class)->allows(Auth::user(), 'economat.stock_counts.close');
        if ($cloturer && $peutCloturer && $erreurs === [] && $saisies !== []) {
            try {
                $stockCount = $this->stockCountService->close($stockCount, Auth::user());
            } catch (RuntimeException $e) {
                return $retour->with('error', $message . ' Clôture impossible : ' . $e->getMessage());
            }

            return $retour->with('success', $message . ' Inventaire clôturé : le stock est ajusté sur les quantités comptées.');
        }

        if ($cloturer && $erreurs !== []) {
            $message .= ' L\'inventaire reste ouvert : corrigez les lignes refusées, puis clôturez.';
        }

        return $retour->with($saisies === [] ? 'error' : 'success', $saisies === [] ? 'Aucune quantité comptée n\'a été importée.' : $message)
            ->with('import_errors', $erreurs);
    }

    public function show(StockCount $stockCount): View
    {
        $stockCount->load(['category', 'openedBy', 'closedBy', 'lines.item.category', 'lines.item.packagings']);

        $lines = $stockCount->lines->sortBy(fn (StockCountLine $l) => $l->item?->name ?? '');

        return view('economat.stock_counts.show', [
            'count'     => $stockCount,
            'lines'     => $lines,
            'reasons'   => StockCountLine::REASONS,
            'canManage' => app(\App\Services\PermissionResolver::class)->allows(Auth::user(), 'economat.stock_counts.close'),
        ]);
    }

    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Cet inventaire est déjà clôturé et ne peut plus être modifié.");
        }

        $validated = $request->validate([
            'lines'                    => ['required', 'array'],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            // Comptage par niveau d'un article conditionné : unités fermées et vrac.
            'lines.*.fermes'           => ['nullable', 'array'],
            'lines.*.fermes.*'         => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'lines.*.vrac'             => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.reason'           => ['nullable', 'string', 'max:50'],
            'lines.*.notes'            => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->stockCountService->updateCounts($stockCount, $validated['lines']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Comptages physiques et motifs enregistrés avec succès.");
    }

    public function close(Request $request, StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Cet inventaire est déjà clôturé.");
        }

        try {
            $stockCount = $this->stockCountService->close($stockCount, Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $varianceFCFA = number_format(abs($stockCount->variance_value) / 100, 0, ',', ' ');
        $sign = $stockCount->variance_value < 0 ? '-' : ($stockCount->variance_value > 0 ? '+' : '');

        return redirect()
            ->route('economat.stock_counts.show', $stockCount)
            ->with('success', "Inventaire {$stockCount->reference} clôturé. Les stocks de l'économat ont été régularisés. Écart net : {$sign}{$varianceFCFA} FCFA.");
    }

    public function cancel(StockCount $stockCount): RedirectResponse
    {
        if ($stockCount->isClosed()) {
            return back()->with('error', "Un inventaire clôturé ne peut pas être annulé.");
        }

        try {
            $this->stockCountService->cancel($stockCount);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('economat.stock_counts.index')
            ->with('success', "Feuille d'inventaire {$stockCount->reference} annulée.");
    }

    /**
     * Procès-Verbal officiel d'inventaire physique et d'écarts de stocks (PV).
     * Vue imprimable conforme aux standards d'audit et sans interface web polluante.
     */
    public function report(StockCount $stockCount): View
    {
        $stockCount->load(['category', 'openedBy', 'closedBy', 'lines.item.category']);

        $lines = $stockCount->lines->sortBy(fn (StockCountLine $l) => $l->item?->name ?? '');

        return view('economat.stock_counts.report', [
            'count'  => $stockCount,
            'lines'  => $lines,
            'tenant' => \App\Models\Tenant::first(),
        ]);
    }
}
