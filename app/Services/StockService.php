<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moteur unique des mouvements de stock de l'économat.
 *
 * Toute variation passe par ce service : c'est lui qui journalise le mouvement,
 * met à jour le stock courant et recalcule le coût moyen pondéré. Le concentrer
 * ici évite que chaque contrôleur réinvente — et fasse diverger — cette logique.
 *
 * Montants en centimes FCFA, quantités en décimal.
 */
class StockService
{
    /**
     * Entrée en stock (réception fournisseur, retour, correction positive).
     *
     * Recalcule le coût moyen pondéré : nouvelle valeur = (valeur existante +
     * valeur reçue) / quantité totale. C'est la moyenne pondérée classique,
     * qui lisse les variations de prix d'achat successives.
     */
    public function recordIn(
        StockItem $item,
        float $quantity,
        int $unitCost,
        string $sourceType = StockMovement::SOURCE_MANUAL,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité entrée doit être positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $sourceType, $sourceId, $reason) {
            // Verrou pessimiste : deux réceptions simultanées du même article ne
            // doivent pas se baser sur le même stock de départ.
            $item = StockItem::lockForUpdate()->find($item->id);

            $currentQty   = (float) $item->current_stock;
            $currentValue = $currentQty * $item->average_cost;
            $incomingValue = $quantity * $unitCost;
            $newQty       = $currentQty + $quantity;

            $newAverage = $newQty > 0
                ? (int) round(($currentValue + $incomingValue) / $newQty)
                : $unitCost;

            $item->update([
                'current_stock'       => $newQty,
                'average_cost'        => $newAverage,
                'last_purchase_price' => $unitCost,
            ]);

            return $this->log($item, StockMovement::TYPE_IN, $quantity, $unitCost, $sourceType, $sourceId, $reason);
        });
    }

    /**
     * Sortie de stock (livraison à un département, perte, correction négative).
     * La sortie est valorisée au coût moyen courant, jamais au dernier prix.
     */
    public function recordOut(
        StockItem $item,
        float $quantity,
        string $sourceType = StockMovement::SOURCE_MANUAL,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité sortie doit être positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $sourceType, $sourceId, $reason) {
            $item = StockItem::lockForUpdate()->find($item->id);

            // On ne sort jamais plus que ce qui est présent : un stock négatif
            // n'a pas de sens physique et fausserait la valorisation.
            if ((float) $item->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Stock insuffisant pour « {$item->name} » : "
                    . "{$item->current_stock} {$item->unit} disponible(s), {$quantity} demandé(s)."
                );
            }

            $newQty = (float) $item->current_stock - $quantity;
            $item->update(['current_stock' => $newQty]);

            return $this->log($item, StockMovement::TYPE_OUT, -$quantity, $item->average_cost, $sourceType, $sourceId, $reason);
        });
    }

    /**
     * Ajustement d'inventaire : fixe le stock à une quantité constatée. Sert à
     * caler la base sur un comptage physique. Positif ou négatif selon l'écart.
     */
    public function adjust(StockItem $item, float $countedQuantity, ?string $reason = null): ?StockMovement
    {
        if ($countedQuantity < 0) {
            throw new \InvalidArgumentException('La quantité constatée ne peut pas être négative.');
        }

        return DB::transaction(function () use ($item, $countedQuantity, $reason) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $delta = $countedQuantity - (float) $item->current_stock;

            if (abs($delta) < 0.0005) {
                return null;   // Rien à corriger.
            }

            $item->update(['current_stock' => $countedQuantity]);

            return $this->log(
                $item,
                StockMovement::TYPE_ADJUSTMENT,
                $delta,
                $item->average_cost,
                StockMovement::SOURCE_MANUAL,
                null,
                $reason ?? 'Ajustement d\'inventaire'
            );
        });
    }

    private function log(
        StockItem $item,
        string $type,
        float $signedQuantity,
        int $unitCost,
        string $sourceType,
        ?int $sourceId,
        ?string $reason
    ): StockMovement {
        return StockMovement::create([
            'stock_item_id' => $item->id,
            'type'          => $type,
            'quantity'      => $signedQuantity,
            'stock_after'   => $item->current_stock,
            'unit_cost'     => $unitCost,
            'source_type'   => $sourceType,
            'source_id'     => $sourceId,
            'reason'        => $reason,
            'user_id'       => Auth::id(),
            'occurred_at'   => now(),
            'tenant_id'     => $item->tenant_id,
        ]);
    }
}
