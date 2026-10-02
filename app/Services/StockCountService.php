<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockCountService
{
    public function __construct(private StockService $stockService)
    {
    }

    /**
     * Ouvre une nouvelle feuille d'inventaire physique.
     * Fige le stock théorique et le CUMP de tous les articles concernés.
     */
    public function open(array $data, ?User $user = null): StockCount
    {
        // Un seul inventaire ouvert à la fois pour éviter la concurrence sur les stocks théoriques
        $existing = StockCount::where('status', StockCount::STATUS_DRAFT)->first();
        if ($existing) {
            throw new RuntimeException("L'inventaire {$existing->reference} est déjà en cours. Veuillez le clôturer ou l'annuler avant d'en ouvrir un nouveau.");
        }

        return DB::transaction(function () use ($data, $user) {
            $countDate = !empty($data['count_date']) ? Carbon::parse($data['count_date'])->toDateString() : now()->toDateString();
            $categoryId = !empty($data['stock_category_id']) ? (int) $data['stock_category_id'] : null;

            $stockCount = StockCount::create([
                'stock_category_id' => $categoryId,
                'status'            => StockCount::STATUS_DRAFT,
                'count_date'        => $countDate,
                'notes'             => $data['notes'] ?? null,
                'opened_by'         => $user?->id ?? Auth::id(),
            ]);

            $query = StockItem::query()->active()->orderBy('name');
            if ($categoryId) {
                $query->where('stock_category_id', $categoryId);
            }

            // Verrou des articles : un mouvement en cours se termine avant le
            // relevé du théorique, et le suivant verra l'inventaire ouvert.
            $items = $query->lockForUpdate()->get();
            $totalTheoreticalValue = 0;

            foreach ($items as $item) {
                $theoreticalQty = (float) $item->current_stock;
                $unitCost = (int) $item->average_cost;
                $theoreticalValue = (int) round($theoreticalQty * $unitCost);

                StockCountLine::create([
                    'stock_count_id'       => $stockCount->id,
                    'stock_item_id'        => $item->id,
                    'theoretical_quantity' => $theoreticalQty,
                    'counted_quantity'     => null, // non compté initialement
                    'variance_quantity'    => 0,
                    'unit_cost'            => $unitCost,
                    'theoretical_value'    => $theoreticalValue,
                    'counted_value'        => null,
                    'variance_value'       => 0,
                ]);

                $totalTheoreticalValue += $theoreticalValue;
            }

            $stockCount->update([
                'total_theoretical_value' => $totalTheoreticalValue,
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Enregistre les comptages physiques saisis par l'économe/magasinier.
     * Met à jour les écarts et motifs sans modifier le stock réel.
     */
    public function updateCounts(StockCount $stockCount, array $linesData): StockCount
    {
        if ($stockCount->isClosed()) {
            throw new RuntimeException("Cet inventaire est déjà clôturé.");
        }

        return DB::transaction(function () use ($stockCount, $linesData) {
            $stockCount = $this->lockDraft($stockCount);
            $stockCount->load('lines.item');

            $totalCountedValue = 0;
            $totalVariance = 0;
            $totalLoss = 0;
            $totalSurplus = 0;

            foreach ($stockCount->lines as $line) {
                if (!array_key_exists($line->id, $linesData)) {
                    continue;
                }

                $input = $linesData[$line->id];
                $countedRaw = $input['counted_quantity'] ?? null;
                $reason = !empty($input['reason']) ? trim((string) $input['reason']) : null;
                $notes = !empty($input['notes']) ? trim((string) $input['notes']) : null;

                if ($countedRaw === null || $countedRaw === '') {
                    $line->update([
                        'counted_quantity'  => null,
                        'variance_quantity' => 0,
                        'counted_value'     => null,
                        'variance_value'    => 0,
                        'reason'            => null,
                        'notes'             => $notes,
                    ]);
                    continue;
                }

                $countedQty = max(0, (float) $countedRaw);
                $theoreticalQty = (float) $line->theoretical_quantity;
                $varianceQty = round($countedQty - $theoreticalQty, 3);
                $unitCost = (int) $line->unit_cost;
                $countedValue = (int) round($countedQty * $unitCost);
                $varianceValue = (int) round($varianceQty * $unitCost);

                $line->update([
                    'counted_quantity'  => $countedQty,
                    'variance_quantity' => $varianceQty,
                    'counted_value'     => $countedValue,
                    'variance_value'    => $varianceValue,
                    'reason'            => $varianceQty != 0 ? $reason : null,
                    'notes'             => $notes,
                ]);

                $totalCountedValue += $countedValue;
                $totalVariance += $varianceValue;

                if ($varianceValue < 0) {
                    $totalLoss += abs($varianceValue);
                } elseif ($varianceValue > 0) {
                    $totalSurplus += $varianceValue;
                }
            }

            $stockCount->update([
                'total_counted_value' => $totalCountedValue,
                'variance_value'      => $totalVariance,
                'loss_value'          => $totalLoss,
                'surplus_value'       => $totalSurplus,
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Clôture l'inventaire physique :
     * 1. Ajuste le stock réel de chaque article ayant un écart via StockService::adjust
     * 2. Fige les montants financiers et passe le statut à closed
     */
    public function close(StockCount $stockCount, ?User $user = null): StockCount
    {
        if ($stockCount->isClosed()) {
            throw new RuntimeException("Cet inventaire est déjà clôturé.");
        }

        return DB::transaction(function () use ($stockCount, $user) {
            // Une clôture rejouée appliquerait deux fois les ajustements ; une
            // feuille annulée ne doit jamais toucher au stock.
            $stockCount = $this->lockDraft($stockCount);
            $stockCount->load('lines.item');

            $totalCountedValue = 0;
            $totalVariance = 0;
            $totalLoss = 0;
            $totalSurplus = 0;

            foreach ($stockCount->lines as $line) {
                if (!$line->isCounted() || !$line->item) {
                    continue;
                }

                $counted = (float) $line->counted_quantity;
                $theoretical = (float) $line->theoretical_quantity;
                $variance = round($counted - $theoretical, 3);
                $unitCost = (int) $line->unit_cost;
                $varianceValue = (int) round($variance * $unitCost);
                $countedValue = (int) round($counted * $unitCost);

                $line->update([
                    'variance_quantity' => $variance,
                    'counted_value'     => $countedValue,
                    'variance_value'    => $varianceValue,
                ]);

                $totalCountedValue += $countedValue;
                $totalVariance += $varianceValue;

                if ($varianceValue < 0) {
                    $totalLoss += abs($varianceValue);
                } elseif ($varianceValue > 0) {
                    $totalSurplus += $varianceValue;
                }

                // Ajustement physique si écart significatif
                if (abs($variance) >= 0.0005) {
                    $reasonLabel = $line->reasonLabel();
                    $reasonText = "Inventaire {$stockCount->reference}";
                    if (!empty($line->reason)) {
                        $reasonText .= " ({$reasonLabel})";
                    }
                    if (!empty($line->notes)) {
                        $reasonText .= " — {$line->notes}";
                    }

                    $this->stockService->adjust(
                        item: $line->item,
                        countedQuantity: $counted,
                        reason: $reasonText,
                        sourceType: StockMovement::SOURCE_STOCK_COUNT,
                        sourceId: $stockCount->id,
                    );
                }
            }

            $stockCount->update([
                'status'              => StockCount::STATUS_CLOSED,
                'total_counted_value' => $totalCountedValue,
                'variance_value'      => $totalVariance,
                'loss_value'          => $totalLoss,
                'surplus_value'       => $totalSurplus,
                'closed_by'           => $user?->id ?? Auth::id(),
                'closed_at'           => now(),
            ]);

            return $stockCount->fresh('lines.item');
        });
    }

    /**
     * Annule une feuille d'inventaire en cours sans modifier les stocks.
     */
    public function cancel(StockCount $stockCount): void
    {
        if ($stockCount->isClosed()) {
            throw new RuntimeException("Un inventaire clôturé ne peut pas être annulé.");
        }

        DB::transaction(function () use ($stockCount) {
            $this->lockDraft($stockCount)->update([
                'status' => StockCount::STATUS_CANCELLED,
            ]);
        });

        $stockCount->refresh();
    }

    /**
     * Relit la feuille sous verrou et exige qu'elle soit encore en cours de
     * comptage : seul un brouillon se modifie, se clôture ou s'annule.
     */
    private function lockDraft(StockCount $stockCount): StockCount
    {
        $stockCount = StockCount::query()->lockForUpdate()->findOrFail($stockCount->id);

        if (!$stockCount->isDraft()) {
            throw new RuntimeException("L'inventaire {$stockCount->reference} n'est plus en cours de comptage.");
        }

        return $stockCount;
    }
}
