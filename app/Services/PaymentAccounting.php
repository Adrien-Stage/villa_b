<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\ReceptionSale;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentAccounting
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    public function recordExchange(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->requireXafPayment($payment);
            $converted = BigDecimal::of($data['xaf_rate'])->multipliedBy($data['original_minor'])->multipliedBy(100)
                ->dividedBy(10 ** $data['minor_digits'], 0, RoundingMode::HalfUp);
            if (!$converted->isEqualTo($payment->amount) || $data['rate_on'] > $payment->paid_at->toDateString()) {
                throw new RuntimeException('La conversion doit retrouver le montant encaissé avec un taux daté au plus tard le jour du paiement.');
            }
            $existing = DB::table('payment_exchange_evidence')->where('payment_id', $payment->id)->first();
            if ($existing) {
                foreach ($data as $name => $value) {
                    if ((string) $existing->{$name} !== (string) $value) {
                        throw new RuntimeException('Le justificatif de change a déjà été enregistré pour ce paiement.');
                    }
                }
                return;
            }
            DB::table('payment_exchange_evidence')->insert($data + [
                'payment_id' => $payment->id, 'amount_xaf_centimes' => $payment->amount,
                'recorded_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function settle(Payment $payment, Carbon $date, int $net, string $reference): JournalEntry
    {
        return DB::transaction(function () use ($payment, $date, $net, $reference) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->requireXafPayment($payment);
            if (!in_array($payment->method, ['card', 'stripe', 'mobile_money', 'mtn_momo', 'orange_money'], true)) {
                throw new RuntimeException('Sélectionnez un encaissement par carte ou Mobile Money.');
            }
            $original = $this->ledger->findBySource($payment, LedgerPostingService::SCHEMA_PAYMENT);
            if (!$original && $sale = ReceptionSale::query()->where('payment_id', $payment->id)->first()) {
                $original = $this->ledger->findBySource($sale, LedgerPostingService::SCHEMA_RECEPTION_SALE);
            }
            $treasury = $original?->lines->first(fn ($line) => in_array($line->account_code, [Account::BANK, '531000'], true)
                && $line->debit === $payment->amount);
            if (!$treasury || $original->isReversed() || !$original->isPosted() || $date->isBefore($original->entry_date)
                || $net < 0 || $net > $payment->amount) {
                throw new RuntimeException('Vérifiez le brut déjà comptabilisé, la date et le montant net versé.');
            }
            $schema = 'provider_settlement';
            if ($prior = $this->ledger->findBySource($payment, $schema)) {
                $priorNet = (int) $prior->lines->where('account_code', Account::BANK)->sum('debit');
                if ($prior->entry_date->isSameDay($date) && $prior->reference === $reference && $priorNet === $net) {
                    return $prior;
                }
                throw new RuntimeException('Ce paiement figure déjà sur un autre versement opérateur.');
            }

            return $this->ledger->post(Journal::BANK, $date, 'Versement opérateur — ' . $payment->reference, [
                ['account' => Account::BANK, 'debit' => $net, 'label' => 'Net versé'],
                ['account' => '631000', 'debit' => $payment->amount - $net, 'label' => 'Commission opérateur'],
                ['account' => $treasury->account_code, 'credit' => $payment->amount, 'label' => 'Brut rapproché'],
            ], source: $payment, schema: $schema, reference: $reference, valueDate: $original->entry_date);
        });
    }

    public function recordStamp(Payment $payment, Carbon $date, int $amount, string $reference, string $legalReference): JournalEntry
    {
        $this->requireXafPayment($payment);
        if ($amount <= 0 || $date->toDateString() < $payment->paid_at->toDateString()) {
            throw new RuntimeException('Renseignez le droit réellement encaissé et sa date de perception.');
        }
        $account = match ($payment->method) {
            'cash' => Account::CASH,
            'orange_money', 'mtn_momo', 'mobile_money' => '531000',
            'card', 'bank_transfer', 'check', 'stripe' => Account::BANK,
            default => throw new RuntimeException('Le moyen de règlement du justificatif ne correspond pas à une trésorerie identifiable.'),
        };
        if ($entry = $this->ledger->findBySource($payment, 'collected_stamp')) {
            if ($entry->entry_date->isSameDay($date) && $entry->totalDebit() === $amount && $entry->reference === $reference) {
                return $entry;
            }
            throw new RuntimeException('Un droit de timbre est déjà comptabilisé pour ce paiement.');
        }

        return $this->ledger->post($account === Account::CASH ? Journal::CASH : Journal::BANK,
            $date, 'Droit de timbre perçu — ' . $payment->reference, [
                ['account' => $account, 'debit' => $amount],
                ['account' => '447200', 'credit' => $amount, 'label' => $legalReference],
            ], source: $payment, schema: 'collected_stamp', reference: $reference);
    }

    private function requireXafPayment(Payment $payment): void
    {
        if (strtoupper($payment->currency) !== 'XAF' || $payment->status !== 'completed' || $payment->amount <= 0 || !$payment->paid_at) {
            throw new RuntimeException('Sélectionnez un encaissement confirmé et valorisé en XAF.');
        }
    }
}
