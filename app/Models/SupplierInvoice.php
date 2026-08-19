<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Facture reçue d'un fournisseur.
 *
 * Porte la dette (401000), le droit à déduction de TVA (445100) et, le cas
 * échéant, la retenue à la source que l'établissement prélève pour le compte
 * de l'État (442100).
 *
 * Montants en centimes FCFA.
 */
class SupplierInvoice extends Model
{
    /** Natures de retenue à la source. Les taux vivent dans TaxationService. */
    public const WITHHOLDING_NONE = null;
    public const WITHHOLDING_SERVICES = 'services';
    public const WITHHOLDING_FEES = 'fees';
    public const WITHHOLDING_INTELLECTUAL = 'intellectual';

    public const WITHHOLDING_TYPES = [
        self::WITHHOLDING_SERVICES     => 'Prestations de services',
        self::WITHHOLDING_FEES         => 'Honoraires',
        self::WITHHOLDING_INTELLECTUAL => 'Prestations intellectuelles',
    ];

    /** Comptes de charge proposés à la saisie, par nature d'achat. */
    public const CHARGE_ACCOUNTS = [
        '601000' => 'Achats de marchandises',
        '602000' => 'Matières premières et fournitures',
        '605000' => 'Eau, électricité, carburant',
        '611000' => 'Transports sur achats',
        '622000' => 'Locations et charges locatives',
        '624000' => 'Entretien, réparations et maintenance',
        '625000' => 'Primes d’assurance',
        '632000' => 'Honoraires et rémunérations d’intermédiaires',
        '633000' => 'Frais de formation',
        '658000' => 'Charges diverses',
    ];

    protected $fillable = [
        'supplier_id',
        'purchase_order_id',
        'number',
        'invoice_date',
        'due_date',
        'charge_account',
        'label',
        'amount_ttc',
        'amount_ht',
        'amount_vat',
        'tax_rate_code',
        'withholding_type',
        'withholding_basis_points',
        'withholding_amount',
        'net_payable',
        'posted_at',
        'notes',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'invoice_date'             => 'date',
        'due_date'                 => 'date',
        'posted_at'                => 'datetime',
        'amount_ttc'               => 'integer',
        'amount_ht'                => 'integer',
        'amount_vat'               => 'integer',
        'withholding_basis_points' => 'integer',
        'withholding_amount'       => 'integer',
        'net_payable'              => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function hasWithholding(): bool
    {
        return $this->withholding_amount > 0;
    }

    public function withholdingLabel(): string
    {
        return self::WITHHOLDING_TYPES[$this->withholding_type] ?? '—';
    }

    public function chargeLabel(): string
    {
        return self::CHARGE_ACCOUNTS[$this->charge_account] ?? $this->charge_account;
    }

    /** Taux appliqué, en pourcentage lisible. */
    public function withholdingRate(): float
    {
        return $this->withholding_basis_points / 100;
    }

    public function scopeWithheld(Builder $query): Builder
    {
        return $query->where('withholding_amount', '>', 0);
    }

    public function scopeInPeriod(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('invoice_date', [$from, $to]);
    }
}
