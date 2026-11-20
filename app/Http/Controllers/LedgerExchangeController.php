<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\LedgerImport;
use App\Models\Payment;
use App\Services\BankReconciliation;
use App\Services\LedgerImportService;
use App\Services\PaymentAccounting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

class LedgerExchangeController extends Controller
{
    public function index(Request $request, BankReconciliation $bank)
    {
        $accounts = Account::query()->postable()->where('account_class', 5)->orderBy('code')->get()
            ->filter(fn ($account) => preg_match('/^(52|53)/', $account->code));
        $statements = BankStatement::query()->latest('id')->limit(30)->get();
        $statement = $request->filled('statement') ? BankStatement::query()->findOrFail($request->integer('statement')) : $statements->first();
        $lines = $statement?->lines()->with('ledgerLine.entry')->orderBy('position')->paginate(25)->withQueryString();
        $line = $request->filled('line') ? $statement?->lines()->findOrFail($request->integer('line')) : null;
        $candidates = $line && !$line->journal_entry_line_id ? $bank->candidates($line) : collect();
        $suggestion = $candidates->filter(fn ($candidate) => $candidate->entry->reference === $line->reference);
        $imports = LedgerImport::query()->latest('id')->limit(15)->get();

        return response()->view('accounting.ledger.exchange',
            compact('accounts', 'statements', 'statement', 'lines', 'line', 'candidates', 'suggestion', 'imports'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function import(Request $request, BankReconciliation $bank, LedgerImportService $journals)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['bank', 'journal', 'payroll'])],
            'reference' => ['required', 'string', 'max:60'],
            'account_code' => ['required_if:kind,bank', 'nullable', 'string', 'max:20'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
        ]);

        try {
            if ($data['kind'] === 'bank') {
                $statement = $bank->import($data['file'], $data['account_code'], $data['reference']);

                return redirect()->route('accounting.ledger.exchange.index', ['statement' => $statement->id])
                    ->with('success', 'Le relevé est disponible pour le pointage.');
            }
            $batch = $journals->import($data['file'], $data['kind'], $data['reference']);
        } catch (RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('accounting.ledger.exchange.index')
            ->with('success', "Le lot {$batch->reference} est comptabilisé.");
    }

    public function matchLine(Request $request, BankStatementLine $line, BankReconciliation $bank)
    {
        $data = $request->validate(['line_id' => ['nullable', 'integer', 'exists:journal_entry_lines,id']]);
        try {
            $bank->setMatch($line, isset($data['line_id']) ? (int) $data['line_id'] : null);
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('accounting.ledger.exchange.index', ['statement' => $line->bank_statement_id])
            ->with('success', 'Le pointage a été mis à jour.');
    }

    public function payment(Request $request, PaymentAccounting $accounting)
    {
        $data = $request->validate([
            'operation' => ['required', Rule::in(['exchange', 'payout', 'stamp'])],
            'payment_reference' => ['required', 'string', 'max:80'],
            'reference' => ['required', 'string', 'max:60'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'currency' => ['required_if:operation,exchange', 'nullable', 'regex:/^[A-Z]{3}$/', 'not_in:XAF'],
            'original_minor' => ['required_if:operation,exchange', 'nullable', 'integer', 'between:1,200000000000'],
            'minor_digits' => ['required_if:operation,exchange', 'nullable', 'integer', 'between:0,3'],
            'xaf_rate' => ['required_if:operation,exchange', 'nullable', 'regex:/^\d{1,8}(?:\.\d{1,8})?$/', 'numeric', 'gt:0'],
            'amount' => ['required_unless:operation,exchange', 'nullable', 'integer', 'between:0,2000000000'],
            'legal_reference' => ['required_if:operation,stamp', 'nullable', 'string', 'max:180'],
        ]);
        try {
            $payment = Payment::query()->where('reference', $data['payment_reference'])->first();
            if (!$payment) {
                throw new RuntimeException('Cette référence ne correspond à aucun paiement.');
            }
            $date = Carbon::parse($data['date']);
            if ($data['operation'] === 'exchange') {
                $accounting->recordExchange($payment, [
                    'currency' => $data['currency'], 'original_minor' => (int) $data['original_minor'],
                    'minor_digits' => (int) $data['minor_digits'], 'xaf_rate' => $data['xaf_rate'],
                    'rate_on' => $data['date'], 'reference' => $data['reference'],
                ]);

                return back()->with('success', 'Le justificatif de change a été enregistré.');
            }
            $amount = (int) $data['amount'] * 100;
            $entry = $data['operation'] === 'stamp'
                ? $accounting->recordStamp($payment, $date, $amount, $data['reference'], $data['legal_reference'])
                : $accounting->settle($payment, $date, $amount, $data['reference']);
        } catch (RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('accounting.ledger.entry', $entry)->with('success', 'La pièce a été comptabilisée.');
    }
}
