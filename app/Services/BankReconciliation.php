<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class BankReconciliation
{
    public const HEADER = ['date', 'reference', 'montant_centimes_xaf', 'libelle'];

    public function __construct(private readonly AccountingTableReader $reader)
    {
    }

    public function import(UploadedFile $file, string $accountCode, string $reference): BankStatement
    {
        $rows = $this->reader->read($file, self::HEADER);
        $items = [];
        foreach ($rows as $index => [$date, $ref, $amount, $label]) {
            $data = ['booked_on' => $date, 'reference' => $ref, 'amount' => $amount, 'label' => $label];
            $validator = Validator::make($data, [
                'booked_on' => 'required|date_format:Y-m-d', 'reference' => 'required|string|max:80',
                'amount' => 'required|integer|between:-200000000000,200000000000|not_in:0', 'label' => 'required|string|max:255',
            ]);
            if ($validator->fails() || (int) $amount === 0) {
                throw new RuntimeException('Date, référence, montant ou libellé invalide à la ligne ' . ($index + 2) . '.');
            }
            $items[] = array_replace($data, ['amount' => (int) $amount, 'position' => $index + 1]);
        }
        $digest = hash('sha256', json_encode($items, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($accountCode, $reference, $items, $digest) {
            $account = Account::query()->postable()->where('code', $accountCode)->where('account_class', 5)
                ->lockForUpdate()->first();
            if (!$account || !preg_match('/^(52|53)/', $accountCode)) {
                throw new RuntimeException('Choisissez un compte bancaire ou d’opérateur de paiement imputable.');
            }
            $existing = BankStatement::query()->where('account_code', $accountCode)
                ->where(fn ($q) => $q->where('reference', $reference)->orWhere('digest', $digest))->first();
            if ($existing) {
                if (hash_equals($existing->digest, $digest)) {
                    return $existing;
                }
                throw new RuntimeException('Un relevé différent porte déjà cette référence pour ce compte.');
            }

            $statement = BankStatement::create([
                'account_code' => $accountCode, 'reference' => $reference, 'digest' => $digest, 'imported_by' => Auth::id(),
            ]);
            foreach (array_chunk($items, 100) as $chunk) {
                DB::table('bank_statement_lines')->insert(array_map(fn ($item) => $item + [
                    'bank_statement_id' => $statement->id, 'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }

            return $statement;
        });
    }

    public function candidates(BankStatementLine $line): Collection
    {
        return JournalEntryLine::query()
            ->where('account_code', $line->statement->account_code)
            ->whereRaw('debit - credit = ?', [$line->amount])
            ->whereNotIn('id', BankStatementLine::query()->select('journal_entry_line_id')->whereNotNull('journal_entry_line_id'))
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at')
                ->whereDate('entry_date', '>=', $line->booked_on->copy()->subDays(7)->toDateString())
                ->whereDate('entry_date', '<=', $line->booked_on->copy()->addDays(7)->toDateString()))
            ->with('entry')->orderBy('id')->limit(50)->get();
    }

    public function setMatch(BankStatementLine $bankLine, ?int $ledgerLineId): void
    {
        DB::transaction(function () use ($bankLine, $ledgerLineId) {
            $bankLine = BankStatementLine::query()->lockForUpdate()->findOrFail($bankLine->id);
            $previous = $bankLine->journal_entry_line_id;
            if ($previous === $ledgerLineId) {
                return;
            }
            if ($previous !== null && $ledgerLineId !== null) {
                throw new RuntimeException('Retirez le pointage existant avant de choisir une autre écriture.');
            }
            if ($ledgerLineId !== null) {
                $candidate = JournalEntryLine::query()->with('entry')->lockForUpdate()->findOrFail($ledgerLineId);
                if (!$candidate->entry->isPosted() || $candidate->signedAmount() !== $bankLine->amount
                    || $candidate->account_code !== $bankLine->statement->account_code
                    || BankStatementLine::query()->where('journal_entry_line_id', $ledgerLineId)->exists()) {
                    throw new RuntimeException('Le compte et le montant signé doivent correspondre à une ligne validée disponible.');
                }
            }

            $bankLine->update(['journal_entry_line_id' => $ledgerLineId]);
            DB::table('bank_reconciliation_events')->insert([
                'bank_statement_line_id' => $bankLine->id, 'journal_entry_line_id' => $ledgerLineId ?? $previous,
                'matched' => $ledgerLineId !== null, 'actor_id' => Auth::id(), 'recorded_at' => now(),
            ]);
        });
    }
}
