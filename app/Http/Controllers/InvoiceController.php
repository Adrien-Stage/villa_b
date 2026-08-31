<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Tenant;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'booking.room.roomType',
            'customer',
            'items',
        ]);

        // Une base ne contient qu'un établissement. La relation par
        // `bookings.tenant_id` ne résout rien — cette colonne, héritée d'un
        // modèle à base partagée, n'est jamais renseignée — et l'en-tête de
        // facture partait alors en erreur sur un tenant nul.
        $tenant = $invoice->booking?->tenant ?? Tenant::current();

        return view('invoices.show', compact('invoice', 'tenant'));
    }
}
