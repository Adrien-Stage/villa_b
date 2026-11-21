<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceFiscalEvent;
use App\Services\InvoiceFiscalRegister;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class LedgerFiscalController extends Controller
{
    public function index(Request $request)
    {
        $number = $request->input('number');
        $invoice = is_string($number) && $number !== '' ? Invoice::query()->where('invoice_number', $number)->first() : null;
        $events = $invoice ? InvoiceFiscalEvent::query()->where('invoice_id', $invoice->id)->latest('id')
            ->get(['id', 'status', 'issuer_niu', 'authority_reference', 'observations', 'created_at']) : collect();

        return response()->view('accounting.ledger.fiscal', compact('number', 'invoice', 'events'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, InvoiceFiscalRegister $register)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'status' => ['required', Rule::in(['prepared', 'accepted', 'rejected'])],
            'authority_reference' => ['required_unless:status,prepared', 'nullable', 'string', 'max:120'],
            'proof' => ['required_unless:status,prepared', 'nullable', 'file', 'mimes:pdf,xml,txt,json', 'max:4096'],
            'notes' => ['required_if:status,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $invoice = Invoice::findOrFail($data['invoice_id']);
        try {
            if ($data['status'] === 'prepared') {
                $register->prepare($invoice);
            } else {
                $register->recordResponse($invoice, $data['status'], $data['authority_reference'],
                    $data['proof'], $data['notes'] ?? null);
            }
        } catch (RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('accounting.ledger.fiscal.index', ['number' => $invoice->invoice_number])
            ->with('success', 'Le suivi de la facture a été mis à jour.');
    }

    public function export(InvoiceFiscalEvent $event, InvoiceFiscalRegister $register)
    {
        $name = 'piece-fiscale-' . $event->id . ($event->status === 'prepared' ? '.json' : '.bin');

        return response($register->read($event))
            ->header('Content-Type', 'application/octet-stream')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '"');
    }
}
