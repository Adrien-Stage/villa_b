<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    use HasFactory;

    public const REASON_WASTE       = 'waste';
    public const REASON_SPOILAGE    = 'spoilage';
    public const REASON_PACKAGING   = 'packaging';
    public const REASON_INPUT_ERROR = 'input_error';
    public const REASON_THEFT       = 'theft';
    public const REASON_OTHER       = 'other';

    public const REASONS = [
        self::REASON_WASTE       => 'Casse / perte',
        self::REASON_SPOILAGE    => 'Périmé / avarié',
        self::REASON_PACKAGING   => 'Écart de conditionnement',
        self::REASON_INPUT_ERROR => 'Erreur de saisie antérieure',
        self::REASON_THEFT       => 'Inexpliqué / coulage suspecté',
        self::REASON_OTHER       => 'Autre motif',
    ];

    protected $fillable = [
        'stock_count_id',
        'stock_item_id',
        'theoretical_quantity',
        'counted_quantity',
        'packaging_counts',
        'variance_quantity',
        'unit_cost',
        'theoretical_value',
        'counted_value',
        'variance_value',
        'reason',
        'notes',
    ];

    protected $casts = [
        'theoretical_quantity' => 'decimal:3',
        'counted_quantity'     => 'decimal:3',
        'variance_quantity'    => 'decimal:3',
        'unit_cost'            => 'integer',
        'theoretical_value'    => 'integer',
        'counted_value'        => 'integer',
        'variance_value'       => 'integer',
        'packaging_counts'     => 'array',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->item();
    }

    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    public function hasVariance(): bool
    {
        return $this->isCounted() && abs((float) $this->variance_quantity) >= 0.0005;
    }

    public function isDeficit(): bool
    {
        return $this->isCounted() && (float) $this->variance_quantity < -0.0005;
    }

    public function isSurplus(): bool
    {
        return $this->isCounted() && (float) $this->variance_quantity > 0.0005;
    }

    public function reasonLabel(): string
    {
        if (empty($this->reason)) {
            return '—';
        }

        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
