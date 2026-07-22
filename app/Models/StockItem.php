<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Article détenu au magasin central. Le stock courant est le résultat des
 * mouvements ; il est maintenu en colonne pour éviter de rejouer le journal à
 * chaque affichage, mais reste reconstituable à partir de stock_movements.
 *
 * Les montants sont en centimes FCFA.
 */
class StockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_category_id', 'name', 'reference', 'unit', 'description',
        'current_stock', 'min_stock', 'average_cost', 'last_purchase_price',
        'supplier_id', 'is_active', 'tenant_id',
    ];

    protected $casts = [
        'current_stock'       => 'decimal:3',
        'min_stock'           => 'decimal:3',
        'average_cost'        => 'integer',
        'last_purchase_price' => 'integer',
        'is_active'           => 'boolean',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'stock_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ── Portées ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Articles à réapprovisionner : stock au niveau du seuil ou en dessous. */
    public function scopeBelowThreshold(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0);
    }

    // ── État ─────────────────────────────────────────────────────────────────

    public function isOutOfStock(): bool
    {
        return (float) $this->current_stock <= 0;
    }

    public function isBelowThreshold(): bool
    {
        return (float) $this->min_stock > 0
            && (float) $this->current_stock <= (float) $this->min_stock;
    }

    /**
     * Niveau d'alerte : 'out' (rupture), 'low' (sous le seuil), 'ok'.
     * Sert à colorer les listes sans dupliquer la logique dans les vues.
     */
    public function stockLevel(): string
    {
        if ($this->isOutOfStock()) {
            return 'out';
        }

        return $this->isBelowThreshold() ? 'low' : 'ok';
    }

    /** Valeur du stock détenu, au coût moyen pondéré. */
    public function stockValue(): int
    {
        return (int) round((float) $this->current_stock * $this->average_cost);
    }

    /** Quantité réellement servable pour une demande. */
    public function availableFor(float $requested): float
    {
        return min($requested, max(0, (float) $this->current_stock));
    }
}
