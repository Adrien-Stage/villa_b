<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PartnerOrganization;
use App\Models\Payment;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WalletEntryAttachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Adaptateur hôtel : accord du partenaire, règlement et preuve comptable atomiques. */
class PartnerWalletService
{
    public const METHODS = ['bank_transfer' => 'Virement bancaire', 'check' => 'Chèque',
        'cash' => 'Espèces', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo', 'stripe' => 'Carte bancaire'];

    public function __construct(private readonly WalletService $wallets, private readonly CheckOutService $checkout) {}

    public function topup(PartnerOrganization $partner, array $data, User $actor, ?UploadedFile $proof = null): Payment
    {
        $this->authorize($actor, 'partners.wallet.credit');
        $this->amount($data);
        if (! isset(self::METHODS[$data['method'] ?? '']) || trim($data['reference'] ?? '') === '') {
            throw new \DomainException('Mode et référence du versement requis.');
        }

        return $this->withProof(function () use ($partner, $data, $actor, $proof) {
            $partner = PartnerOrganization::query()->lockForUpdate()->findOrFail($partner->id);
            if (! $partner->is_active) {
                throw new \DomainException('Ce partenaire est désactivé.');
            }
            $wallet = $this->wallets->forOwner($partner);
            $entry = $this->wallets->credit($wallet, $data['amount'] * 100, $data['request_key'], [
                'context' => 'partner_deposit', 'method' => $data['method'],
                'reference' => trim($data['reference']), 'notes' => $data['notes'] ?? null,
                'proof_hash' => $proof ? hash_file('sha256', $proof->getRealPath()) : null,
            ], $actor->id);
            if (! $entry->wasRecentlyCreated) {
                return Payment::where('wallet_entry_id', $entry->id)->sole();
            }

            return $this->record($partner, $entry, [
                'amount' => $data['amount'] * 100, 'method' => $data['method'],
                'purpose' => Payment::PURPOSE_PARTNER_DEPOSIT,
                'payment_origin' => Payment::ORIGIN_DEPOT_PARTENAIRE,
                'external_reference' => trim($data['reference']),
                'notes' => $data['notes'] ?? null,
            ], $actor, $proof);
        });
    }

    public function settle(PartnerOrganization $partner, Booking $booking, ?Invoice $invoice, array $data, User $actor, ?UploadedFile $proof = null): Payment
    {
        $this->authorize($actor, 'partners.wallet.debit');
        $this->amount($data);

        return $this->withProof(function () use ($partner, $booking, $invoice, $data, $actor, $proof) {
            $partner = PartnerOrganization::query()->lockForUpdate()->findOrFail($partner->id);
            if (! $partner->is_active) {
                throw new \DomainException('Ce partenaire est désactivé.');
            }
            if (trim($data['authorized_by'] ?? '') === '' || trim($data['authorization_reference'] ?? '') === '') {
                throw new \DomainException('Le nom de l’autorisateur et la référence de son accord sont obligatoires.');
            }
            $wallet = $this->wallets->forOwner($partner);
            $entry = $this->wallets->debit($wallet, $data['amount'] * 100, $data['request_key'], [
                'context' => 'partner_settlement', 'booking_id' => $booking->id, 'invoice_id' => $invoice?->id,
                'authorized_by' => trim($data['authorized_by']), 'authorization_reference' => trim($data['authorization_reference']),
                'notes' => $data['notes'] ?? null, 'proof_hash' => $proof ? hash_file('sha256', $proof->getRealPath()) : null,
            ], $actor->id);
            if (! $entry->wasRecentlyCreated) {
                return Payment::where('wallet_entry_id', $entry->id)->sole();
            }

            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $invoice = $invoice ? Invoice::query()->lockForUpdate()->findOrFail($invoice->id) : null;
            $customer = Customer::query()->lockForUpdate()->findOrFail($invoice?->customer_id ?? $booking->customer_id);
            if ($customer->partner_organization_id !== $partner->id || ($invoice && $invoice->booking_id !== $booking->id)) {
                throw new \DomainException('Ce client n’appartient pas à ce partenaire.');
            }
            if ($invoice && in_array($invoice->status, ['cancelled', 'draft'], true)) {
                throw new \DomainException('Cette facture ne peut pas être réglée.');
            }
            if (! $invoice && ($booking->status !== BookingStatus::CHECKED_IN || Invoice::where('booking_id', $booking->id)->where('kind', Invoice::KIND_SEJOUR)->exists())) {
                throw new \DomainException('Utilisez la facture émise, ou un séjour en cours.');
            }
            $due = $invoice?->balance_due ?? $booking->effectiveBalanceDue();
            if ($due < $data['amount'] * 100 || $due <= 0) {
                throw new \DomainException('Le montant dépasse le solde restant à régler.');
            }
            $payment = $this->record($partner, $entry, [
                'booking_id' => $booking->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice?->id,
                'payment_origin' => $invoice?->isComplementaire() ? Payment::ORIGIN_FACTURE_COMPLEMENTAIRE : Payment::ORIGIN_SEJOUR_NUITEES,
                'amount' => $data['amount'] * 100, 'method' => Payment::METHOD_PARTNER_WALLET,
                'notes' => $data['notes'] ?? null,
            ], $actor, $proof);
            $this->updateTarget($booking, $invoice, $payment->amount);

            return $payment;
        });
    }

    public function reverse(PartnerOrganization $partner, WalletEntry $original, array $data, User $actor): Payment
    {
        $this->authorize($actor, 'partners.wallet.reverse');

        return DB::transaction(function () use ($partner, $original, $data, $actor) {
            $partner = PartnerOrganization::query()->lockForUpdate()->findOrFail($partner->id);
            $wallet = $this->wallets->forOwner($partner);
            if ($original->wallet_id !== $wallet->id) {
                throw new \DomainException('Ce mouvement n’appartient pas à ce partenaire.');
            }
            $source = Payment::where('wallet_entry_id', $original->id)->sole();
            $entry = $this->wallets->reverse($original, $data['request_key'], [
                'context' => 'partner_reversal', 'reason' => trim($data['reason']),
            ], $actor->id);
            if (! $entry->wasRecentlyCreated) {
                return Payment::where('wallet_entry_id', $entry->id)->sole();
            }
            $booking = $source->booking_id ? Booking::query()->lockForUpdate()->findOrFail($source->booking_id) : null;
            // Un règlement du séjour peut précéder la facture créée au départ.
            $invoice = $source->invoice_id
                ? Invoice::query()->lockForUpdate()->findOrFail($source->invoice_id)
                : ($booking ? Invoice::where('booking_id', $booking->id)->where('kind', Invoice::KIND_SEJOUR)->lockForUpdate()->first() : null);
            $payment = $this->record($partner, $entry, [
                'booking_id' => $source->booking_id, 'customer_id' => $source->customer_id,
                'invoice_id' => $invoice?->id, 'amount' => -$source->amount,
                'purpose' => $source->purpose, 'method' => $source->method,
                'notes' => 'Annulation de '.$source->reference.' : '.trim($data['reason']),
            ], $actor);
            if ($booking) {
                $this->updateTarget($booking, $invoice, -$source->amount);
            }

            return $payment;
        });
    }

    private function updateTarget(Booking $booking, ?Invoice $invoice, int $amount): void
    {
        if ($invoice) {
            $paid = $invoice->paid_amount + $amount;
            $invoice->update(['paid_amount' => $paid, 'balance_due' => $invoice->total_amount - $paid,
                'status' => $paid >= $invoice->total_amount ? 'paid' : 'sent']);
        }
        if (! $invoice || ! $invoice->isComplementaire()) {
            $this->checkout->recalculateTotals($booking);

            if (! $invoice && $amount > 0) {
                $freshBooking = $booking->fresh();
                if ($freshBooking
                    && $freshBooking->status === BookingStatus::CHECKED_IN
                    && $freshBooking->hasOverstayedCheckout()
                    && $freshBooking->getConsumedBalance() <= 0
                ) {
                    $freshBooking->update(['actual_check_out' => now()]);
                    $this->checkout->syncDurationToNow($freshBooking);
                }
            }
        }
    }

    private function record(PartnerOrganization $partner, WalletEntry $entry, array $attributes, User $actor, ?UploadedFile $proof = null): Payment
    {
        $payment = Payment::create($attributes + [
            'partner_organization_id' => $partner->id, 'wallet_entry_id' => $entry->id,
            'reference' => sprintf('DEP-%08d', $entry->id), 'currency' => 'XAF',
            'status' => 'completed', 'paid_at' => now(), 'processed_by' => $actor->id,
        ]);
        if ($proof) {
            $path = $proof->store('wallet-agreements', 'local');
            if (! $path) {
                throw new \RuntimeException('Le justificatif n’a pas pu être enregistré.');
            }
            $this->storedPath = $path;
            WalletEntryAttachment::create(['wallet_entry_id' => $entry->id, 'path' => $path,
                'original_name' => $proof->getClientOriginalName(), 'mime_type' => $proof->getMimeType()]);
        }
        AuditLog::record($actor->id, 'partner_wallet', 'Compte dépôt '.$partner->name.' : '.$payment->reference,
            'accounting', ['wallet_entry_id' => $entry->id, 'amount' => $entry->amount, 'balance_after' => $entry->balance_after]);

        return $payment;
    }

    private ?string $storedPath = null;

    private function authorize(User $actor, string $permission): void
    {
        if (! app(PermissionResolver::class)->allows($actor, $permission)) {
            throw new AuthorizationException('Opération de compte dépôt non autorisée.');
        }
    }

    private function amount(array $data): void
    {
        if (! isset($data['amount']) || filter_var($data['amount'], FILTER_VALIDATE_INT) === false
            || $data['amount'] < 1 || $data['amount'] > 1000000000000) {
            throw new \DomainException('Montant en FCFA entier, positif et inférieur à la limite autorisée requis.');
        }
    }

    private function withProof(callable $operation): Payment
    {
        $this->storedPath = null;
        try {
            return DB::transaction($operation);
        } catch (\Throwable $e) {
            if ($this->storedPath) {
                Storage::disk('local')->delete($this->storedPath);
            }
            throw $e;
        }
    }
}
