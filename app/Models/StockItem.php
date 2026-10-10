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

    /** Conditionnements de l'article, du plus petit au plus grand. */
    public function packagings(): HasMany
    {
        return $this->hasMany(StockItemPackaging::class)->orderBy('factor');
    }

    public function pantryItems(): HasMany
    {
        return $this->hasMany(RestaurantPantryItem::class);
    }

    public function pantryItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RestaurantPantryItem::class);
    }

    public function stockCountLines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function purchaseRequestLines(): HasMany
    {
        return $this->hasMany(PurchaseRequestLine::class);
    }

    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
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

    /**
     * Compte de classe 3 qui valorise l'article. La catégorie le décide ;
     * sans catégorie ni compte, l'article reste une fourniture d'économat.
     */
    public function stockAccount(): string
    {
        return $this->category?->effectiveStockAccount() ?? Account::STOCK_STORE;
    }

    /** Quantité réellement servable pour une demande. */
    public function availableFor(float $requested): float
    {
        return min($requested, max(0, (float) $this->current_stock));
    }

    // ── Conditionnements ─────────────────────────────────────────────────────

    public function aDesConditionnements(): bool
    {
        return $this->relationLoaded('packagings') ? $this->packagings->isNotEmpty() : $this->packagings()->exists();
    }

    /** Unités de l'article contenues dans un conditionnement ; 1 pour l'unité de l'article. */
    public function facteurDe(?string $conditionnement): float
    {
        if ($conditionnement === null || $conditionnement === '' || $conditionnement === $this->unit) {
            return 1.0;
        }

        $niveau = $this->packagings->firstWhere('name', $conditionnement);
        if ($niveau === null) {
            throw new \InvalidArgumentException("« {$this->name} » n'a pas de conditionnement « {$conditionnement} ».");
        }

        return (float) $niveau->factor;
    }

    /** Stock décomposé en unités fermées : « 4 cartons · 19 paquets · 5 pièces ». Null sans conditionnement. */
    public function stockDecompose(): ?string
    {
        $etat = app(\App\Services\Conditionnements::class)->etatDe($this);

        return $etat === null ? null : \App\Support\Conditionnement::decomposition($etat, (string) $this->unit);
    }

    /**
     * Les unités dans lesquelles on peut demander ou sortir cet article : la
     * sienne, puis ses conditionnements. Pour les formulaires.
     *
     * @return list<array{nom: string, facteur: float, fermes: int|null}>
     */
    public function unitesDeSaisie(): array
    {
        $unites = [['nom' => (string) $this->unit, 'facteur' => 1.0, 'fermes' => null]];
        foreach ($this->packagings as $niveau) {
            $unites[] = ['nom' => $niveau->name, 'facteur' => (float) $niveau->factor, 'fermes' => (int) $niveau->closed_count];
        }

        return $unites;
    }
}
