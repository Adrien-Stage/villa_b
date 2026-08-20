<?php

namespace App\Http\Controllers;

use App\Services\AnalyticPostingService;
use App\Services\AnalyticReportService;
use App\Services\LedgerReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Comptabilité analytique (classe 9).
 *
 * Répond à la question que la comptabilité générale ne pose pas : non pas
 * « combien avons-nous dépensé », mais « où cet argent est-il parti, et
 * qu'a-t-il rapporté ».
 */
class AnalyticController extends Controller
{
    public function __construct(
        private readonly AnalyticReportService $analytics,
        private readonly AnalyticPostingService $mirror,
        private readonly LedgerReportService $reports,
    ) {
    }

    /** Résultat par centre de profit, RevPAR et qualité de la ventilation. */
    public function index(Request $request): View
    {
        $periode = $this->period($request);

        $resultat    = $this->analytics->resultByCenter($periode['from'], $periode['to']);
        $revpar      = $this->analytics->revpar($periode['from'], $periode['to']);
        $ventilation = $this->analytics->ventilationRate($periode['from'], $periode['to']);
        $reflet      = $this->mirror->status($periode['from'], $periode['to']);

        return view('accounting.ledger.analytic', compact('periode', 'resultat', 'revpar', 'ventilation', 'reflet'));
    }

    /** Marges de contribution par type de chambre. */
    public function margins(Request $request): View
    {
        $periode = $this->period($request);
        $marges  = $this->analytics->contributionMargins();
        $revpar  = $this->analytics->revpar($periode['from'], $periode['to']);

        return view('accounting.ledger.margins', compact('periode', 'marges', 'revpar'));
    }

    /** Produit le reflet de classe 9 pour la période affichée. */
    public function postMirror(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from'  => ['required', 'date'],
            'to'    => ['required', 'date'],
            'force' => ['nullable', 'boolean'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to   = Carbon::parse($validated['to'])->endOfDay();

        try {
            $ecriture = $request->boolean('force')
                ? $this->mirror->remirror($from, $to)
                : $this->mirror->mirror($from, $to);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($ecriture === null) {
            return back()->with('error', 'Rien à refléter : soit la période est déjà reflétée, soit elle ne porte aucune charge.');
        }

        return back()->with('success', 'Reflet analytique enregistré — la classe 9 se solde à zéro, le bilan est inchangé.');
    }

    /**
     * Découpe la période. Par défaut le mois écoulé : l'analytique se lit au
     * mois, là où la balance se lit sur l'exercice.
     *
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    private function period(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse($request->input('from'))->startOfDay();
                $to   = Carbon::parse($request->input('to'))->endOfDay();

                if ($to->lt($from)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }

                return ['from' => $from, 'to' => $to, 'label' => $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y')];
            } catch (\Throwable) {
                // Retombe sur le mois courant.
            }
        }

        $from = now()->copy()->startOfMonth();
        $to   = now()->copy()->endOfMonth();

        return ['from' => $from, 'to' => $to, 'label' => $from->translatedFormat('F Y')];
    }
}
