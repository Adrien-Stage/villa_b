<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FixedAssetRegister
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function register(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $line = JournalEntryLine::query()->with(['account', 'entry'])->lockForUpdate()->findOrFail($data['journal_entry_line_id']);
            if (!$line->entry->isPosted() || $line->entry->isReversed() || $line->debit <= 0
                || !preg_match('/^2[1-7]/', $line->account_code) || $line->account?->account_class !== 2) {
                throw new RuntimeException('Indiquez le débit d’acquisition sur un compte d’immobilisation, dans une écriture validée.');
            }
            if ((int) $data['residual_amount'] > $line->debit
                || Carbon::parse($data['in_service_on'])->isBefore($line->entry->entry_date)
                || str_starts_with($line->account_code, '22') && (int) $data['useful_months'] > 0) {
                throw new RuntimeException('Vérifiez la mise en service, la durée et la valeur résiduelle.');
            }
            if (FixedAsset::query()->where('journal_entry_line_id', $line->id)->orWhere('reference', $data['reference'])->exists()) {
                throw new RuntimeException('Cette ligne d’acquisition ou cette référence figure déjà au registre.');
            }
            $data['registered_by'] = Auth::id();

            return FixedAsset::create($data);
        });
    }

    public function depreciation(FixedAsset $asset, Carbon $date): ?JournalEntry
    {
        return DB::transaction(function () use ($asset, $date) {
            $asset = FixedAsset::query()->with('acquisitionLine.entry')->lockForUpdate()->findOrFail($asset->id);

            return $this->postDepreciation($asset, $date);
        });
    }

    private function postDepreciation(FixedAsset $asset, Carbon $date): ?JournalEntry
    {
        if ($asset->disposed_on || $asset->acquisitionLine->entry->isReversed()) {
            throw new RuntimeException('L’actif est sorti ou son acquisition a été contre-passée.');
        }
        if ($date->toDateString() > today()->toDateString()) {
            throw new RuntimeException('Choisissez une date comptable au plus tard aujourd’hui.');
        }
        if ($asset->useful_months === 0 || $date->isBefore($asset->in_service_on)) {
            return null;
        }
        $schema = 'fixed_asset_depreciation:' . $date->toDateString();
        if ($entry = $this->ledger->findBySource($asset, $schema)) {
            return $entry;
        }
        $start = $asset->in_service_on;
        $end = $start->copy()->addMonthsNoOverflow($asset->useful_months);
        $days = max(1, (int) $start->diffInDays($end));
        $elapsed = min($days, (int) $start->diffInDays($date->copy()->addDay()->startOfDay()));
        $base = $asset->acquisitionLine->debit - $asset->residual_amount;
        $expected = (int) round($base * ($elapsed / $days));
        $booked = $this->bookedDepreciation($asset, today());
        if ($expected <= $booked) {
            return null;
        }
        $value = $expected - $booked;

        return $this->ledger->post(Journal::MISC, $date, 'Dotation — ' . $asset->label, [
            ['account' => $asset->charge_account, 'debit' => $value],
            ['account' => $asset->depreciation_account, 'credit' => $value],
        ], source: $asset, schema: $schema, reference: $asset->reference);
    }

    public function bookedDepreciation(FixedAsset $asset, Carbon $date): int
    {
        $posted = $asset->entries()->where('schema', 'like', 'fixed_asset_depreciation:%')->select('id');

        return (int) JournalEntryLine::query()->where('account_code', $asset->depreciation_account)
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at')->whereDate('entry_date', '<=', $date)
                ->where(fn ($q) => $q->whereIn('id', clone $posted)->orWhereIn('reverses_id', clone $posted)))
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) AS total')->value('total');
    }

    public function dispose(FixedAsset $asset, Carbon $date, string $reference, int $proceeds, int $vat, bool $ordinary): JournalEntry
    {
        if ($proceeds < 0 || $vat < 0 || $vat > $proceeds || $date->toDateString() > today()->toDateString()) {
            throw new RuntimeException('Vérifiez le prix de cession, la TVA et la date de sortie.');
        }

        return DB::transaction(function () use ($asset, $date, $reference, $proceeds, $vat, $ordinary) {
            $asset = FixedAsset::query()->with('acquisitionLine.entry')->lockForUpdate()->findOrFail($asset->id);
            if ($asset->disposed_on || $asset->acquisitionLine->entry->isReversed()
                || $date->isBefore($asset->acquisitionLine->entry->entry_date)
                || $this->bookedDepreciation($asset, today()) !== $this->bookedDepreciation($asset, $date)) {
                throw new RuntimeException('La sortie doit suivre l’acquisition et les dotations déjà comptabilisées.');
            }
            $this->postDepreciation($asset, $date);
            $amortization = $this->bookedDepreciation($asset, $date);
            $gross = $asset->acquisitionLine->debit;

            $entry = $this->ledger->post(Journal::MISC, $date, 'Sortie d’immobilisation — ' . $asset->label, [
                ['account' => $asset->depreciation_account, 'debit' => $amortization],
                ['account' => $ordinary ? '654000' : '810000', 'debit' => $gross - $amortization],
                ['account' => $asset->acquisitionLine->account_code, 'credit' => $gross],
                ['account' => Account::BANK, 'debit' => $proceeds],
                ['account' => $ordinary ? '754000' : '820000', 'credit' => $proceeds - $vat],
                ['account' => Account::VAT_COLLECTED, 'credit' => $vat],
            ], source: $asset, schema: 'fixed_asset_disposal', reference: $reference);
            $asset->update(['disposed_on' => $date->toDateString(), 'disposal_entry_id' => $entry->id]);

            return $entry;
        });
    }
}
