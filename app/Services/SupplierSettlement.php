<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SupplierSettlement
{
    public const METHODS = [
        'cash' => 'Espèces', 'bank_transfer' => 'Virement', 'check' => 'Chèque', 'mobile_money' => 'Mobile Money',
    ];

    public const SETTLEMENT_METHODS = self::METHODS + ['advance' => 'Imputer une avance déjà versée'];

    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function balance(SupplierInvoice $invoice, Carbon $date): int
    {
        $ids = JournalEntry::query()->select('id')->where('source_type', SupplierInvoice::class)
            ->where('source_id', $invoice->id);

        return (int) JournalEntryLine::query()->where('account_code', Account::SUPPLIERS)
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at')->whereDate('entry_date', '<=', $date)
                ->where(fn ($q) => $q->whereIn('id', clone $ids)->orWhereIn('reverses_id', clone $ids)))
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) AS balance')->value('balance');
    }

    public function pay(SupplierInvoice $invoice, Carbon $date, int $amount, string $method, string $reference): JournalEntry
    {
        if (!isset(self::SETTLEMENT_METHODS[$method]) || $amount <= 0 || trim($reference) === '' || mb_strlen($reference) > 60) {
            throw new RuntimeException('Renseignez le mode, le montant et la référence de la pièce de règlement.');
        }
        if ($date->isBefore($invoice->invoice_date) || $date->toDateString() > today()->toDateString()) {
            throw new RuntimeException('La date doit être comprise entre la date de facture et aujourd’hui.');
        }

        return DB::transaction(function () use ($invoice, $date, $amount, $method, $reference) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($invoice->supplier_id);
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $schema = 'supplier_settlement:' . substr(hash('sha256', $reference), 0, 32);
            $account = $method === 'advance' ? Account::SUPPLIER_ADVANCES : $this->treasury($method);
            if ($existing = $this->ledger->findBySource($invoice, $schema)) {
                if ($existing->entry_date->isSameDay($date) && $existing->totalDebit() === $amount
                    && $existing->lines->firstWhere('credit', '>', 0)?->account_code === $account) {
                    return $existing;
                }
                throw new RuntimeException('Cette référence de règlement est déjà associée à une autre opération.');
            }
            if ($amount > min($this->balance($invoice, $date), $this->balance($invoice, today()))) {
                throw new RuntimeException('Le règlement dépasse le solde dû sur cette facture à la date retenue.');
            }
            $advance = $method === 'advance';
            if ($advance && $amount > min($this->availableAdvance($supplier, $date), $this->availableAdvance($supplier, today()))) {
                throw new RuntimeException('Le fournisseur ne dispose pas d’une avance suffisante à la date retenue.');
            }

            return $this->ledger->post(
                $advance ? Journal::MISC : ($method === 'cash' ? Journal::CASH : Journal::BANK),
                $date,
                'Règlement fournisseur — ' . $invoice->number,
                [
                    ['account' => Account::SUPPLIERS, 'debit' => $amount, 'auxiliary' => $supplier],
                    ['account' => $account, 'credit' => $amount, 'label' => self::SETTLEMENT_METHODS[$method],
                        'auxiliary' => $advance ? $supplier : null],
                ],
                source: $invoice, schema: $schema, reference: $reference,
            );
        });
    }

    public function availableAdvance(Supplier $supplier, Carbon $date): int
    {
        return (int) JournalEntryLine::query()->forAccount(Account::SUPPLIER_ADVANCES)
            ->forAuxiliary(Supplier::class, $supplier->id)
            ->whereHas('entry', fn ($q) => $q->whereNotNull('posted_at')->whereDate('entry_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) AS balance')->value('balance');
    }

    public function advance(Supplier $supplier, Carbon $date, int $amount, string $method, string $reference): JournalEntry
    {
        if (!isset(self::METHODS[$method]) || $amount <= 0 || trim($reference) === '' || mb_strlen($reference) > 60
            || $date->toDateString() > today()->toDateString()) {
            throw new RuntimeException('Vérifiez la date, le mode, le montant et la référence de l’avance versée.');
        }

        return DB::transaction(function () use ($supplier, $date, $amount, $method, $reference) {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $schema = 'supplier_advance:' . substr(hash('sha256', $reference), 0, 32);
            $treasury = $this->treasury($method);
            if ($entry = $this->ledger->findBySource($supplier, $schema)) {
                if ($entry->entry_date->isSameDay($date) && $entry->totalDebit() === $amount
                    && $entry->lines->firstWhere('credit', '>', 0)?->account_code === $treasury) {
                    return $entry;
                }
                throw new RuntimeException('Cette référence correspond déjà à une autre avance.');
            }

            return $this->ledger->post($method === 'cash' ? Journal::CASH : Journal::BANK, $date,
                'Avance fournisseur — ' . $supplier->name, [
                    ['account' => Account::SUPPLIER_ADVANCES, 'debit' => $amount, 'auxiliary' => $supplier],
                    ['account' => $treasury, 'credit' => $amount, 'label' => self::METHODS[$method]],
                ], source: $supplier, schema: $schema, reference: $reference);
        });
    }

    private function treasury(string $method): string
    {
        return match ($method) {
            'cash' => Account::CASH,
            'mobile_money' => '531000',
            default => Account::BANK,
        };
    }
}
