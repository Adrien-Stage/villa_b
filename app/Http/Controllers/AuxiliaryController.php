<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\AuxiliaryLedgerService;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Comptabilité auxiliaire : le détail par tiers derrière les comptes
 * collectifs, et le lettrage qui distingue le réglé de ce qui reste dû.
 *
 * C'est ce module qui rend possible une balance âgée — la page `creances()`
 * de la comptabilité de caisse liste des impayés sans jamais dire depuis
 * combien de temps ils le sont.
 */
class AuxiliaryController extends Controller
{
    public function __construct(
        private readonly AuxiliaryLedgerService $auxiliary,
        private readonly ReconciliationService $reconciliation,
    ) {
    }

    /** Soldes par tiers sur un compte collectif. */
    public function index(Request $request): View
    {
        $collectifs = $this->auxiliary->collectiveAccounts();
        $compte     = $this->compte($request, $collectifs);
        $ouverts    = !$request->boolean('tout');

        $tiers = $compte
            ? $this->auxiliary->balances($compte->code, $ouverts)
            : collect();

        $totaux = [
            'debit'   => (int) $tiers->sum('debit'),
            'credit'  => (int) $tiers->sum('credit'),
            'balance' => (int) $tiers->sum('balance'),
        ];

        return view('accounting.ledger.auxiliary', compact('collectifs', 'compte', 'tiers', 'totaux', 'ouverts'));
    }

    /** Grand livre d'un tiers, avec sélection des lignes à lettrer. */
    public function ledger(Request $request): View
    {
        $collectifs = $this->auxiliary->collectiveAccounts();
        $compte     = $this->compte($request, $collectifs);

        $type = $request->input('type');
        $id   = (int) $request->input('id');

        abort_unless($compte && $type && $id, 404);

        // Le type d'auxiliaire vient de l'URL : on n'accepte que des modèles
        // réellement utilisés comme tiers, jamais une classe arbitraire.
        abort_unless($this->typeAutorise($compte->code, $type), 403);

        $detail = $this->auxiliary->ledger($compte->code, $type, $id);
        $nom    = $this->auxiliary->label($compte->code, $type, $id);

        return view('accounting.ledger.auxiliary-detail', compact('collectifs', 'compte', 'detail', 'nom', 'type', 'id'));
    }

    /** Balance âgée : les impayés ventilés par ancienneté. */
    public function agedBalance(Request $request): View
    {
        $collectifs = $this->auxiliary->collectiveAccounts();
        $compte     = $this->compte($request, $collectifs);

        $arrete = $request->filled('au')
            ? Carbon::parse($request->input('au'))->endOfDay()
            : now();

        $balance = $compte
            ? $this->auxiliary->agedBalance($compte->code, $arrete)
            : ['rows' => collect(), 'totals' => []];

        $buckets = AuxiliaryLedgerService::BUCKETS;

        return view('accounting.ledger.aged', compact('collectifs', 'compte', 'balance', 'buckets', 'arrete'));
    }

    /** Lettrage manuel des lignes cochées. */
    public function reconcile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lines'   => ['required', 'array', 'min:2'],
            'lines.*' => ['integer', 'exists:journal_entry_lines,id'],
        ], [
            'lines.required' => 'Sélectionnez les lignes à lettrer.',
            'lines.min'      => 'Un lettrage rapproche au moins deux lignes.',
        ]);

        try {
            $lettre = $this->reconciliation->reconcile($validated['lines']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Lettrage {$lettre} enregistré.");
    }

    /** Délettrage : la créance redevient un poste ouvert. */
    public function unreconcile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:16'],
        ]);

        $lignes = $this->reconciliation->unreconcile($validated['code']);

        if ($lignes === 0) {
            return back()->with('error', "Aucune ligne ne porte la lettre {$validated['code']}.");
        }

        return back()->with('success', "Lettrage {$validated['code']} annulé — {$lignes} ligne(s) rouvertes.");
    }

    /** Lettrage automatique sur un compte collectif. */
    public function autoReconcile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'compte' => ['required', 'string', 'exists:accounts,code'],
        ]);

        $lettres = $this->reconciliation->autoReconcile($validated['compte']);

        if ($lettres === 0) {
            return back()->with('error', 'Aucun rapprochement évident trouvé. Les règlements partiels se lettrent à la main.');
        }

        return back()->with('success', "{$lettres} lettrage(s) automatique(s) enregistré(s).");
    }

    /** Compte collectif demandé, ou le premier disponible. */
    private function compte(Request $request, $collectifs): ?Account
    {
        $code = $request->input('compte');

        return $code
            ? $collectifs->firstWhere('code', $code)
            : $collectifs->first();
    }

    /** Le type de tiers doit être un modèle réellement mouvementé sur ce compte. */
    private function typeAutorise(string $accountCode, string $type): bool
    {
        return \App\Models\JournalEntryLine::query()
            ->where('account_code', $accountCode)
            ->where('auxiliary_type', $type)
            ->exists();
    }
}
