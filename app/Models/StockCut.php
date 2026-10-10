<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bon de découpe : une quantité d'un article de l'économat (30 kg de poulet)
 * répartie en portions qui entrent au garde-manger d'un restaurant. Montants
 * en centimes FCFA.
 */
class StockCut extends Model
{
    protected $fillable = [
        'number', 'stock_item_id', 'point_of_sale_id', 'quantity', 'unit_cost', 'total_value',
        'notes', 'cut_by', 'cut_at', 'tenant_id',
    ];

    protected $casts = [
        'quantity'    => 'decimal:3',
        'unit_cost'   => 'integer',
        'total_value' => 'integer',
        'cut_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockCut $decoupe) {
            $decoupe->number ??= self::generateNumber();
            $decoupe->cut_at ??= now();
        });
    }

    public static function generateNumber(): string
    {
        $prefixe = sprintf('DEC-%d-', now()->year);

        $dernier = self::query()->where('number', 'like', $prefixe . '%')->orderByDesc('number')->value('number');
        $suite = $dernier && preg_match('/-(\d+)$/', $dernier, $m) ? ((int) $m[1]) + 1 : 1;

        do {
            $numero = $prefixe . str_pad((string) $suite++, 4, '0', STR_PAD_LEFT);
        } while (self::query()->where('number', $numero)->exists());

        return $numero;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }

    public function cutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cut_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCutLine::class);
    }

    /** Quantité répartie en portions, dans l'unité de l'article. */
    public function quantiteRepartie(): float
    {
        return round((float) $this->lines->sum(fn ($l) => (float) $l->quantity), 3);
    }

    /** Ce qui manque entre la quantité prise et les portions (os, eau, parures non gardées). */
    public function freinte(): float
    {
        return round(max(0.0, (float) $this->quantity - $this->quantiteRepartie()), 3);
    }
}
