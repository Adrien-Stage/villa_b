<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\RestaurantStockCountLine;
use App\Models\RestaurantWasteLog;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * StockControlService : Moteur de contrôle, valorisation globale, analyse des écarts,
 * détection des seuils de réapprovisionnement et propositions automatiques de commande.
 *
 * Consolide le Magasin Central (Économat) et le Garde-Manger (Restaurant).
 */
class StockControlService
{
    /**
     * Calcule la valorisation globale du stock de l'établissement.
     *
     * @return array{
     *     economat_value: int,
     *     pantry_value: int,
     *     total_value: int,
     *     economat_items_count: int,
     *     pantry_items_count: int,
     *     categories: array<int, array{name: string, value: int, count: int}>
     * }
     */
    public function getGlobalValuation(?int $tenantId = null): array
    {
        // 1. Magasin Central (Économat)
        $economatItems = StockItem::query()
            ->active()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('category')
            ->get();

        $economatValue = 0;
        $categoriesBreakdown = [];

        foreach ($economatItems as $item) {
            $val = $item->stockValue();
            $economatValue += $val;

            $catName = $item->category?->name ?? 'Général / Non classé';
            if (!isset($categoriesBreakdown[$catName])) {
                $categoriesBreakdown[$catName] = ['name' => $catName, 'value' => 0, 'count' => 0, 'type' => 'economat'];
            }
            $categoriesBreakdown[$catName]['value'] += $val;
            $categoriesBreakdown[$catName]['count']++;
        }

        // 2. Garde-Manger (Restaurant)
        $pantryItems = RestaurantPantryItem::query()
            ->active()
            ->with('category')
            ->get();

        $pantryValue = 0;
        foreach ($pantryItems as $pItem) {
            $pVal = (int) $pItem->stockValue();
            $pantryValue += $pVal;

            $pCatName = 'Restaurant — ' . ($pItem->category?->name ?? 'Garde-manger');
            if (!isset($categoriesBreakdown[$pCatName])) {
                $categoriesBreakdown[$pCatName] = ['name' => $pCatName, 'value' => 0, 'count' => 0, 'type' => 'restaurant'];
            }
            $categoriesBreakdown[$pCatName]['value'] += $pVal;
            $categoriesBreakdown[$pCatName]['count']++;
        }

        return [
            'economat_value'       => (int) $economatValue,
            'pantry_value'         => (int) $pantryValue,
            'total_value'          => (int) ($economatValue + $pantryValue),
            'economat_items_count' => $economatItems->count(),
            'pantry_items_count'   => $pantryItems->count(),
            'categories'           => array_values($categoriesBreakdown),
        ];
    }

    /**
     * Identifie tous les articles sous le stock minimum ou en rupture complète.
     */
    public function getLowStockAlerts(?int $tenantId = null): array
    {
        $economatLow = StockItem::query()
            ->active()
            ->belowThreshold()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['category', 'supplier'])
            ->orderBy('current_stock')
            ->get();

        $pantryLow = RestaurantPantryItem::query()
            ->active()
            ->lowStock()
            ->with('category')
            ->orderBy('current_stock')
            ->get();

