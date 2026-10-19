<?php

namespace App\Http\Controllers\Economat;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCsv;
use App\Models\ServiceStore;
use App\Models\StockImport;
use App\Services\ClasseurStockExport;
use App\Services\ClasseurStockImport;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Classeur Excel de l'économat : exporter un magasin (central ou dépôt) pour
 * le gérer dans Excel, puis réimporter le fichier — aperçu d'abord, écriture
 * sur confirmation.
 */
class StockWorkbookController extends Controller
{
    use HandlesCsv;

    public function __construct(
        private readonly ClasseurStockExport $export,
        private readonly ClasseurStockImport $import,
    ) {
    }

    public function index(Request $request): View
    {
        return view('economat.classeur.index', [
            'depots'  => ServiceStore::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'imports' => StockImport::query()->with('store', 'creator', 'applier')->latest('id')->take(20)->get(),
            'choisi'  => $request->integer('depot') ?: null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $depot = $this->depot($request);
        $tenant = $this->tenantCourant();
        $classeur = $this->export->classeur($this->export->donnees($depot), $tenant);

        $nom = 'classeur_stock_' . ($depot ? Str::slug($depot->name) : 'economat') . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($classeur) {
            $writer = new Xlsx($classeur);
            // Excel recalcule à l'ouverture : inutile d'évaluer ici des
            // milliers de formules de stock.
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $classeur->disconnectWorksheets();
        }, $nom, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Dépôt du fichier : analyse et aperçu, rien n'est encore écrit au stock. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,ods'],
            'depot'   => ['nullable', 'integer', 'exists:service_stores,id'],
        ], [
            'fichier.required' => 'Choisissez le classeur à importer.',
            'fichier.mimes'    => 'Le classeur doit être un fichier Excel (.xlsx, .xls) ou LibreOffice (.ods).',
        ]);

        $depot = $this->depot($request);
        $fichier = $request->file('fichier');
        $chemin = $fichier->storeAs('classeurs-stock', Str::uuid() . '.' . strtolower($fichier->getClientOriginalExtension()));

        try {
            $analyse = $this->import->analyser($this->import->lire(\Illuminate\Support\Facades\Storage::path($chemin)), $depot);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::delete($chemin);
            report($e);

            return back()->with('error', 'Ce fichier ne se lit pas comme un classeur : ' . $e->getMessage());
        }

        $brouillon = StockImport::create([
            'service_store_id' => $depot?->id,
            'file_name'        => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'file_path'        => $chemin,
            'analysis'         => $analyse,
            'created_by'       => Auth::id(),
        ]);

        return redirect()->route('economat.classeur.show', $brouillon);
    }

    public function show(StockImport $import): View
    {
        $import->load('store', 'creator', 'applier');

        return view('economat.classeur.show', [
            'import'       => $import,
            'analyse'      => $import->analysis ?? [],
            'peutConfirmer' => $import->isDraft()
                && empty($import->analysis['erreurs'])
                && app(PermissionResolver::class)->allows(Auth::user(), 'economat.classeur.confirm'),
        ]);
    }

    /** Confirmation : le fichier est relu, réanalysé, puis appliqué d'un bloc. */
    public function confirm(StockImport $import): RedirectResponse
    {
        try {
            $analyse = $this->import->appliquer($import);
        } catch (\RuntimeException $e) {
            return redirect()->route('economat.classeur.show', $import)->with('error', $e->getMessage());
        }

        $r = $analyse['resume'];

        return redirect()->route('economat.classeur.show', $import)->with('success',
            "Import {$import->reference} enregistré : {$r['articles_crees']} article(s) créé(s), {$r['articles_modifies']} modifié(s), "
            . "{$r['mouvements']} mouvement(s). Téléchargez à nouveau le classeur pour continuer sur une version à jour.");
    }

    private function depot(Request $request): ?ServiceStore
    {
        $id = $request->integer('depot');

        return $id ? ServiceStore::query()->findOrFail($id) : null;
    }
}
