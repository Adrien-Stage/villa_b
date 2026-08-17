<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lecture du grand livre : balance, grand livre par compte, journaux.
 *
 * Séparé de LedgerService à dessein : l'un écrit et garantit les invariants,
 * l'autre ne fait que lire. Une méthode de ce service ne doit jamais produire
 * d'écriture.
 *
 * Montants en centimes FCFA.
 */
class LedgerReportService
{
    /**
     * Balance générale : un solde par compte mouvementé sur la période.
     *
     * Les comptes sans mouvement sont omis — une balance ne liste pas le plan
     * de comptes, elle liste ce qui a bougé.
     *
     * @return Collection<int, array{code:string, label:string, class:int, debit:int, credit:int, balance:int}>
     */
    public function balance(CarbonInterface $from, CarbonInterface $to, ?int $accountClass = null): Collection
    {
        $rows = JournalEntryLine::query()
            ->select(
                'journal_entry_lines.account_code',
                DB::raw('SUM(journal_entry_lines.debit) as total_debit'),
                DB::raw('SUM(journal_entry_lines.credit) as total_credit')
            )
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('journal_entries.posted_at')
            ->groupBy('journal_entry_lines.account_code')
            ->get();

        $accounts = Account::query()
            ->whereIn('code', $rows->pluck('account_code'))
            ->get()
            ->keyBy('code');

        return $rows
            ->map(function ($row) use ($accounts) {
                $account = $accounts->get($row->account_code);
                $debit   = (int) $row->total_debit;
                $credit  = (int) $row->total_credit;

                return [
                    'code'    => $row->account_code,
                    'label'   => $account?->label ?? 'Compte inconnu',
                    'class'   => $account?->account_class ?? 0,
                    'debit'   => $debit,
                    'credit'  => $credit,
                    'balance' => $debit - $credit,
                ];
            })
            ->when($accountClass !== null, fn ($c) => $c->where('class', $accountClass))
            ->sortBy('code')
            ->values();
    }

    /**
     * Totaux de la balance. Sur un grand livre sain, débits et crédits sont
     * rigoureusement égaux — c'est le contrôle de cohérence le plus direct.
     *
     * @return array{debit:int, credit:int, balanced:bool}
     */
    public function balanceTotals(Collection $balance): array
    {
        $debit  = (int) $balance->sum('debit');
        $credit = (int) $balance->sum('credit');

        return ['debit' => $debit, 'credit' => $credit, 'balanced' => $debit === $credit];
    }

    /**
     * Grand livre d'un compte : le détail chronologique de ses mouvements,
     * avec le solde progressif.
     *
     * @return array{lines: Collection<int, array<string, mixed>>, opening: int, debit: int, credit: int, closing: int}
     */
    public function generalLedger(string $accountCode, CarbonInterface $from, CarbonInterface $to): array
    {
        // Solde d'entrée : tout ce qui précède la période.
        $before = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entry_lines.account_code', $accountCode)
            ->whereDate('journal_entries.entry_date', '<', $from->toDateString())
            ->whereNotNull('journal_entries.posted_at')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) - COALESCE(SUM(journal_entry_lines.credit), 0) as solde')
            ->value('solde');

        $opening = (int) $before;

        $lines = JournalEntryLine::query()
            ->with(['entry.journal'])
            ->where('account_code', $accountCode)
            ->whereHas('entry', fn ($q) => $q
                ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotNull('posted_at'))
            ->get()
            ->sortBy(fn ($line) => [$line->entry->entry_date->timestamp, $line->id])
            ->values();

        $running = $opening;

        $rows = $lines->map(function ($line) use (&$running) {
            $running += $line->signedAmount();

            return [
                'date'        => $line->entry->entry_date,
                'journal'     => $line->entry->journal?->code,
                'reference'   => $line->entry->reference,
                'label'       => $line->label ?: $line->entry->label,
                'debit'       => $line->debit,
                'credit'      => $line->credit,
                'balance'     => $running,
                'auxiliary'   => $line->auxiliary_type ? $line->auxiliary : null,
                'reconciled'  => $line->isReconciled(),
                'entry_id'    => $line->entry->id,
            ];
        });

        return [
            'lines'   => $rows,
            'opening' => $opening,
            'debit'   => (int) $lines->sum('debit'),
            'credit'  => (int) $lines->sum('credit'),
            'closing' => $running,
        ];
    }

    /** Écritures d'un journal, ou de tous, sur une période. */
    public function entries(CarbonInterface $from, CarbonInterface $to, ?int $journalId = null, int $perPage = 40)
    {
        return JournalEntry::query()
            ->with(['journal', 'lines'])
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->when($journalId, fn ($q) => $q->where('journal_id', $journalId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Comptes mouvementés sur la période, pour alimenter le sélecteur du
     * grand livre — inutile de proposer un compte resté vide.
     *
     * @return Collection<int, Account>
     */
    public function movedAccounts(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $codes = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereBetween('journal_entries.entry_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('journal_entries.posted_at')
            ->distinct()
            ->pluck('journal_entry_lines.account_code');

        return Account::query()->whereIn('code', $codes)->orderBy('code')->get();
    }

    /** Exercice courant, ou le plus récent s'il n'y en a pas pour aujourd'hui. */
    public function currentFiscalYear(): ?FiscalYear
    {
        return FiscalYear::forDate(now()) ?? FiscalYear::query()->orderByDesc('starts_on')->first();
    }
}
