<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saisie des factures fournisseurs et suivi des retenues à la source.
 *
 * Le montant saisi est un TTC — c'est ce que porte le document reçu. Tout le
 * reste en découle : base hors taxes, TVA récupérable, retenue, net à payer.
 * L'opérateur n'a donc qu'un chiffre à recopier, et aucune décomposition à
 * refaire de tête.
 *
 * Montants en centimes FCFA.
 */
class SupplierInvoiceService
{
    public function __construct(
        private readonly TaxationService $taxation,
        private readonly LedgerPostingService $posting,
    ) {
    }

    /**
     * Enregistre une facture et la comptabilise.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): SupplierInvoice
    {
        $fournisseur = $data['supplier'] instanceof Supplier
            ? $data['supplier']
            : Supplier::findOrFail($data['supplier']);

        $ttc = (int) $data['amount_ttc'];

        if ($ttc <= 0) {
            throw new RuntimeException('Le montant de la facture doit être supérieur à zéro.');
        }

        $decomposition = $this->decompose($ttc, $data['withholding_type'] ?? null);

        return DB::transaction(function () use ($data, $fournisseur, $ttc, $decomposition) {
            $facture = SupplierInvoice::create([
                'supplier_id'              => $fournisseur->id,
                'purchase_order_id'        => $data['purchase_order_id'] ?? null,
                'number'                   => $data['number'],
                'invoice_date'             => $data['invoice_date'],
                'due_date'                 => $data['due_date'] ?? null,
                'charge_account'           => $data['charge_account'],
                'label'                    => $data['label'],
                'amount_ttc'               => $ttc,
                'amount_ht'                => $decomposition['ht'],
                'amount_vat'               => $decomposition['vat'],
                'tax_rate_code'            => $decomposition['rate_code'],
                'withholding_type'         => $decomposition['withholding']['type'],
                'withholding_basis_points' => $decomposition['withholding']['basis_points'],
                'withholding_amount'       => $decomposition['withholding']['amount'],
                'net_payable'              => $decomposition['net_payable'],
                'notes'                    => $data['notes'] ?? null,
                'created_by'               => Auth::id(),
                'tenant_id'                => $fournisseur->tenant_id,
            ]);

            // La comptabilisation est immédiate : une facture fournisseur est
            // une dette dès sa réception, pas à son règlement.
            $ecriture = $this->posting->postSupplierInvoice($facture->load('supplier'));

            if ($ecriture !== null) {
                $facture->update(['posted_at' => now()]);
            }

            return $facture->fresh(['supplier', 'purchaseOrder']);
        });
    }

    /**
     * Décompose un TTC : hors taxes, TVA, retenue, net à payer.
     *
     * Sert aussi bien à l'enregistrement qu'à l'aperçu affiché pendant la
     * saisie — un seul calcul, donc aucun écart possible entre ce que
     * l'opérateur voit et ce qui sera écrit au grand livre.
     *
     * @return array{ht: int, vat: int, rate_code: string|null, withholding: array{type: string|null, basis_points: int, amount: int}, net_payable: int}
     */
    public function decompose(int $ttc, ?string $withholdingType): array
    {
        $tva = $this->taxation->breakdown($ttc);
        $retenue = $this->taxation->withholding($tva->ht, $withholdingType);

        return [
            'ht'          => $tva->ht,
            'vat'         => $tva->vat,
            'rate_code'   => $tva->rateCode,
            'withholding' => $retenue,
            'net_payable' => $ttc - $retenue['amount'],
        ];
    }

    /**
     * Montant restant à facturer sur un bon de commande.
     *
     * Un bon peut être facturé en plusieurs fois ; l'écart avec le total
     * commandé est une information utile à la saisie, pas une règle bloquante
     * — un fournisseur facture parfois plus qu'il n'a été commandé.
     */
    public function outstandingOnOrder(PurchaseOrder $order): int
    {
        $facture = (int) SupplierInvoice::where('purchase_order_id', $order->id)->sum('amount_ttc');

        return (int) $order->total_amount - $facture;
    }

    /**
     * État des retenues à la source pour la déclaration.
     *
     * Ventilé par nature — chaque taux se déclare séparément — et détaillé par
     * fournisseur, l'administration réclamant l'identité des bénéficiaires.
     *
     * @return array{lines: Collection<int, SupplierInvoice>, byType: array<string, array{label: string, base: int, amount: int, count: int}>, total: int, base: int}
     */
    public function withholdingStatement(CarbonInterface $from, CarbonInterface $to): array
    {
        $factures = SupplierInvoice::query()
            ->withheld()
            ->inPeriod($from->toDateString(), $to->toDateString())
            ->with('supplier')
            ->orderBy('invoice_date')
            ->get();

        $parType = [];

        foreach ($factures as $facture) {
            $type = $facture->withholding_type;

            $parType[$type] ??= [
                'label'  => $facture->withholdingLabel(),
                'rate'   => $facture->withholding_basis_points,
                'base'   => 0,
                'amount' => 0,
                'count'  => 0,
            ];

            $parType[$type]['base']   += $facture->amount_ht;
            $parType[$type]['amount'] += $facture->withholding_amount;
            $parType[$type]['count']++;
        }

        return [
            'lines'  => $factures,
            'byType' => $parType,
            'base'   => (int) $factures->sum('amount_ht'),
            'total'  => (int) $factures->sum('withholding_amount'),
        ];
    }

    /** Bons de commande réceptionnés et pas encore intégralement facturés. */
    public function invoiceableOrders(): Collection
    {
        $facturables = SupplierInvoice::query()
            ->whereNotNull('purchase_order_id')
            ->select('purchase_order_id', DB::raw('SUM(amount_ttc) as factures'))
            ->groupBy('purchase_order_id')
            ->pluck('factures', 'purchase_order_id');

        return PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
            ->with('supplier')
            ->orderByDesc('received_at')
            ->get()
            ->filter(fn (PurchaseOrder $o) => (int) ($facturables[$o->id] ?? 0) < (int) $o->total_amount)
            ->values();
    }

    /** Période par défaut d'une déclaration : le mois écoulé. */
    public function defaultPeriod(): array
    {
        return [
            'from' => Carbon::now()->startOfMonth(),
            'to'   => Carbon::now()->endOfMonth(),
        ];
    }
}
