<?php

namespace App\Services;

use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreCountLine;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cycle de l'inventaire d'un dépôt : ouverture (théorique figé), comptage,
 * clôture qui inscrit la consommation au dépôt.
 *
 * Pendant le comptage, le dépôt ne reçoit plus de livraison : une entrée
 * passée entre l'ouverture et la clôture serait comptée comme consommée.
 */
class ServiceStoreCountService
{
    public function open(ServiceStore $store, ?User $user = null, ?string $notes = null): ServiceStoreCount
    {
        return DB::transaction(function () use ($store, $user, $notes) {
            // Verrou du dépôt : deux ouvertures simultanées ne passent pas.
            $store = ServiceStore::query()->lockForUpdate()->findOrFail($store->id);

            if ($encours = self::inProgress($store)) {
                throw new RuntimeException("L'inventaire {$encours->reference} du dépôt {$store->name} est déjà en cours.");
            }

            $inventaire = ServiceStoreCount::create([
                'service_store_id' => $store->id,
                'status'           => ServiceStoreCount::STATUS_DRAFT,
                'count_date'       => now()->toDateString(),
                'notes'            => $notes,
                'opened_by'        => $user?->id ?? auth()->id(),
            ]);

            // Verrou des stocks : une livraison en cours se termine avant le relevé.
            $stocks = ServiceStoreStock::query()
                ->where('service_store_id', $store->id)
                ->lockForUpdate()
                ->get();

            foreach ($stocks as $stock) {
                ServiceStoreCountLine::create([
                    'service_store_count_id' => $inventaire->id,
                    'stock_item_id'          => $stock->stock_item_id,
                    'theoretical_quantity'   => $stock->current_stock,
                    'unit_cost'              => $stock->average_cost,
                ]);
            }

            return $inventaire->load('lines.item');
        });
    }

    /** @param  array<int, array{counted_quantity?: mixed, notes?: string|null}>  $saisies  [ligne => saisie] */
    public function updateCounts(ServiceStoreCount $inventaire, array $saisies): void
    {
        DB::transaction(function () use ($inventaire, $saisies) {
            $inventaire = $this->lockDraft($inventaire);

            foreach ($inventaire->lines()->get() as $ligne) {
                if (!array_key_exists($ligne->id, $saisies)) {
                    continue;
                }

                $compte = $saisies[$ligne->id]['counted_quantity'] ?? null;

                $ligne->update([
                    'counted_quantity' => $compte === null || $compte === '' ? null : max(0, (float) $compte),
                    'notes'            => trim((string) ($saisies[$ligne->id]['notes'] ?? '')) ?: null,
                ]);
            }
        });
    }

    /**
     * Clôture : chaque ligne comptée cale le stock du dépôt sur le compté.
     * L'écart, presque toujours négatif, est la consommation du service.
     * Une ligne non comptée garde son stock théorique.
     */
    public function close(ServiceStoreCount $inventaire, ?User $user = null): ServiceStoreCount
    {
        return DB::transaction(function () use ($inventaire, $user) {
            $inventaire = $this->lockDraft($inventaire);
            $inventaire->load('lines.item', 'store');

            foreach ($inventaire->lines as $ligne) {
                $ecart = $ligne->varianceQuantity();

                if (!$ligne->isCounted() || abs($ecart) < 0.0005 || $ligne->item === null) {
                    continue;
                }

                $stock = ServiceStoreStock::query()
                    ->where('service_store_id', $inventaire->service_store_id)
                    ->where('stock_item_id', $ligne->stock_item_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $stock->update(['current_stock' => $ligne->counted_quantity]);

                ServiceStoreMovement::create([
                    'service_store_id' => $inventaire->service_store_id,
                    'stock_item_id'    => $ligne->stock_item_id,
                    'type'             => ServiceStoreMovement::TYPE_ADJUSTMENT,
                    'quantity'         => $ecart,
                    'stock_after'      => $ligne->counted_quantity,
                    'unit_cost'        => $ligne->unit_cost,
                    'stock_account'    => $ligne->item->stockAccount(),
                    'source_type'      => ServiceStoreMovement::SOURCE_STOCK_COUNT,
                    'source_id'        => $inventaire->id,
                    'reason'           => ($ecart < 0 ? 'Consommation' : 'Excédent') . " — inventaire {$inventaire->reference}",
                    'user_id'          => $user?->id ?? auth()->id(),
                    'occurred_at'      => now(),
                ]);
            }

            $inventaire->update([
                'status'    => ServiceStoreCount::STATUS_CLOSED,
                'closed_by' => $user?->id ?? auth()->id(),
                'closed_at' => now(),
            ]);

            return $inventaire->fresh(['lines.item', 'store']);
        });
    }

    public function cancel(ServiceStoreCount $inventaire): void
    {
        DB::transaction(function () use ($inventaire) {
            $this->lockDraft($inventaire)->update(['status' => ServiceStoreCount::STATUS_CANCELLED]);
        });
    }

    public static function inProgress(ServiceStore $store): ?ServiceStoreCount
    {
        return ServiceStoreCount::query()
            ->where('service_store_id', $store->id)
            ->where('status', ServiceStoreCount::STATUS_DRAFT)
            ->first();
    }

    private function lockDraft(ServiceStoreCount $inventaire): ServiceStoreCount
    {
        $inventaire = ServiceStoreCount::query()->lockForUpdate()->findOrFail($inventaire->id);

        if (!$inventaire->isDraft()) {
            throw new RuntimeException("L'inventaire {$inventaire->reference} n'est plus en cours de comptage.");
        }

        return $inventaire;
    }
}
