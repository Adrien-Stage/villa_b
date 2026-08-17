<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\LedgerReportService;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Comptabilité générale (SYSCOHADA révisé).
 *
 * Coexiste avec AccountingController, qui tient la comptabilité de caisse :
 * l'un répond à « combien est entré aujourd'hui », l'autre à l'obligation
 * légale et au pilotage. Les deux lisent les mêmes faits.
 */
class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly LedgerReportService $reports,
    ) {
    }

    /** Tableau de bord : santé du grand livre et état des périodes. */
    public function index(Request $request): View
    {
        $exercice = $this->reports->currentFiscalYear();
        $periode  = $this->period($request, $exercice);

        $balance = $this->reports->balance($periode['from'], $periode['to']);
        $totaux  = $this->reports->balanceTotals($balance);

        // Périodes dont le délai de verrouillage est dépassé : c'est le
        // signal que l'Article 22 n'est plus respecté.
        $enRetard = $exercice
            ? $exercice->periods()->get()->filter(fn (FiscalPeriod $p) => $p->isOverdue())
            : collect();

        $dernieres = JournalEntry::with('journal')->latest('entry_date')->latest('id')->limit(8)->get();

        return view('accounting.ledger.index', compact('exercice', 'periode', 'balance', 'totaux', 'enRetard', 'dernieres'));
    }

    /** Plan de comptes. */
    public function accounts(Request $request): View
    {
        $classe = $request->filled('classe') ? (int) $request->input('classe') : null;

        $comptes = Account::query()
            ->when($classe, fn ($q) => $q->where('account_class', $classe))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = $request->input('q');
                $q->where(fn ($sub) => $sub->where('code', 'like', "%{$terme}%")
                    ->orWhere('label', 'like', "%{$terme}%"));
            })
            ->orderBy('code')
            ->get()
            ->groupBy('account_class');

        return view('accounting.ledger.accounts', compact('comptes', 'classe'));
    }

    /** Journaux et leurs écritures. */
    public function journals(Request $request): View
    {
        $exercice = $this->reports->currentFiscalYear();
        $periode  = $this->period($request, $exercice);

        $journaux = Journal::orderBy('code')->get();
        $journal  = $request->filled('journal')
            ? $journaux->firstWhere('id', (int) $request->input('journal'))
            : null;

        $ecritures = $this->reports->entries($periode['from'], $periode['to'], $journal?->id);

        return view('accounting.ledger.journals', compact('journaux', 'journal', 'ecritures', 'periode'));
    }

    /** Grand livre d'un compte. */
    public function generalLedger(Request $request): View
    {
        $exercice = $this->reports->currentFiscalYear();
        $periode  = $this->period($request, $exercice);

        $comptes = $this->reports->movedAccounts($periode['from'], $periode['to']);
        $code    = $request->input('compte') ?: $comptes->first()?->code;

        $compte  = $code ? Account::where('code', $code)->first() : null;
        $detail  = $compte ? $this->reports->generalLedger($compte->code, $periode['from'], $periode['to']) : null;

        return view('accounting.ledger.general', compact('comptes', 'compte', 'detail', 'periode'));
    }

    /** Balance générale. */
    public function balance(Request $request): View
    {
        $exercice = $this->reports->currentFiscalYear();
        $periode  = $this->period($request, $exercice);
        $classe   = $request->filled('classe') ? (int) $request->input('classe') : null;

        $balance = $this->reports->balance($periode['from'], $periode['to'], $classe);
        $totaux  = $this->reports->balanceTotals($balance);

        return view('accounting.ledger.balance', compact('balance', 'totaux', 'periode', 'classe'));
    }

    /** Détail d'une écriture. */
    public function show(JournalEntry $entry): View
    {
        $entry->load(['lines', 'journal', 'period', 'reverses', 'reversedBy']);

        return view('accounting.ledger.entry', compact('entry'));
    }

    /** Contre-passation d'une écriture. */
    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Une contre-passation doit être motivée.',
        ]);

        try {
            $extourne = $this->ledger->reverse($entry, now(), $validated['reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.ledger.entry', $extourne)
            ->with('success', 'L’écriture a été contre-passée.');
    }

    // ── Périodes et exercices ───────────────────────────────────────────────

    /** Exercices et état de verrouillage de leurs périodes. */
    public function periods(): View
    {
        $exercices = FiscalYear::with('periods')->orderByDesc('starts_on')->get();

        return view('accounting.ledger.periods', compact('exercices'));
    }

    /** Ouvre un exercice et ses douze périodes. */
    public function openYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        FiscalYear::openYear((int) $validated['year']);

        return back()->with('success', "L’exercice {$validated['year']} est ouvert.");
    }

    /** Verrouille une période — irréversible (Article 22). */
    public function lockPeriod(FiscalPeriod $period): RedirectResponse
    {
        if ($period->isLocked()) {
            return back()->with('error', 'Cette période est déjà verrouillée.');
        }

        $this->ledger->lockPeriod($period);

        return back()->with('success', "La période {$period->label()} est verrouillée. Toute correction passera désormais par une contre-passation.");
    }

    // ── Balance d'ouverture (à-nouveaux) ────────────────────────────────────

    public function openingBalance(Request $request): View
    {
        $exercice = $request->filled('exercice')
            ? FiscalYear::find((int) $request->input('exercice'))
            : $this->reports->currentFiscalYear();

        $exercices = FiscalYear::orderByDesc('starts_on')->get();

        // Seuls les comptes de bilan se reprennent : les comptes de gestion
        // repartent à zéro à chaque exercice.
        $comptes = Account::postable()->where('account_class', '<=', 5)->orderBy('code')->get();

        return view('accounting.ledger.opening', compact('exercice', 'exercices', 'comptes'));
    }

    public function storeOpeningBalance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fiscal_year_id'   => ['required', 'exists:fiscal_years,id'],
            'lines'            => ['required', 'array', 'min:1'],
            'lines.*.account'  => ['nullable', 'string', 'exists:accounts,code'],
            'lines.*.debit'    => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'   => ['nullable', 'numeric', 'min:0'],
        ]);

        $exercice = FiscalYear::findOrFail($validated['fiscal_year_id']);

        // Montants saisis en francs, stockés en centimes.
        $lignes = collect($validated['lines'])
            ->filter(fn ($l) => !empty($l['account']) && ((float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0))
            ->map(fn ($l) => [
                'account' => $l['account'],
                'debit'   => (int) round((float) ($l['debit'] ?? 0) * 100),
                'credit'  => (int) round((float) ($l['credit'] ?? 0) * 100),
            ])
            ->values()
            ->all();

        if ($lignes === []) {
            return back()->with('error', 'Aucune ligne renseignée.')->withInput();
        }

        try {
            $this->ledger->postOpeningBalance($exercice, $lignes);
        } catch (\RuntimeException $e) {
            // Le déséquilibre est le cas le plus fréquent : le message du
            // service chiffre l'écart, on le remonte tel quel.
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('accounting.ledger.balance')
            ->with('success', "Les à-nouveaux de l’{$exercice->label} ont été repris.");
    }

    /**
     * Découpe la période demandée. Par défaut, l'exercice courant en entier :
     * une balance se lit sur l'exercice, pas sur le mois.
     *
     * @return array{from: Carbon, to: Carbon, label: string}
     */
    private function period(Request $request, ?FiscalYear $exercice): array
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
                // Retombe sur l'exercice.
            }
        }

        $from = $exercice?->starts_on->copy()->startOfDay() ?? now()->startOfYear();
        $to   = $exercice?->ends_on->copy()->endOfDay() ?? now()->endOfYear();

        return ['from' => $from, 'to' => $to, 'label' => $exercice?->label ?? 'Exercice ' . now()->year];
    }
}