        return [
            'economat' => $economatLow,
            'pantry'   => $pantryLow,
            'total'    => $economatLow->count() + $pantryLow->count(),
        ];
    }

    /**
     * Génère des propositions de commande de réapprovisionnement basées sur les stocks minimums,
     * les niveaux actuels et les conditionnements d'achat.
     *
     * @return array<int, array{
     *     item: StockItem,
     *     current_stock: float,
     *     min_stock: float,
     *     target_stock: float,
     *     suggested_quantity: float,
     *     unit: string,
     *     estimated_unit_price: int,
     *     estimated_total_cost: int,
     *     supplier: ?\App\Models\Supplier
     * }>
     */
    public function generateOrderSuggestions(?int $tenantId = null): array
    {
        $items = StockItem::query()
            ->active()
            ->belowThreshold()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['category', 'supplier'])
            ->get();

        $suggestions = [];

        foreach ($items as $item) {
            $current = (float) $item->current_stock;
            $min = (float) $item->min_stock;

            // Règle de réapprovisionnement : réaligner sur le stock idéal (2x le stock de sécurité)
            $target = max($min * 2.0, 1.0);
            $needed = max(0.0, $target - $current);

            if ($needed <= 0.001) {
                continue;
            }

            // Arrondi au demi ou entier le plus proche
            $qty = ceil($needed);
            $unitCost = (int) ($item->last_purchase_price ?: $item->average_cost ?: 0);
            $totalCost = (int) round($qty * $unitCost);

            $suggestions[] = [
                'item'                 => $item,
                'current_stock'        => $current,
                'min_stock'            => $min,
                'target_stock'         => $target,
                'suggested_quantity'   => $qty,
                'unit'                 => $item->unit,
                'estimated_unit_price' => $unitCost,
                'estimated_total_cost' => $totalCost,
                'supplier'             => $item->supplier,
            ];
        }

        return $suggestions;
    }

    /**
     * Transforme une sélection de propositions de réapprovisionnement en vraie Demande d'Achat interne.
     *
     * @param  array<int, array{item_id: int, quantity: float, notes?: string|null}>  $selectedItems
     */
    public function createPurchaseRequestFromSuggestions(
        array $selectedItems,
        User $user,
        ?int $tenantId = null,
        string $priority = PurchaseRequest::PRIORITY_NORMAL
    ): PurchaseRequest {
        if (empty($selectedItems)) {
            throw new RuntimeException('Aucun article sélectionné pour la commande.');
        }

        return DB::transaction(function () use ($selectedItems, $user, $tenantId, $priority) {
            $totalEstimated = 0;
            $linesData = [];

            foreach ($selectedItems as $entry) {
                $itemId = (int) ($entry['item_id'] ?? 0);
                $qty = (float) ($entry['quantity'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                $item = StockItem::query()->findOrFail($itemId);
                $unitPrice = (int) ($item->last_purchase_price ?: $item->average_cost ?: 0);
                $lineTotal = (int) round($qty * $unitPrice);

                $totalEstimated += $lineTotal;

                $linesData[] = [
                    'stock_item_id'          => $item->id,
                    'item_name'              => $item->name,
                    'item_reference'         => $item->reference,
                    'unit'                   => $item->unit,
                    'quantity_requested'     => $qty,
                    'estimated_unit_price'   => $unitPrice,
                    'estimated_total_amount' => $lineTotal,
                    'notes'                  => $entry['notes'] ?? 'Généré automatiquement par le contrôle des seuils de stock',
                ];
            }

            if (empty($linesData)) {
                throw new RuntimeException('Les quantités demandées doivent être strictement supérieures à zéro.');
            }

            $purchaseRequest = PurchaseRequest::create([
                'department'             => 'economat',
                'priority'               => $priority,
                'status'                 => PurchaseRequest::STATUS_PENDING,
                'purpose'                => 'Réapprovisionnement automatique suite alerte stock bas',
                'total_estimated_amount' => $totalEstimated,
                'requested_by'           => $user->id,
                'tenant_id'              => $tenantId ?? $user->tenant_id,
            ]);

            foreach ($linesData as $data) {
                $data['purchase_request_id'] = $purchaseRequest->id;
                PurchaseRequestLine::create($data);
            }

            return $purchaseRequest->fresh(['lines.stockItem']);
        });
    }

    /**
     * Analyse et synthétise tous les écarts d'inventaire constatés sur une période.
     */
    public function getInventoryVariancesSummary(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $tenantId = null
    ): array {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        // 1. Écarts inventaires Économat
        $economatLines = StockCountLine::query()
            ->with(['stockCount', 'stockItem.category'])
            ->whereHas('stockCount', function ($q) use ($start, $end, $tenantId) {
                $q->where('status', 'closed')
                  ->whereBetween('closed_at', [$start, $end])
                  ->when($tenantId, fn ($tq) => $tq->where('tenant_id', $tenantId));
            })
            ->whereRaw('abs(variance_quantity) > 0.0005')
            ->get();

        $economatVarianceValue = 0;
        $economatLossValue = 0;
        $economatSurplusValue = 0;

        foreach ($economatLines as $line) {
            $val = (int) $line->variance_value;
            $economatVarianceValue += $val;
            if ($val < 0) {
                $economatLossValue += abs($val);
            } else {
                $economatSurplusValue += $val;
            }
        }

        // 2. Écarts inventaires Garde-Manger Restaurant
        $restaurantLines = RestaurantStockCountLine::query()
            ->with(['stockCount', 'item.category'])
            ->whereHas('stockCount', function ($q) use ($start, $end) {
                $q->where('status', 'closed')
                  ->whereBetween('closed_at', [$start, $end]);
            })
            ->whereRaw('abs(variance_quantity) > 0.0005')
            ->get();

        $restaurantVarianceValue = 0;
        $restaurantLossValue = 0;
        $restaurantSurplusValue = 0;

        foreach ($restaurantLines as $rLine) {
            $val = (int) $rLine->variance_value;
            $restaurantVarianceValue += $val;
            if ($val < 0) {
                $restaurantLossValue += abs($val);
            } else {
                $restaurantSurplusValue += $val;
            }
        }

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'economat' => [
                'lines'          => $economatLines,
                'count'          => $economatLines->count(),
                'variance_value' => $economatVarianceValue,
                'loss_value'     => $economatLossValue,
                'surplus_value'  => $economatSurplusValue,
            ],
            'restaurant' => [
                'lines'          => $restaurantLines,
                'count'          => $restaurantLines->count(),
                'variance_value' => $restaurantVarianceValue,
                'loss_value'     => $restaurantLossValue,
                'surplus_value'  => $restaurantSurplusValue,
            ],
            'total_variance_value' => $economatVarianceValue + $restaurantVarianceValue,
            'total_loss_value'     => $economatLossValue + $restaurantLossValue,
            'total_surplus_value'  => $economatSurplusValue + $restaurantSurplusValue,
        ];
    }

    /**
     * Rapport consolidé exécutif pour le Contrôle de Gestion et la Direction Générale.
     */
    public function getExecutiveReport(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $tenantId = null
    ): array {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $valuation = $this->getGlobalValuation($tenantId);
        $variances = $this->getInventoryVariancesSummary($start, $end, $tenantId);
        $alerts = $this->getLowStockAlerts($tenantId);

        // Achats reçus sur la période : valeur acceptée des bons d'entrée non
        // annulés. Une réception annulée n'a jamais enrichi le stock.
        $totalPurchasesReceived = (int) GoodsReceiptLine::query()
            ->whereHas('receipt', function ($q) use ($start, $end, $tenantId) {
                $q->where('status', GoodsReceipt::STATUS_RECEIVED)
                  ->whereBetween('received_at', [$start, $end])
                  ->when($tenantId, fn ($tq) => $tq->where('tenant_id', $tenantId));
            })
            ->sum('total_cost');

        // Réquisitions livrées aux départements : la ligne de demande ne porte
        // pas de coût, la valeur servie est celle des sorties de stock au CUMP.
        $deliveredRequisitionIds = StockRequisition::query()
            ->where('status', StockRequisition::STATUS_DELIVERED)
            ->whereBetween('delivered_at', [$start, $end])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->pluck('id');

        $totalRequisitionsDelivered = (int) StockMovement::query()
            ->where('source_type', StockMovement::SOURCE_REQUISITION)
            ->where('type', StockMovement::TYPE_OUT)
            ->whereIn('source_id', $deliveredRequisitionIds)
            ->get(['quantity', 'unit_cost'])
            ->sum(fn (StockMovement $m) => (int) round(abs((float) $m->quantity) * $m->unit_cost));

        // Pertes restaurant
        $wasteLogs = RestaurantWasteLog::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('occurred_at', [$start, $end])
            ->get();
        $totalWasteValue = (int) $wasteLogs->sum('total_cost');

        // CA Restaurant et Food Cost
        $restaurantOrders = RestaurantCustomerOrder::query()
            ->whereBetween('placed_at', [$start, $end])
            ->whereNotIn('status', [RestaurantCustomerOrder::STATUS_CANCELED]);
        $restaurantRevenue = (int) $restaurantOrders->sum('total_amount');
        $theoreticalFoodCost = (int) $restaurantOrders->sum('food_cost');

        $realKitchenCost = $theoreticalFoodCost + $totalWasteValue + $variances['restaurant']['loss_value'];
        $foodCostPercent = $restaurantRevenue > 0 ? round(($realKitchenCost / $restaurantRevenue) * 100, 2) : 0.0;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'valuation'                    => $valuation,
            'alerts'                       => $alerts,
            'variances'                    => $variances,
            'total_purchases_received'     => $totalPurchasesReceived,
            'total_requisitions_delivered' => $totalRequisitionsDelivered,
            'total_waste_value'            => $totalWasteValue,
            'restaurant_revenue'           => $restaurantRevenue,
            'theoretical_food_cost'        => $theoreticalFoodCost,
            'real_kitchen_cost'            => $realKitchenCost,
            'food_cost_percent'            => $foodCostPercent,
        ];
    }
}
