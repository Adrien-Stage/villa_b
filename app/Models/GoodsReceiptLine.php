<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne de réception d'un bon de réception fournisseur.
 */
class GoodsReceiptLine extends Model
{
    use HasFactory;

    public const REASONS = [
        'damaged'       => 'Avarie / Casse au transport',
        'spoilage'      => 'Produit détérioré / Altéré',
        'short_date'    => 'Date de péremption trop proche / Dépassée',
        'non_compliant' => 'Non conforme aux spécifications / Qualité insuffisante',
        'wrong_item'    => 'Mauvaise référence livrée',
        'packaging'     => 'Emballage défectueux / Ouvert',
        'other'         => 'Autre motif de litige',
    ];

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_line_id',
        'stock_item_id',
        'quantity_ordered',
        'quantity_delivered',
        'quantity_accepted',
        'quantity_rejected',
        'rejection_reason',
        'unit_cost',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered'   => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_accepted'  => 'decimal:3',
        'quantity_rejected'  => 'decimal:3',
        'unit_cost'          => 'integer', // centimes FCFA
        'total_cost'         => 'integer', // centimes FCFA
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function rejectionReasonLabel(): ?string
    {
        return self::REASONS[$this->rejection_reason] ?? $this->rejection_reason;
    }
}
