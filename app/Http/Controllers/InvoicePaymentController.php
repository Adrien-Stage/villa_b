<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashRegisterSession;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement d'une facture complémentaire : le client parti avant le relevé
 * du mini-bar règle plus tard ce qu'il y a consommé, à la caisse de la
 * réception.
 */
class InvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        if (!$invoice->isComplementaire()) {
            return back()->withErrors(['payment' => 'La facture du séjour se règle depuis le séjour, pendant le séjour.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:cash,stripe,orange_money,mtn_momo,bank_transfer'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Indiquez le montant encaissé.',
        ]);

        $session = CashRegisterSession::query()
            ->where('user_id', Auth::id())
            ->where('module', 'reception')
            ->whereNull('closed_at')
            ->first();

        if ($session === null) {
            return back()->withErrors(['payment' => 'Ouvrez la caisse de la réception pour encaisser.']);
        }

        $montant = (int) $data['amount'] * 100;

        try {
            $paiement = DB::transaction(function () use ($invoice, $data, $montant, $session) {
                $facture = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if ($facture->balance_due <= 0) {
                    throw new \RuntimeException("La facture {$facture->invoice_number} est déjà soldée.");
                }
                if ($montant > $facture->balance_due) {
                    throw new \RuntimeException('Le montant dépasse le solde dû de '
                        . number_format($facture->balance_due / 100, 0, ',', ' ') . ' FCFA.');
                }

                $paiement = Payment::create([
                    'booking_id'               => $facture->booking_id,
                    'customer_id'              => $facture->customer_id,
                    'invoice_id'               => $facture->id,
                    'amount'                   => $montant,
                    'currency'                 => 'XAF',
                    'method'                   => $data['method'],
                    'status'                   => 'completed',
                    'reference'                => $this->prochaineReference(),
                    'paid_at'                  => now(),
                    'processed_by'             => Auth::id(),
                    'notes'                    => trim("Facture complémentaire {$facture->invoice_number}. " . ($data['notes'] ?? '')),
                    'cash_register_session_id' => $session->id,
                ]);

                $solde = $facture->balance_due - $montant;
                $facture->update([
                    'paid_amount' => $facture->paid_amount + $montant,
                    'balance_due' => $solde,
                    'status'      => $solde === 0 ? 'paid' : 'sent',
                ]);

                return $paiement;
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        AuditLog::record(Auth::id(), 'sensitive_action',
            'Encaissement de ' . number_format($montant / 100, 0, ',', ' ') . " FCFA sur la facture complémentaire {$invoice->invoice_number}",
            'bookings', ['invoice_id' => $invoice->id, 'reference' => $paiement->reference]);

        $invoice->refresh();

        return back()->with('success', "Encaissement {$paiement->reference} enregistré. Solde de la facture : "
            . number_format($invoice->balance_due / 100, 0, ',', ' ') . ' FCFA.');
    }

    /** PAY-AAAA-NNNNNN, à la suite des encaissements de l'année. */
    private function prochaineReference(): string
    {
        $max = 0;
        Payment::withoutGlobalScopes()
            ->where('reference', 'like', 'PAY-' . now()->year . '-%')
            ->pluck('reference')
            ->each(function (string $reference) use (&$max) {
                $fin = substr($reference, strrpos($reference, '-') + 1);
                if (ctype_digit($fin)) {
                    $max = max($max, (int) $fin);
                }
            });

        return sprintf('PAY-%d-%06d', now()->year, $max + 1);
    }
}
