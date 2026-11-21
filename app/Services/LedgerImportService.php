<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\LedgerImport;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class LedgerImportService
{
    public const HEADER = ['date', 'piece', 'compte', 'libelle', 'debit_centimes_xaf', 'credit_centimes_xaf', 'date_origine', 'centre'];

    public function __construct(
        private readonly AccountingTableReader $reader,
        private readonly LedgerService $ledger,
    ) {
    }

    public function import(UploadedFile $file, string $kind, string $reference): LedgerImport
    {
        $rows = $this->reader->read($file, self::HEADER);
        $entries = [];
        foreach ($rows as $index => [$date, $piece, $account, $label, $debit, $credit, $valueDate, $center]) {
            $validator = Validator::make(
                compact('date', 'piece', 'account', 'label', 'debit', 'credit', 'valueDate', 'center'),
                [
                    'date' => 'required|date_format:Y-m-d|before_or_equal:today',
                    'piece' => 'required|string|max:60', 'account' => 'required|string|max:20',
                    'label' => 'required|string|max:200',
                    'debit' => 'nullable|integer|min:0|max:200000000000', 'credit' => 'nullable|integer|min:0|max:200000000000',
                    'valueDate' => 'nullable|date_format:Y-m-d|before_or_equal:date',
                    'center' => 'nullable|regex:/^[a-z][a-z0-9_]{0,39}$/',
                ],
            );
            if ($validator->fails() || ((int) $debit === 0) === ((int) $credit === 0)) {
                throw new RuntimeException('Donnée comptable invalide à la ligne ' . ($index + 2) . '.');
            }
            if ($kind === 'payroll' && !preg_match('/^(66|42|43|44|52|53|57)/', $account)) {
                throw new RuntimeException('Compte hors du périmètre de paie à la ligne ' . ($index + 2) . '.');
            }
            $key = $date . '|' . $piece;
            $entries[$key] ??= ['date' => $date, 'piece' => $piece, 'value_date' => $valueDate, 'lines' => []];
            if ($entries[$key]['value_date'] !== $valueDate) {
                throw new RuntimeException("Les lignes de la pièce {$piece} doivent partager la même date d’origine.");
            }
            $entries[$key]['lines'][] = [
                'account' => $account, 'label' => $label, 'debit' => (int) $debit, 'credit' => (int) $credit,
                'center' => $center ?: null,
            ];
        }
        ksort($entries); // Même ordre de verrouillage des périodes pour tous les imports.
        $digest = hash('sha256', json_encode($entries, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($kind, $reference, $digest, $entries) {
                if ($previous = $this->existing($kind, $reference, $digest)) {
                    return $previous;
                }
                $batch = LedgerImport::create([
                    'kind' => $kind, 'reference' => $reference, 'digest' => $digest, 'imported_by' => Auth::id(),
                ]);

                foreach ($entries as $key => $entry) {
                    $date = Carbon::parse($entry['date']);
                    $this->ledger->lockForDate($date);
                    $duplicate = JournalEntry::query()->where('source_type', LedgerImport::class)
                        ->whereDate('entry_date', $date->toDateString())->where('reference', $entry['piece'])->exists();
                    if ($duplicate) {
                        throw new RuntimeException("La pièce {$entry['piece']} est déjà importée à cette date.");
                    }
                    $this->ledger->post(
                        journalCode: Journal::MISC,
                        date: $date,
                        label: ($kind === 'payroll' ? 'Paie — ' : 'Opérations diverses — ') . $entry['piece'],
                        lines: $entry['lines'],
                        source: $batch,
                        schema: 'import:' . substr(hash('sha256', $key), 0, 40),
                        reference: $entry['piece'],
                        valueDate: $entry['value_date'] !== '' ? Carbon::parse($entry['value_date']) : null,
                    );
                }

                return $batch;
            });
        } catch (UniqueConstraintViolationException $error) {
            return $this->existing($kind, $reference, $digest) ?? throw $error;
        }
    }

    private function existing(string $kind, string $reference, string $digest): ?LedgerImport
    {
        $existing = LedgerImport::query()->where('kind', $kind)
            ->where(fn ($q) => $q->where('reference', $reference)->orWhere('digest', $digest))->first();
        if ($existing && !hash_equals($existing->digest, $digest)) {
            throw new RuntimeException('Cette référence est déjà associée à un autre contenu.');
        }

        return $existing;
    }
}
