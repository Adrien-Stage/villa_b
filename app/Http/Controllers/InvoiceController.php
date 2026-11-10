<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\CashRegisterCircuit;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'booking.room.roomType',
            'customer',
            'items',
            'payments',
        ]);

        // Une base ne contient qu'un établissement. La relation par
        // `bookings.tenant_id` ne résout rien — cette colonne, héritée d'un
        // modèle à base partagée, n'est jamais renseignée — et l'en-tête de
        // facture partait alors en erreur sur un tenant nul.
        $tenant = $invoice->booking?->tenant ?? Tenant::current();
        $cashRegisterContext = Auth::check()
            ? app(CashRegisterCircuit::class)->receptionContextePour(Auth::user())
            : null;

        return view('invoices.show', compact('invoice', 'tenant', 'cashRegisterContext'));
    }
}
