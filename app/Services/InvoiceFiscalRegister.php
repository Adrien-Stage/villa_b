<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceFiscalEvent;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceFiscalRegister
{
    public function prepare(Invoice $invoice): InvoiceFiscalEvent
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);
            $last = $this->lastEvent($invoice);
            if ($last?->status === 'prepared') {
                return $last;
            }
            if ($last?->status === 'accepted' || !in_array($invoice->status, ['sent', 'paid', 'overdue'], true)) {
                throw new RuntimeException('La préparation requiert une facture émise, sans accusé d’acceptation consigné.');
            }
            if ($invoice->items->isEmpty() || (int) $invoice->items->sum('total_price') !== $invoice->total_amount) {
                throw new RuntimeException('Le détail des lignes doit correspondre au total de la facture.');
            }
            $tenant = Tenant::current();
            $niu = trim((string) data_get($tenant?->settings, 'taxes.issuer_niu'));
            if ($niu === '') {
                throw new RuntimeException('Renseignez le NIU de l’établissement dans les paramètres fiscaux.');
            }

            $payload = json_encode([
                'format' => 'wetchah-facture-v1',
                'issuer' => ['name' => $tenant?->name, 'niu' => $niu],
                'number' => $invoice->invoice_number, 'date' => $invoice->invoice_date->toDateString(),
                'currency' => 'XAF', 'unit' => 'centimes',
                'person_id' => $invoice->customer_id, 'total' => $invoice->total_amount,
                'vat' => $invoice->tax_amount, 'tourist_tax' => $invoice->tourist_tax_amount,
                'lines' => $invoice->items->sortBy('id')->values()->map(fn ($line) => $line->only([
                    'description', 'quantity', 'unit_price', 'total_price', 'tax_rate', 'tax_rate_code',
                    'tax_amount', 'tax_surtax_amount', 'vat_reason', 'category',
                ]))->all(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            return $this->record($invoice, 'prepared', $payload, ['issuer_niu' => $niu]);
        });
    }

    public function recordResponse(Invoice $invoice, string $status, string $reference, UploadedFile $proof, ?string $notes): InvoiceFiscalEvent
    {
        if (!in_array($status, ['accepted', 'rejected'], true) || trim($reference) === ''
            || ($status === 'rejected' && blank($notes))) {
            throw new RuntimeException('Renseignez la référence de l’accusé et le motif en cas de rejet.');
        }
        $contents = $proof->get();
        if (!$contents) {
            throw new RuntimeException('L’accusé de réception est illisible.');
        }

        return DB::transaction(function () use ($invoice, $status, $reference, $contents, $notes) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $last = $this->lastEvent($invoice);
            if ($last?->status !== 'prepared') {
                if ($last?->status === $status && $last->authority_reference === $reference
                    && hash_equals($last->digest, hash('sha256', $contents))) {
                    return $last;
                }
                throw new RuntimeException('Préparez une version de la facture avant d’y joindre un accusé.');
            }

            return $this->record($invoice, $status, $contents, [
                'authority_reference' => $reference,
                'observations' => $notes,
            ]);
        });
    }

    public function read(InvoiceFiscalEvent $event): string
    {
        $data = Crypt::decryptString($event->evidence);
        if (!hash_equals($event->digest, hash('sha256', $data))) {
            throw new RuntimeException('Empreinte de pièce fiscale non concordante.');
        }

        return $data;
    }

    private function lastEvent(Invoice $invoice): ?InvoiceFiscalEvent
    {
        return InvoiceFiscalEvent::query()->where('invoice_id', $invoice->id)->latest('id')->first();
    }

    private function record(Invoice $invoice, string $status, string $contents, array $extra): InvoiceFiscalEvent
    {
        return InvoiceFiscalEvent::create($extra + [
            'invoice_id' => $invoice->id, 'status' => $status,
            'digest' => hash('sha256', $contents), 'evidence' => Crypt::encryptString($contents), 'recorded_by' => Auth::id(),
        ]);
    }
}
