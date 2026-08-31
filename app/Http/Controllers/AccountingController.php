<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Module comptabilité (comptabilité de caisse). Piloté par le rôle Comptable
 * (+ manager / admin). Toute la logique de calcul vit dans AccountingService.
 */
class AccountingController extends Controller
{
    use \App\Http\Controllers\Concerns\PaginatesLists;

    private const EXPENSE_METHODS = ['cash', 'bank_transfer', 'orange_money', 'mtn_momo', 'check', 'other'];

    public function __construct(private readonly AccountingService $accounting)
    {
    }

    /** Tableau de bord comptable. */
    public function index(Request $request): View
    {
        $period = $this->period($request);
        $resultat = $this->accounting->compteDeResultat($period['from'], $period['to']);
        $recettes = $this->accounting->recettes($period['from'], $period['to']);
        $caisse = $this->accounting->caisse($period['from'], $period['to']);
        $creances = $this->accounting->creances();

        return view('accounting.index', compact('period', 'resultat', 'recettes', 'caisse', 'creances'));
    }

    /** Cahier des recettes et des dépenses (journal chronologique). */
    public function journal(Request $request): View
    {
        $period = $this->period($request);
        $entries = $this->accounting->journal($period['from'], $period['to']);

        $totalRecettes = (int) $entries->sum('recette');
        $totalDepenses = (int) $entries->sum('depense');

        return view('accounting.journal', compact('period', 'entries', 'totalRecettes', 'totalDepenses'));
    }

    /** Compte de résultat de la période. */
    public function incomeStatement(Request $request): View
    {
        $period = $this->period($request);
        $resultat = $this->accounting->compteDeResultat($period['from'], $period['to']);

        return view('accounting.income-statement', [
            'period' => $period,
            'resultat' => $resultat,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    /** Créances : ce qu'on nous doit (instantané). */
    public function receivables(): View
    {
        $creances = $this->accounting->creances();

        return view('accounting.receivables', compact('creances'));
    }

    /** Caisse : sessions et rapprochement sur la période. */
    public function cash(Request $request): View
    {
        $period = $this->period($request);
        $caisse = $this->accounting->caisse($period['from'], $period['to']);

        return view('accounting.cash', compact('period', 'caisse'));
    }

    // ── Dépenses ─────────────────────────────────────────────────────────────

    public function expenses(Request $request): View
    {
        $period = $this->period($request);

        $requete = Expense::query()
            ->inPeriod($period['from'], $period['to']);

        // Le total porte sur la période entière, pas sur la page affichée : le
        // tirer du paginateur donnerait le cumul des quinze lignes visibles et
        // afficherait un montant faux dès la seconde page.
        $total = (int) (clone $requete)->sum('amount');

        $expenses = $requete
            ->with('recordedBy:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PAR_PAGE)
            ->withQueryString();

        return view('accounting.expenses', [
            'period' => $period,
            'expenses' => $expenses,
            'total' => $total,
            'categories' => Expense::CATEGORIES,
            'methods' => self::EXPENSE_METHODS,
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $this->validateExpense($request);

        Expense::create($this->expenseAttributes($request, $data));

        return redirect()
            ->route('accounting.expenses', ['month' => $request->input('month')])
            ->with('success', 'Dépense enregistrée.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $data = $this->validateExpense($request);

        $attributes = $this->expenseAttributes($request, $data, $expense);
        $expense->update($attributes);

        return redirect()
            ->route('accounting.expenses', ['month' => $request->input('month')])
            ->with('success', 'Dépense mise à jour.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        if ($expense->receipt_path) {
            Storage::disk('public')->delete($expense->receipt_path);
        }
        $expense->delete();

        return redirect()
            ->route('accounting.expenses', ['month' => $request->input('month')])
            ->with('success', 'Dépense supprimée.');
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'occurred_at'    => ['required', 'date'],
            'category'       => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'label'          => ['required', 'string', 'max:180'],
            // Saisi en FCFA -> stocké en centimes
            'amount'         => ['required', 'integer', 'min:1', 'max:2000000000'],
            'payment_method' => ['nullable', Rule::in(self::EXPENSE_METHODS)],
            'receipt'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,pdf', 'max:4096'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function expenseAttributes(Request $request, array $data, ?Expense $expense = null): array
    {
        $attributes = [
            'occurred_at'    => Carbon::parse($data['occurred_at']),
            'category'       => $data['category'],
            'label'          => trim($data['label']),
            'amount'         => (int) $data['amount'] * 100,
            'payment_method' => $data['payment_method'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ];

        if ($expense === null) {
            $attributes['recorded_by'] = Auth::id();
        }

        if ($request->hasFile('receipt')) {
            if ($expense?->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }
            $attributes['receipt_path'] = $request->file('receipt')->store('expense_receipts', 'public');
        }

        return $attributes;
    }

    // ── Période ──────────────────────────────────────────────────────────────

    /**
     * Résout la période analysée : ?from & ?to explicites, sinon ?month=YYYY-MM,
     * sinon le mois courant.
     *
     * @return array{from:Carbon, to:Carbon, label:string, month:?string}
     */
    private function period(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse($request->input('from'))->startOfDay();
                $to = Carbon::parse($request->input('to'))->endOfDay();
                if ($to->lt($from)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }

                return [
                    'from' => $from,
                    'to' => $to,
                    'label' => $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y'),
                    'month' => null,
                ];
            } catch (\Throwable) {
                // Retombe sur le mois courant.
            }
        }

        $monthStr = (string) $request->input('month', now()->format('Y-m'));
        try {
            $from = Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth();
        } catch (\Throwable) {
            $from = now()->startOfMonth();
            $monthStr = $from->format('Y-m');
        }

        return [
            'from' => $from,
            'to' => $from->copy()->endOfMonth(),
            'label' => $from->locale('fr')->isoFormat('MMMM YYYY'),
            'month' => $monthStr,
        ];
    }
}
