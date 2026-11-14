<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\RestaurantBarBottle;
use App\Models\RestaurantBarDrink;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuItem;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement as Movement;
use App\Models\ServiceStoreStock;
use App\Models\StockItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantBarStock
{
    public function storeFor(PointOfSale $restaurant): ?ServiceStore
    {
        if (! $restaurant->offre(PointOfSale::SERVICE_BAR) || ! $restaurant->is_active) {
            return null;
        }

        return $restaurant->serviceStores()->active()->where('restaurant_service', PointOfSale::SERVICE_BAR)
            ->orderBy('id')->first();
    }

    public static function canOpen(StockItem $item): bool
    {
        return in_array(mb_strtolower(trim($item->unit)), [
            'bouteille', 'bouteilles', 'btl', 'bt', 'pièce', 'pièces', 'piece', 'pieces', 'pcs', 'pc', 'u', 'unité', 'unite',
        ], true);
    }

    public function openBottle(ServiceStore $store, StockItem $item, int $capacity, string $key): RestaurantBarBottle
    {
        return DB::transaction(function () use ($store, $item, $capacity, $key) {
            $this->lockStore($store);

            if ($alreadyOpened = RestaurantBarBottle::where('opening_key', $key)->first()) {
                abort_unless($alreadyOpened->service_store_id === $store->id
                    && $alreadyOpened->stock_item_id === $item->id && $alreadyOpened->capacity_ml === $capacity, 409);

                return $alreadyOpened;
            }
            if ($capacity < 1) {
                throw ValidationException::withMessages(['capacity_ml' => 'Renseignez la contenance de la bouteille.']);
            }

            $stock = $store->stocks()->where('stock_item_id', $item->id)->first();
            if (! $stock || $stock->current_stock < 1 || ! self::canOpen($item)) {
                throw ValidationException::withMessages(['stock_item_id' => 'Sélectionnez une bouteille fermée disponible dans ce dépôt.']);
            }

            $bottle = $store->barBottles()->create([
                'stock_item_id' => $item->id,
                'capacity_ml' => $capacity,
                'remaining_ml' => $capacity,
                'opening_cost' => $stock->average_cost,
                'opened_by' => auth()->id(),
                'opening_key' => $key,
            ]);

            $this->moveStock($store, $item, $stock, -1, Movement::SOURCE_BAR_OPEN, $bottle->id, 'Ouverture de la bouteille', [
                'bottle_id' => $bottle->id,
                'remaining_ml' => $capacity,
            ]);

            return $bottle;
        });
    }

    /**
     * Les lignes configurées au bar sortent du dépôt du restaurant. Les autres
     * lignes continuent de suivre les fiches du garde-manger.
     *
     * @return array{lineIds: list<int>, shortages: array, cost: int}
     */
    public function deductForOrder(RestaurantCustomerOrder $order): array
    {
        $result = ['lineIds' => [], 'shortages' => [], 'cost' => 0];
        $lines = $order->items->where('station', RestaurantCustomerOrderItem::STATION_BAR);
        if (! $order->point_of_sale_id || $lines->isEmpty()) {
            return $result;
        }

        $lines->loadMissing('menuItem.barDrink');
        $configured = $lines->filter(fn (RestaurantCustomerOrderItem $line) => $line->menuItem?->barDrink !== null);
        if ($configured->isEmpty()) {
            return $result;
        }

        $restaurant = PointOfSale::findOrFail($order->point_of_sale_id);
        if (! $restaurant->offre(PointOfSale::SERVICE_BAR)) {
            return $result;
        }

        $store = $this->storeFor($restaurant) ?? throw ValidationException::withMessages([
            'restaurant' => 'Le dépôt Bar de ce restaurant doit être actif. Vérifiez le service Bar dans Paramètres › Restaurant.',
        ]);
        $this->lockStore($store);

        $alreadyConsumed = $this->activeSales($order)->groupBy(fn (Movement $movement) => $movement->details['order_line_id']);

        foreach ($configured as $line) {
            $drink = $line->menuItem->barDrink;
            $result['lineIds'][] = $line->id;

            if ($remaining = $alreadyConsumed->get($line->id)) {
                $result['cost'] -= $remaining->sum(fn (Movement $movement) => $movement->details['value_delta']);
                continue;
            }

            foreach ($drink->ingredients as $ingredient) {
                $item = StockItem::find($ingredient['stock_item_id']) ?? throw ValidationException::withMessages([
                    'stock' => 'Un article de la fiche « '.$line->item_name.' » a été supprimé. Mettez à jour sa fiche Bar.',
                ]);
                $quantity = (float) $line->quantity * (float) $ingredient['quantity'];
                $details = [
                    'order_line_id' => $line->id,
                    'menu_item_id' => $line->menu_item_id,
                    'sale_label' => $line->item_name,
                    'recipe_id' => in_array($drink->format, ['cocktail', 'mocktail'], true) ? $drink->id : null,
                    'format' => $drink->format,
                    'instructions' => $drink->instructions,
                ];

                if ($ingredient['measure'] === 'ml') {
                    $result['cost'] += $this->pour($store, $item, (int) round($quantity), $order->id, $details, $result['shortages']);
                    continue;
                }

                $stock = $this->stock($store, $item);
                if ((float) $stock->current_stock < $quantity) {
                    $result['shortages'][] = $this->shortage($item, $quantity, (float) $stock->current_stock, $item->unit);
                    $details['shortage_quantity'] = round($quantity - max(0, (float) $stock->current_stock), 3);
                }

                $movement = $this->moveStock($store, $item, $stock, -$quantity, Movement::SOURCE_BAR_SALE, $order->id, $line->item_name, $details);
                $result['cost'] -= $movement->details['value_delta'];
            }
        }

        return $result;
    }

    private function pour(ServiceStore $store, StockItem $item, int $volume, int $orderId, array $details, array &$shortages): int
    {
        $remaining = $volume;
        $cost = 0;
        $bottles = $store->barBottles()->where('stock_item_id', $item->id)
            ->where('remaining_ml', '>', 0)->orderBy('id')->get();

        foreach ($bottles as $bottle) {
            if ($remaining === 0) {
                break;
            }

            $taken = min($remaining, $bottle->remaining_ml);
            $movement = $this->moveBottle($store, $item, $bottle, -$taken, Movement::SOURCE_BAR_SALE, $orderId, $details['sale_label'], $details);
            $cost -= $movement->details['value_delta'];
            $remaining -= $taken;
        }

        if ($remaining > 0) {
            $this->stock($store, $item);
            $shortages[] = $this->shortage($item, $volume, $volume - $remaining, 'mL');
            $lastBottle = $bottles->last() ?? $store->barBottles()->where('stock_item_id', $item->id)->latest('id')->first();
            $shortageCost = $lastBottle ? $lastBottle->valueAt($remaining) : 0;
            $this->logVolume($store, $item, -$remaining, Movement::SOURCE_BAR_SALE, $orderId, $details['sale_label'], $details + [
                'unallocated' => true, 'value_delta' => -$shortageCost,
            ]);
            $cost += $shortageCost;
        }

        return $cost;
    }

    public function recordedLineIds(RestaurantCustomerOrder $order): array
    {
        return $this->activeSales($order)->pluck('details.order_line_id')->unique()->values()->all();
    }

    /** La restitution rejoue les mouvements d'origine, même si la fiche technique a été modifiée. */
    public function restoreForOrder(RestaurantCustomerOrder $order): int
    {
        $sales = $this->activeSales($order);
        $prepared = $order->items->filter(fn (RestaurantCustomerOrderItem $line) => $line->ready_at !== null)->pluck('id');
        $retainedCost = 0;

        foreach ($sales->groupBy('service_store_id')->sortKeys() as $storeId => $movements) {
            $store = ServiceStore::findOrFail($storeId);
            $this->lockStore($store, allowInactive: true);
            $offsets = $this->offsets($store, null);

            foreach ($movements as $movement) {
                $details = $movement->details;
                if ($order->served_at || $order->status === RestaurantCustomerOrder::STATUS_SERVED || $prepared->contains($details['order_line_id'])) {
                    $retainedCost += ($details['unallocated'] ?? false)
                        ? $this->outstanding($movement, $offsets)[1]
                        : -$details['value_delta'];
                    continue;
                }

                $item = StockItem::findOrFail($movement->stock_item_id);
                $details = $details + ['reverses' => $movement->id];
                $reason = 'Annulation avant préparation — '.$details['sale_label'];

                if (isset($details['bottle_id'])) {
                    $bottle = $store->barBottles()->findOrFail($details['bottle_id']);
                    $countedAfter = $store->movements()->where('source_type', Movement::SOURCE_BAR_COUNT)
                        ->where('source_id', $bottle->id)->where('id', '>', $movement->id)->exists();

                    if ($countedAfter) {
                        $retainedCost -= $details['value_delta'];
                        $this->logVolume($store, $item, 0, Movement::SOURCE_BAR_RETURN, $order->id,
                            'Annulation après comptage physique — '.$details['sale_label'], [
                                ...$details, 'remaining_ml' => $bottle->remaining_ml, 'value_delta' => 0,
                            ]);
                        continue;
                    }

                    $this->moveBottle($store, $item, $bottle, -$details['volume_ml'], Movement::SOURCE_BAR_RETURN, $order->id, $reason, $details);
                } elseif ($details['unallocated'] ?? false) {
                    [$outstanding, $value] = $this->outstanding($movement, $offsets);
                    $this->logVolume($store, $item, $outstanding, Movement::SOURCE_BAR_RETURN, $order->id, $reason, [
                        ...$details, 'value_delta' => $value,
                    ]);
                } else {
                    $this->moveStock($store, $item, $this->stock($store, $item), -(float) $movement->quantity, Movement::SOURCE_BAR_RETURN, $order->id, $reason, $details, $movement->unit_cost);
                }
            }
        }

        return $retainedCost;
    }

    public function countBottle(ServiceStore $store, RestaurantBarBottle $bottle, int $volume, string $reason): void
    {
        DB::transaction(function () use ($store, $bottle, $volume, $reason) {
            $this->lockStore($store, allowDuringCount: true);
            $bottle = $store->barBottles()->findOrFail($bottle->id);
            $delta = $volume - $bottle->remaining_ml;
            $this->moveBottle($store, $bottle->item, $bottle, $delta, Movement::SOURCE_BAR_COUNT, $bottle->id, $reason);
        });
    }

    public function unallocated(ServiceStore $store, ?int $itemId = null): Collection
    {
        $offsets = $this->offsets($store, $itemId);

        return $store->movements()->with('item')->where('source_type', Movement::SOURCE_BAR_SALE)
            ->where('details->unallocated', true)
            ->when($itemId, fn ($query) => $query->where('stock_item_id', $itemId))
            ->orderBy('id')->get()->map(function (Movement $movement) use ($offsets) {
                [$volume] = $this->outstanding($movement, $offsets);

                return ['movement' => $movement, 'volume' => $volume];
            })->filter(fn (array $row) => $row['volume'] > 0)->values();
    }

    public function allocate(ServiceStore $store, RestaurantBarBottle $bottle, int $movementId, int $volume, string $key): void
    {
        DB::transaction(function () use ($store, $bottle, $movementId, $volume, $key) {
            $sale = $store->movements()->where('source_type', Movement::SOURCE_BAR_SALE)
                ->where('details->unallocated', true)->where('stock_item_id', $bottle->stock_item_id)->findOrFail($movementId);
            $order = RestaurantCustomerOrder::query()->lockForUpdate()->findOrFail($sale->source_id);
            $this->lockStore($store, allowDuringCount: true);
            $bottle = $store->barBottles()->findOrFail($bottle->id);
            $prior = $store->movements()->where('source_type', Movement::SOURCE_BAR_SALE)
                ->where('details->allocation_key', $key)->first();
            if ($prior) {
                abort_unless($prior->details['bottle_id'] === $bottle->id
                    && $prior->details['allocates'] === $sale->id && $prior->details['volume_ml'] === -$volume, 409);

                return;
            }

            [$outstanding, $outstandingValue] = $this->outstanding($sale, $this->offsets($store, $bottle->stock_item_id));

            if ($volume < 1 || $volume > $outstanding || $volume > $bottle->remaining_ml) {
                throw ValidationException::withMessages(['volume_ml' => 'Le volume dépasse le reste à attribuer ou le volume restant dans la bouteille.']);
            }

            $item = $bottle->item;
            $details = collect($sale->details)->except(['unallocated', 'value_delta', 'volume_ml'])->all();
            $reason = 'Attribution — '.$details['sale_label'];
            $movement = $this->moveBottle($store, $item, $bottle, -$volume, Movement::SOURCE_BAR_SALE, $sale->source_id, $reason, $details + [
                'allocates' => $sale->id, 'allocation_key' => $key,
            ]);
            $offsetValue = (int) round($outstandingValue * $volume / $outstanding);
            $this->logVolume($store, $item, $volume, Movement::SOURCE_BAR_VARIANCE, $sale->source_id, $reason, $details + [
                'unallocated' => true,
                'offsets' => $sale->id,
                'value_delta' => $offsetValue,
            ]);

            $order->update(['food_cost' => (int) $order->food_cost - $offsetValue - $movement->details['value_delta']]);
        });
    }

    private function offsets(ServiceStore $store, ?int $itemId): Collection
    {
        return $store->movements()->whereIn('source_type', [Movement::SOURCE_BAR_RETURN, Movement::SOURCE_BAR_VARIANCE])
            ->where('details->unallocated', true)
            ->when($itemId, fn ($query) => $query->where('stock_item_id', $itemId))
            ->get()->groupBy(fn (Movement $movement) => $movement->details['offsets'] ?? $movement->details['reverses']);
    }

    /** Volume et valeur de la sortie qui restent à rattacher à une bouteille. */
    private function outstanding(Movement $original, Collection $offsets): array
    {
        $adjustments = $offsets->get($original->id, collect());

        return [
            -$original->details['volume_ml'] - $adjustments->sum('details.volume_ml'),
            -$original->details['value_delta'] - $adjustments->sum('details.value_delta'),
        ];
    }

    /** @return Collection<int, Movement> */
    private function activeSales(RestaurantCustomerOrder $order): Collection
    {
        $movements = Movement::where('source_id', $order->id)
            ->whereIn('source_type', [Movement::SOURCE_BAR_SALE, Movement::SOURCE_BAR_RETURN])->get();
        $reversed = $movements->where('source_type', Movement::SOURCE_BAR_RETURN)->pluck('details.reverses');

        return $movements->where('source_type', Movement::SOURCE_BAR_SALE)->whereNotIn('id', $reversed);
    }

    private function lockStore(ServiceStore $store, bool $allowInactive = false, bool $allowDuringCount = false): void
    {
        $locked = ServiceStore::query()->lockForUpdate()->findOrFail($store->id);
        if ($locked->restaurant_service !== PointOfSale::SERVICE_BAR || (! $allowInactive && ! $locked->is_active)) {
            throw ValidationException::withMessages(['restaurant' => 'Le dépôt Bar est désactivé.']);
        }

        if (! $allowDuringCount && ServiceStoreCountService::inProgress($locked)) {
            throw ValidationException::withMessages(['stock' => 'Un inventaire est en cours au dépôt Bar. Clôturez-le avant de modifier le stock.']);
        }
    }

    private function stock(ServiceStore $store, StockItem $item): ServiceStoreStock
    {
        return $store->stocks()->firstOrCreate(['stock_item_id' => $item->id], [
            'current_stock' => 0,
            'average_cost' => $item->average_cost,
        ]);
    }

    private function moveStock(ServiceStore $store, StockItem $item, ServiceStoreStock $stock, float $delta, string $source, int $id, string $reason, array $details, ?int $unitCost = null): Movement
    {
        $current = (float) $stock->current_stock;
        $after = round($current + $delta, 3);
        $cost = $unitCost ?? (int) $stock->average_cost;
        $stock->update([
            'current_stock' => $after,
            'average_cost' => $delta > 0 && $after > 0
                ? ($current > 0 ? (int) round(($current * $stock->average_cost + $delta * $cost) / $after) : $cost)
                : $stock->average_cost,
        ]);

        return $store->movements()->create([
            'stock_item_id' => $item->id,
            'type' => $delta > 0 ? Movement::TYPE_IN : Movement::TYPE_OUT,
            'quantity' => $delta,
            'stock_after' => $after,
            'unit_cost' => $cost,
            'stock_account' => $item->stockAccount(),
            'source_type' => $source,
            'source_id' => $id,
            'reason' => $reason,
            'details' => [...$details, 'value_delta' => (int) round($delta * $cost)],
            'user_id' => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    private function moveBottle(ServiceStore $store, StockItem $item, RestaurantBarBottle $bottle, int $delta, string $source, int $id, string $reason, array $details = []): Movement
    {
        $after = $bottle->remaining_ml + $delta;
        if ($after < 0 || $after > $bottle->capacity_ml) {
            throw ValidationException::withMessages(['stock' => 'Le volume résultant dépasse la contenance de la bouteille. Vérifiez son comptage.']);
        }

        $valueDelta = $bottle->valueAt($after) - $bottle->valueAt($bottle->remaining_ml);
        $bottle->update(['remaining_ml' => $after]);

        return $this->logVolume($store, $item, $delta, $source, $id, $reason, [
            ...$details, 'bottle_id' => $bottle->id, 'remaining_ml' => $after, 'value_delta' => $valueDelta,
        ]);
    }

    private function logVolume(ServiceStore $store, StockItem $item, int $delta, string $source, int $id, string $reason, array $details): Movement
    {
        return $store->movements()->create([
            'stock_item_id' => $item->id,
            'type' => $source === Movement::SOURCE_BAR_COUNT || $delta === 0
                ? Movement::TYPE_ADJUSTMENT
                : ($delta > 0 ? Movement::TYPE_IN : Movement::TYPE_OUT),
            'quantity' => 0,
            'stock_after' => $store->stocks()->where('stock_item_id', $item->id)->value('current_stock') ?? 0,
            'unit_cost' => 0,
            'stock_account' => $item->stockAccount(),
            'source_type' => $source,
            'source_id' => $id,
            'reason' => $reason,
            'details' => [...$details, 'volume_ml' => $delta],
            'user_id' => auth()->id(),
            'occurred_at' => now(),
        ]);
    }

    private function shortage(StockItem $item, float $needed, float $available, string $unit): array
    {
        return ['item' => $item->name.' (Bar)', 'needed' => $needed, 'available' => max(0, $available), 'unit' => $unit];
    }
}
