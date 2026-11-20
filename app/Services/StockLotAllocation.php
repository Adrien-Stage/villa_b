<?php

namespace App\Services;

use App\Models\StockItem;
use App\Models\StockLot;
use RuntimeException;

class StockLotAllocation
{
    /** Le stock reçu sans lot reste utilisable après les lots datés utilisables. */
    public function consume(StockItem $item, float $quantity, bool $adjustment = false): array
    {
        $lots = StockLot::query()->where('stock_item_id', $item->id)->where('quantity_remaining', '>', 0)
            ->orderBy('expires_on')->orderBy('id')->lockForUpdate()->get();
        if ($lots->isEmpty()) {
            return [];
        }
        $today = today();
        $untracked = max(0, round((float) $item->current_stock - $lots->sum('quantity_remaining'), 3));
        $eligible = $adjustment ? $lots : $lots->reject(fn ($lot) => $lot->expires_on->isBefore($today));
        if ($quantity > $untracked + $eligible->sum('quantity_remaining') + 0.0005) {
            throw new RuntimeException("Des lots de « {$item->name} » sont périmés. Constatez leur retrait avant de poursuivre.");
        }

        $result = [];
        foreach ($eligible as $lot) {
            $used = round(min($quantity, (float) $lot->quantity_remaining), 3);
            if ($used <= 0) {
                break;
            }
            $lot->update(['quantity_remaining' => round((float) $lot->quantity_remaining - $used, 3)]);
            $result[] = ['lot_id' => $lot->id, 'lot_number' => $lot->lot_number,
                'expires_on' => $lot->expires_on->toDateString(), 'quantity' => $used];
            $quantity = round($quantity - $used, 3);
        }

        return $result;
    }

    public function restore(StockItem $item, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $lot = StockLot::query()->where('stock_item_id', $item->id)->lockForUpdate()->findOrFail($allocation['lot_id']);
            $lot->update(['quantity_remaining' => round((float) $lot->quantity_remaining + $allocation['quantity'], 3)]);
        }
    }

    public function cancelReceipt(StockItem $item, int $receiptLineId, float $quantity): void
    {
        $lot = StockLot::query()->where('stock_item_id', $item->id)
            ->where('goods_receipt_line_id', $receiptLineId)->lockForUpdate()->first();
        if (!$lot) {
            return;
        }
        if (abs($quantity - (float) $lot->quantity_remaining) > 0.0005) {
            throw new RuntimeException("Le lot {$lot->lot_number} a déjà été utilisé. Le bon de réception ne peut pas être annulé.");
        }
        $lot->update(['quantity_remaining' => 0]);
    }
}
