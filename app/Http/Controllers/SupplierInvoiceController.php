<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use App\Services\TaxationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Factures fournisseurs et retenues à la source.
 *
 * La saisie part du TTC porté par le document reçu ; la décomposition, la
 * retenue et le net à payer sont calculés, jamais ressaisis.
 */
class SupplierInvoiceController extends Controller
{
    public function __construct(
        private readonly SupplierInvoiceService $invoices,
        private readonly TaxationService $taxation,
    ) {
    }

    public function index(Request $request): View
    {
        $factures = SupplierInvoice::query()
            ->with('supplier', 'purchaseOrder')
            ->when($request->filled('fournisseur'), fn ($q) => $q->where('supplier_id', (int) $request->input('fournisseur')))
            ->when($request->boolean('retenues'), fn ($q) => $q->withheld())
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $fournisseurs = Supplier::active()->orderBy('name')->get();

        $totaux = [
            'ttc'      => (int) SupplierInvoice::sum('amount_ttc'),
            'retenues' => (int) SupplierInvoice::sum('withholding_amount'),
        ];

        return view('accounting.ledger.supplier-invoices', compact('factures', 'fournisseurs', 'totaux'));
    }

    public function create(Request $request): View
    {
        $fournisseurs = Supplier::active()->orderBy('name')->get();
        $bons         = $this->invoices->invoiceableOrders();

        $bon = $request->filled('bon')
            ? PurchaseOrder::with('supplier')->find((int) $request->input('bon'))
            : null;

        $taux     = $this->taxation->withholdingRates();
        $confirme = $this->taxation->withholdingRatesConfirmed();
        $tvaBp    = $this->taxation->vatEnabled() ? ($this->taxation->defaultRate()?->rate_basis_points ?? 0) : 0;

        return view('accounting.ledger.supplier-invoice-form', compact(
            'fournisseurs', 'bons', 'bon', 'taux', 'confirme', 'tvaBp'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'number'            => ['required', 'string', 'max:60'],
            'invoice_date'      => ['required', 'date'],
            'due_date'          => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'charge_account'    => ['required', Rule::in(array_keys(SupplierInvoice::CHARGE_ACCOUNTS))],
            'label'             => ['required', 'string', 'max:255'],
            'amount_ttc'        => ['required', 'numeric', 'min:1'],
            'withholding_type'  => ['nullable', Rule::in(array_keys(SupplierInvoice::WITHHOLDING_TYPES))],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ], [
            'number.required' => 'La référence portée par la facture du fournisseur est obligatoire.',
        ]);

        // Une référence déjà connue pour ce fournisseur est presque toujours
        // une double saisie : on l'arrête avant de doubler la charge.
        $doublon = SupplierInvoice::where('supplier_id', $validated['supplier_id'])
            ->where('number', $validated['number'])
            ->exists();

        if ($doublon) {
            return back()
                ->with('error', "La facture {$validated['number']} est déjà enregistrée pour ce fournisseur.")
                ->withInput();
        }

        try {
            $facture = $this->invoices->record([
                'supplier'          => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'number'            => $validated['number'],
                'invoice_date'      => Carbon::parse($validated['invoice_date']),
                'due_date'          => isset($validated['due_date']) ? Carbon::parse($validated['due_date']) : null,
                'charge_account'    => $validated['charge_account'],
                'label'             => $validated['label'],
                // Saisie en francs, stockage en centimes.
                'amount_ttc'        => (int) round((float) $validated['amount_ttc'] * 100),
                'withholding_type'  => $validated['withholding_type'] ?? null,
                'notes'             => $validated['notes'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        $message = "Facture {$facture->number} enregistrée et comptabilisée.";

        if ($facture->hasWithholding()) {
            $retenue = number_format($facture->withholding_amount / 100, 0, ',', ' ');
            $net     = number_format($facture->net_payable / 100, 0, ',', ' ');
            $message .= " Retenue de {$retenue} FCFA — net à payer au fournisseur : {$net} FCFA.";
        }

        return redirect()->route('accounting.ledger.suppliers')->with('success', $message);
    }

    public function show(SupplierInvoice $invoice): View
    {
        $invoice->load('supplier', 'purchaseOrder', 'createdBy');

        // L'écriture produite, pour aller du document au grand livre.
        $ecriture = \App\Models\JournalEntry::query()
            ->where('source_type', SupplierInvoice::class)
            ->where('source_id', $invoice->id)
            ->first();

        return view('accounting.ledger.supplier-invoice', compact('invoice', 'ecriture'));
    }

    /** État des retenues à la source, pour la déclaration. */
    public function withholdingStatement(Request $request): View
    {
        $defaut = $this->invoices->defaultPeriod();

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $defaut['from'];
        $to   = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : $defaut['to'];

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $etat     = $this->invoices->withholdingStatement($from, $to);
        $confirme = $this->taxation->withholdingRatesConfirmed();

        return view('accounting.ledger.withholding', compact('etat', 'from', 'to', 'confirme'));
    }
}
