<?php

namespace App\Services;

use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\StockItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moteur des mouvements d'un dépôt de service, comme StockService l'est pour
 * l'économat : journal, stock courant et coût moyen pondéré au même endroit.
 */
class ServiceStoreService
{
    /**
     * Entrée dans le dépôt (livraison de l'économat). L'article arrive au coût
     * auquel il est sorti du magasin central, et dilue le CUMP du dépôt.
     */
    public function receive(
        ServiceStore $store,
        StockItem $item,
        float $quantity,
        int $unitCost,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reason = null
    ): ServiceStoreMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité reçue doit être positive.');
        }

        return DB::transaction(function () use ($store, $item, $quantity, $unitCost, $sourceType, $sourceId, $reason) {
            // Même verrou que l'ouverture d'un inventaire : une livraison et
            // une ouverture du même dépôt ne peuvent pas se croiser.
            ServiceStore::query()->lockForUpdate()->findOrFail($store->id);

            // Dépôt en cours de comptage : une entrée maintenant serait lue
            // comme une consommation à la clôture.
            if ($inventaire = ServiceStoreCountService::inProgress($store)) {
                throw new \RuntimeException(
                    "Inventaire {$inventaire->reference} en cours au dépôt {$store->name} : il ne reçoit plus de livraison avant sa clôture."
                );
            }

            ServiceStoreStock::query()->firstOrCreate(
                ['service_store_id' => $store->id, 'stock_item_id' => $item->id],
                ['current_stock' => 0, 'average_cost' => $unitCost]
            );

            // Verrou : deux livraisons simultanées ne partent pas du même stock.
            $stock = ServiceStoreStock::query()
                ->where('service_store_id', $store->id)
                ->where('stock_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $courant = (float) $stock->current_stock;
            $nouveau = $courant + $quantity;

            $stock->update([
                'current_stock' => $nouveau,
                'average_cost'  => $courant > 0
                    ? (int) round(($courant * $stock->average_cost + $quantity * $unitCost) / $nouveau)
                    : $unitCost,
            ]);

            return ServiceStoreMovement::create([
                'service_store_id' => $store->id,
                'stock_item_id'    => $item->id,
                'type'             => ServiceStoreMovement::TYPE_IN,
                'quantity'         => $quantity,
                'stock_after'      => $nouveau,
                'unit_cost'        => $unitCost,
                'stock_account'    => $item->stockAccount(),
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'reason'           => $reason,
                'user_id'          => Auth::id(),
                'occurred_at'      => now(),
            ]);
        });
    }

    /**
     * Sortie du dépôt, au coût moyen du dépôt. Vers un lieu qui reste du stock
     * de l'hôtel (le mini-bar d'une chambre), aucune charge : elle naîtra à la
     * consommation. Remise à un bénéficiaire (SOURCE_BENEFICIAIRE), la
     * marchandise est consommée : le grand livre en porte la charge.
     */
    public function issue(
        ServiceStore $store,
        StockItem $item,
        float $quantity,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reason = null
    ): ServiceStoreMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité sortie doit être positive.');
        }

        return DB::transaction(function () use ($store, $item, $quantity, $sourceType, $sourceId, $reason) {
            ServiceStore::query()->lockForUpdate()->findOrFail($store->id);

            if ($inventaire = ServiceStoreCountService::inProgress($store)) {
                throw new \RuntimeException(
                    "Inventaire {$inventaire->reference} en cours au dépôt {$store->name} : rien n'en sort avant sa clôture."
                );
            }

            $stock = ServiceStoreStock::query()
                ->where('service_store_id', $store->id)
                ->where('stock_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            $courant = (float) ($stock?->current_stock ?? 0);
            if ($stock === null || $courant + 0.0001 < $quantity) {
                throw new \RuntimeException(
                    "Le dépôt {$store->name} n'a plus que " . rtrim(rtrim(number_format($courant, 3, ',', ' '), '0'), ',')
                    . " {$item->unit} de {$item->name}."
                );
            }

            $nouveau = round($courant - $quantity, 3);
            $stock->update(['current_stock' => $nouveau]);

            return ServiceStoreMovement::create([
                'service_store_id' => $store->id,
                'stock_item_id'    => $item->id,
                'type'             => ServiceStoreMovement::TYPE_OUT,
                'quantity'         => -$quantity,
                'stock_after'      => $nouveau,
                'unit_cost'        => (int) $stock->average_cost,
                'stock_account'    => $item->stockAccount(),
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'reason'           => $reason,
                'user_id'          => Auth::id(),
                'occurred_at'      => now(),
            ]);
        });
    }

    /**
     * Correction du stock du dépôt d'une quantité signée (classeur importé).
     * Comme l'écart d'un inventaire, un manque est une consommation du service
     * et un excédent la reprend.
     */
    public function ajuster(
        ServiceStore $store,
        StockItem $item,
        float $ecart,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reason = null
    ): ServiceStoreMovement {
        if (abs($ecart) < 0.0005) {
            throw new \InvalidArgumentException("L'ajustement doit corriger une quantité.");
        }

        return DB::transaction(function () use ($store, $item, $ecart, $sourceType, $sourceId, $reason) {
            ServiceStore::query()->lockForUpdate()->findOrFail($store->id);

            if ($inventaire = ServiceStoreCountService::inProgress($store)) {
                throw new \RuntimeException(
                    "Inventaire {$inventaire->reference} en cours au dépôt {$store->name} : son stock ne se corrige qu'à la clôture."
                );
            }

            ServiceStoreStock::query()->firstOrCreate(
                ['service_store_id' => $store->id, 'stock_item_id' => $item->id],
                ['current_stock' => 0, 'average_cost' => (int) $item->average_cost]
            );

            $stock = ServiceStoreStock::query()
                ->where('service_store_id', $store->id)
                ->where('stock_item_id', $item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $nouveau = round((float) $stock->current_stock + $ecart, 3);
            if ($nouveau < -0.0005) {
                throw new \RuntimeException(
                    "Le dépôt {$store->name} ne peut pas descendre sous zéro pour {$item->name}."
                );
            }

            $stock->update(['current_stock' => max(0, $nouveau)]);

            return ServiceStoreMovement::create([
                'service_store_id' => $store->id,
                'stock_item_id'    => $item->id,
                'type'             => ServiceStoreMovement::TYPE_ADJUSTMENT,
                'quantity'         => $ecart,
                'stock_after'      => max(0, $nouveau),
                'unit_cost'        => (int) $stock->average_cost,
                'stock_account'    => $item->stockAccount(),
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'reason'           => $reason,
                'user_id'          => Auth::id(),
                'occurred_at'      => now(),
            ]);
        });
    }
}
