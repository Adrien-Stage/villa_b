<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequisitionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_requisition_id', 'stock_item_id',
        'quantity_requested', 'quantity_issued',
        'packaging_name', 'packaging_quantity',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:3',
        'quantity_issued'    => 'decimal:3',
        'packaging_quantity' => 'decimal:3',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->item();
    }

    /** Le stock couvre-t-il la quantité demandée sur cette ligne ? */
    public function isServiceable(): bool
    {
        return $this->item && (float) $this->item->current_stock >= (float) $this->quantity_requested;
    }

    /**
     * Unités de l'article dans le conditionnement demandé (10 pour un paquet
     * de 10 pièces) ; 1 quand la ligne est demandée dans l'unité de l'article.
     */
    public function facteur(): float
    {
        if ($this->packaging_name === null || (float) $this->packaging_quantity <= 0) {
            return 1.0;
        }

        return (float) $this->quantity_requested / (float) $this->packaging_quantity;
    }

    /** « 2 paquets (20 pièces) », ou « 20 pièces » sans conditionnement. */
    public function quantiteDemandee(): string
    {
        return $this->libelle((float) $this->quantity_requested);
    }

    /** La quantité servie, dans le conditionnement demandé quand elle s'y exprime. */
    public function quantiteServie(): string
    {
        return $this->libelle((float) $this->quantity_issued);
    }

    /** Quantité servie proposée par défaut, dans le conditionnement demandé. */
    public function quantiteServieProposee(): float
    {
        $servable = $this->item ? $this->item->availableFor((float) $this->quantity_requested) : 0.0;

        return round($servable / $this->facteur(), 3);
    }

    private function libelle(float $quantite): string
    {
        $unite = (string) ($this->item?->unit ?? '');
        $base = \App\Support\Conditionnement::libelle($quantite, $unite);
        if ($this->packaging_name === null) {
            return $base;
        }

        $nombre = $quantite / $this->facteur();

        return \App\Support\Conditionnement::libelle(round($nombre, 3), $this->packaging_name) . " ({$base})";
    }

    /** Coût unitaire moyen pondéré au centime près. */
    public function unitCost(): int
    {
        return (int) ($this->item?->average_cost ?? 0);
    }

    /** Montant total demandé (centimes FCFA). */
    public function totalRequestedCost(): int
    {
        return (int) round((float) $this->quantity_requested * $this->unitCost());
    }

    /** Montant total servi (centimes FCFA). */
    public function totalIssuedCost(): int
    {
        return (int) round((float) $this->quantity_issued * $this->unitCost());
    }
}
