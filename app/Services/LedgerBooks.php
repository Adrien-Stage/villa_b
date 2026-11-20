<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LedgerBooks
{
    public function __construct(private readonly LedgerReportService $reports)
    {
    }

    /** @return list<Document> */
    public function documents(Carbon $from, Carbon $to): array
    {
        $lines = collect(iterator_to_array($this->lines($from, $to), false));
        $columns = [
            Colonne::date('date', 'Date comptable'), Colonne::date('value_date', 'Date d’origine'),
            Colonne::texte('number', 'Écriture'), Colonne::texte('journal', 'Journal'),
            Colonne::texte('reference', 'Pièce'), Colonne::texte('account', 'Compte'),
            Colonne::texte('label', 'Libellé'), Colonne::montant('debit', 'Débit'),
            Colonne::montant('credit', 'Crédit'),
        ];
        $totals = ['debit' => $lines->sum('debit'), 'credit' => $lines->sum('credit')];
        $journal = $this->document('Livre-journal', $from, $to)->colonnes($columns)
            ->lignes($lines)->totaux($totals);

        $balanceRows = $this->balance($from, $to);
        $balance = $this->document('Balance générale', $from, $to)->colonnes([
            Colonne::texte('code', 'Compte'), Colonne::texte('label', 'Intitulé'),
            Colonne::montant('opening', 'Solde initial'), Colonne::montant('debit', 'Débit'),
            Colonne::montant('credit', 'Crédit'), Colonne::montant('closing', 'Solde final'),
        ])->lignes($balanceRows)->totaux($totals);

        $running = $balanceRows->pluck('opening', 'code')->all();
        $byAccount = $lines->sortBy('account', SORT_STRING)->map(function (array $line) use (&$running) {
            $account = $line['account'];
            $running[$account] = ($running[$account] ?? 0) + $line['debit'] - $line['credit'];

            return $line + ['running' => $running[$account]];
        });
        $ledger = $this->document('Grand livre', $from, $to)
            ->colonnes([...$columns, Colonne::montant('running', 'Solde du compte', false)])
            ->lignes($byAccount)->totaux($totals);

        return [$journal, $balance, $ledger];
    }

    public function balance(Carbon $from, Carbon $to): Collection
    {
        $yearStart = $from->copy()->startOfYear();
        $opening = $from->isSameDay($yearStart) ? collect()
            : $this->reports->balance($yearStart, $from->copy()->subDay())->keyBy('code');
        $movements = $this->reports->balance($from, $to)->keyBy('code');

        return $opening->keys()->merge($movements->keys())->unique()->sort(SORT_STRING)->values()
            ->map(function ($code) use ($opening, $movements) {
                $row = $movements->get($code) ?? $opening->get($code);
                $start = $opening->get($code)['balance'] ?? 0;
                $debit = $movements->get($code)['debit'] ?? 0;
                $credit = $movements->get($code)['credit'] ?? 0;

                return [
                    'code' => $row['code'], 'label' => $row['label'], 'class' => $row['class'],
                    'opening' => $start, 'debit' => $debit, 'credit' => $credit, 'closing' => $start + $debit - $credit,
                ];
            });
    }

    public function entries(Carbon $from, Carbon $to): Builder
    {
        return JournalEntry::query()->whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->whereNotNull('posted_at');
    }

    public function lines(Carbon $from, Carbon $to): iterable
    {
        $entries = $this->entries($from, $to)->with(['journal', 'lines' => fn ($q) => $q->orderBy('id')])
            ->orderBy('entry_date')->orderBy('id')->lazy();
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                yield [
                    'number' => $entry->number(), 'line_id' => $line->id,
                    'date' => $entry->entry_date->toDateString(), 'value_date' => $entry->value_date?->toDateString(),
                    'journal' => $entry->journal->code, 'reference' => $entry->reference,
                    'account' => $line->account_code, 'label' => $line->label ?: $entry->label,
                    'debit' => $line->debit, 'credit' => $line->credit, 'center' => $line->analytic_center,
                    'source_type' => $entry->source_type, 'source_id' => $entry->source_id,
                    'auxiliary_type' => $line->auxiliary_type, 'auxiliary_id' => $line->auxiliary_id,
                    'validated_at' => $entry->posted_at->toDateTimeString(), 'validated_by' => $entry->validated_by,
                    'reverses' => $entry->reverses_id, 'reversed_by' => $entry->reversed_by_id,
                ];
            }
        }
    }

    public function document(string $title, Carbon $from, Carbon $to): Document
    {
        return Document::intitule($title)->periode($from, $to)->filtres([
            'Édité le' => now()->format('d/m/Y H:i'),
        ]);
    }
}
