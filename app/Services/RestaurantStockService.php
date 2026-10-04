<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantStockCount;
use App\Models\RestaurantStockCountLine;
use App\Models\RestaurantWasteLog;
use App\Models\StockRequisition;
use App\Notifications\PantryItemLowStock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Le moteur du garde-manger.
 *
 * Toute écriture de stock passe par ici : c'est la seule façon de garantir que le
 * stock, le coût moyen pondéré et la piste d'audit des mouvements restent
 * cohérents entre eux.
 *
 * Deux principes structurent le module :
 *
 *  - Le stock est théorique. Il se déduit des fiches techniques : vendre 5 ndolé
 *    sort 2,5 kg d'arachide. L'inventaire physique confronte ce théorique au réel,
 *    et l'écart mesure le gaspillage, le sur-portionnage et le vol.
 *
 *  - Le coût suit le stock. Chaque entrée recalcule le coût moyen pondéré de
 *    l'ingrédient, donc le coût matière des plats et la marge se mettent à jour
 *    tout seuls quand le prix de l'arachide monte.
 */
class RestaurantStockService
{
    /**
     * Enregistre un mouvement et met le stock à jour, en une seule transaction.
     *
     * Les entrées recalculent le coût moyen pondéré ; les sorties sont valorisées
     * au coût moyen du moment. Le stock peut devenir négatif : la cuisine sait
     * parfois mieux que le système, et bloquer une vente sur une donnée de stock
     * imparfaite coûte plus cher que de tolérer un stock négatif signalé.
     */
    public function recordMovement(
        RestaurantPantryItem $item,
        string $type,
        float $quantity,
        string $reason,
        ?float $unitCost = null,
        ?string $notes = null,
        ?RestaurantCustomerOrder $order = null,
        ?RestaurantRecipe $recipe = null,
        ?CarbonInterface $occurredAt = null,
        ?int $stockRequisitionId = null,
        ?int $restaurantWasteLogId = null,
    ): RestaurantPantryMovement {
        return DB::transaction(function () use (
            $item, $type, $quantity, $reason, $unitCost, $notes, $order, $recipe, $occurredAt, $stockRequisitionId, $restaurantWasteLogId
        ) {
            // Verrou : deux ventes simultanées ne doivent pas lire le même stock.
            $item = RestaurantPantryItem::query()->lockForUpdate()->findOrFail($item->id);

            $current = (float) $item->current_stock;
            $average = (float) $item->average_cost;
            $wasAboveThreshold = !$item->isLowStock();

            switch ($type) {
                case RestaurantPantryMovement::TYPE_IN:
                    $cost = $unitCost ?? $average;
                    $next = $current + $quantity;

                    // Coût moyen pondéré : le nouveau lot dilue l'ancien.
                    // Un stock négatif fausserait la pondération, on repart du coût du lot.
                    if ($next > 0 && $current >= 0) {
                        $average = (($current * $average) + ($quantity * $cost)) / $next;
                    } elseif ($cost > 0) {
                        $average = $cost;
                    }
                    break;

                case RestaurantPantryMovement::TYPE_OUT:
                    $cost = $unitCost ?? $average;
                    $next = $current - $quantity;
                    break;

                case RestaurantPantryMovement::TYPE_ADJUST:
                    // La quantité est le stock absolu constaté, pas un delta.
                    $cost = $unitCost ?? $average;
                    $next = $quantity;
                    break;

                default:
                    throw new RuntimeException("Type de mouvement inconnu : {$type}.");
            }

            $movement = RestaurantPantryMovement::create([
                'restaurant_pantry_item_id' => $item->id,
                'type' => $type,
                'quantity' => round($quantity, 3),
                'unit_cost' => round($cost, 4),
                'total_cost' => (int) round($quantity * $cost),
                'stock_after' => round($next, 3),
                'restaurant_customer_order_id' => $order?->id,
                'restaurant_recipe_id' => $recipe?->id,
                'stock_requisition_id' => $stockRequisitionId,
                'restaurant_waste_log_id' => $restaurantWasteLogId,
                'reason' => $reason,
                'notes' => $notes,
                'recorded_by' => Auth::id(),
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $item->update([
                'current_stock' => round($next, 3),
                'average_cost' => round($average, 4),
            ]);

            // Seul le franchissement alerte : la cuisine sort des ingrédients à
            // chaque service, renotifier sur chaque sortie noierait le signal.
            // afterCommit car une vente déduit plusieurs ingrédients d'un coup,
            // dans une transaction englobante qui peut encore échouer.
            if ($wasAboveThreshold && $item->isLowStock()) {
                DB::afterCommit(fn () => app(Notifier::class)
                    ->toRoles(['restaurant_chief', 'manager'], new PantryItemLowStock($item)));
            }

            return $movement;
        });
    }

    /**
     * Réception de marchandise, saisie en unités d'achat (3 sacs de 50 kg) et
     * convertie en unités de stock. Le prix total payé fixe le coût du lot, donc
     * le nouveau coût moyen pondéré.
     *
     * @param  float     $purchaseQuantity  Nombre d'unités d'achat reçues
     * @param  int|null  $totalPrice        Prix total payé, en centimes FCFA
     */
    public function receive(
        RestaurantPantryItem $item,
        float $purchaseQuantity,
        ?int $totalPrice = null,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): RestaurantPantryMovement {
        $stockQuantity = $purchaseQuantity * $item->conversion();

        if ($stockQuantity <= 0) {
            throw new RuntimeException('La quantité reçue doit être supérieure à zéro.');
        }

        $unitCost = $totalPrice !== null && $totalPrice > 0
            ? $totalPrice / $stockQuantity
            : null;

        return $this->recordMovement(
            item: $item,
            type: RestaurantPantryMovement::TYPE_IN,
            quantity: $stockQuantity,
            reason: RestaurantPantryMovement::REASON_PURCHASE,
            unitCost: $unitCost,
            notes: $notes,
            occurredAt: $occurredAt,
        );
    }

    /**
     * Réception de marchandise en provenance de l'Économat (transfert interne).
     *
     * @param  RestaurantPantryItem  $item         L'ingrédient de destination
     * @param  float                 $quantity     Quantité reçue en unité de stock garde-manger
     * @param  float|int             $unitCost     Coût unitaire en centimes FCFA par unité de garde-manger
     * @param  StockRequisition      $requisition  Le bon de sortie / demande de l'économat
     * @param  string|null           $notes        Commentaire éventuel
     * @param  CarbonInterface|null  $occurredAt   Date effective
     */
    public function receiveFromEconomat(
        RestaurantPantryItem $item,
        float $quantity,
        float|int $unitCost,
        StockRequisition $requisition,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
    ): RestaurantPantryMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité reçue du transfert doit être supérieure à zéro.');
        }

        return $this->recordMovement(
            item: $item,
            type: RestaurantPantryMovement::TYPE_IN,
            quantity: $quantity,
            reason: RestaurantPantryMovement::REASON_TRANSFER_IN,
            unitCost: (float) $unitCost,
            notes: $notes ?? "Transfert Économat (BS #{$requisition->number})",
            occurredAt: $occurredAt,
            stockRequisitionId: $requisition->id,
        );
    }

    /**
     * Sort du garde-manger les ingrédients de tous les plats d'une commande.
     *
     * Idempotent : une commande déjà déduite est ignorée. Fige au passage le coût
     * matière de la commande, ce qui donne la marge réelle de la vente.
     *
     * @return array<int, array{item: string, needed: float, available: float, unit: string}>
     *         Les ingrédients tombés en négatif — à remonter au chef.
     */
    public function deductForOrder(RestaurantCustomerOrder $order): array
    {
        if ($order->stockWasDeducted()) {
            return [];
        }

        $order->loadMissing('items');

        $requirements = $this->explodeOrder($order);
        $shortages = [];

        DB::transaction(function () use ($order, $requirements, &$shortages) {
            $foodCost = 0.0;

            foreach ($requirements as $requirement) {
                /** @var RestaurantPantryItem $item */
                $item = $requirement['item'];
                $quantity = $requirement['quantity'];

                $available = (float) $item->current_stock;

                if ($available < $quantity) {
                    $shortages[] = [
                        'item' => $item->name,
                        'needed' => $quantity,
                        'available' => max(0, $available),
                        'unit' => $item->unit,
                    ];
                }

                $movement = $this->recordMovement(
                    item: $item,
                    type: RestaurantPantryMovement::TYPE_OUT,
                    quantity: $quantity,
                    reason: RestaurantPantryMovement::REASON_SALE,
                    notes: "Commande #{$order->id}",
                    order: $order,
                );

                $foodCost += (float) $movement->total_cost;
            }

            $order->update([
                'stock_deducted_at' => now(),
                'food_cost' => (int) round($foodCost),
            ]);
        });

        return $shortages;
    }

    /**
     * Remet en stock les ingrédients d'une commande annulée après son envoi en
     * cuisine. Idempotent : une commande non déduite est ignorée.
     */
    public function restoreForOrder(RestaurantCustomerOrder $order): void
    {
        if (!$order->stockWasDeducted()) {
            return;
        }

        $order->loadMissing('items');

        $requirements = $this->explodeOrder($order);

        DB::transaction(function () use ($order, $requirements) {
            foreach ($requirements as $requirement) {
                $this->recordMovement(
                    item: $requirement['item'],
                    type: RestaurantPantryMovement::TYPE_IN,
                    quantity: $requirement['quantity'],
                    reason: RestaurantPantryMovement::REASON_SALE_RETURN,
                    notes: "Annulation de la commande #{$order->id}",
                    order: $order,
                );
            }

            $order->update([
                'stock_deducted_at' => null,
                'food_cost' => null,
            ]);
        });
    }

    /**
     * Fabrique un batch d'une préparation de base : sort ses ingrédients et fait
     * entrer la préparation en stock, valorisée à son coût de revient réel.
     *
     * @param  float  $batches  Nombre de fois la fiche (1 = un rendement)
     */
    public function produce(RestaurantRecipe $recipe, float $batches = 1.0): RestaurantPantryItem
    {
        if (!$recipe->isPreparation() || !$recipe->produces_pantry_item_id) {
            throw new RuntimeException("Cette fiche n'est pas une préparation de base.");
        }

        if ($batches <= 0) {
            throw new RuntimeException('Le nombre de batchs doit être supérieur à zéro.');
        }

        $recipe->loadMissing(['lines.item', 'producedItem']);

        if ($recipe->lines->isEmpty()) {
            throw new RuntimeException("Cette préparation n'a aucun ingrédient : impossible de la produire.");
        }

        return DB::transaction(function () use ($recipe, $batches) {
            $totalCost = 0.0;

            foreach ($recipe->lines as $line) {
                if (!$line->item) {
                    continue;
                }

                $movement = $this->recordMovement(
                    item: $line->item,
                    type: RestaurantPantryMovement::TYPE_OUT,
                    quantity: $line->grossQuantity() * $batches,
                    reason: RestaurantPantryMovement::REASON_PRODUCTION,
                    notes: "Production : {$recipe->name}",
                    recipe: $recipe,
                );

                $totalCost += (float) $movement->total_cost;
            }

            $produced = $recipe->yield() * $batches;

            // Le coût de revient du batch devient le coût unitaire de la préparation.
            $this->recordMovement(
                item: $recipe->producedItem,
                type: RestaurantPantryMovement::TYPE_IN,
                quantity: $produced,
                reason: RestaurantPantryMovement::REASON_PRODUCTION,
                unitCost: $produced > 0 ? $totalCost / $produced : 0,
                notes: "Production de {$batches} batch(s)",
                recipe: $recipe,
            );

            return $recipe->producedItem->fresh();
        });
    }

    /**
     * Nombre de portions encore réalisables avec le stock actuel — l'ingrédient le
     * plus contraignant décide. Null si le plat n'a pas de fiche technique active.
     */
    public function availablePortions(RestaurantMenuItem $menuItem): ?int
    {
        $recipe = $menuItem->recipe;

        if (!$recipe || !$recipe->is_active) {
            return null;
        }

        $recipe->loadMissing('lines.item');

        if ($recipe->lines->isEmpty()) {
            return null;
        }

        $portions = null;

        foreach ($recipe->lines as $line) {
            if (!$line->item) {
                continue;
            }

            $perPortion = $line->grossQuantity() / $recipe->yield();

            if ($perPortion <= 0) {
                continue;
            }

            $possible = (int) floor(max(0, (float) $line->item->current_stock) / $perPortion);

            $portions = $portions === null ? $possible : min($portions, $possible);
        }

        return $portions;
    }

    /**
     * Ouvre une feuille de comptage : fige le stock théorique de chaque ingrédient
     * actif au moment de l'ouverture.
     */
    public function openStockCount(?string $notes = null, ?PointOfSale $restaurant = null): RestaurantStockCount
    {
        return DB::transaction(function () use ($notes, $restaurant) {
            // Chaque restaurant compte son garde-manger.
            $count = RestaurantStockCount::create([
                'point_of_sale_id' => $restaurant?->id,
                'reference' => 'INV-' . now()->format('Ymd-His') . ($restaurant ? '-' . mb_substr($restaurant->code, 0, 10) : ''),
                'status' => RestaurantStockCount::STATUS_DRAFT,
                'notes' => $notes,
                'opened_by' => Auth::id(),
            ]);

            $items = RestaurantPantryItem::query()->duRestaurant($count->point_of_sale_id)->active()->orderBy('name')->get();

            foreach ($items as $item) {
                RestaurantStockCountLine::create([
                    'restaurant_stock_count_id' => $count->id,
                    'restaurant_pantry_item_id' => $item->id,
                    'theoretical_quantity' => $item->current_stock,
                    'unit_cost' => $item->average_cost,
                ]);
            }

            return $count;
        });
    }

    /**
     * Clôture l'inventaire : chaque ligne comptée génère un ajustement de stock, et
     * l'écart valorisé est figé. C'est le chiffre qui compte — l'argent parti en
     * fumée entre ce que les recettes disaient et ce qui est réellement là.
     */
    public function closeStockCount(RestaurantStockCount $count): RestaurantStockCount
    {
        if ($count->isClosed()) {
            throw new RuntimeException('Cet inventaire est déjà clôturé.');
        }

        $count->loadMissing('lines.item');

        return DB::transaction(function () use ($count) {
            $totalVariance = 0;

            foreach ($count->lines as $line) {
                if (!$line->isCounted() || !$line->item) {
                    continue;
                }

                $counted = (float) $line->counted_quantity;
                $theoretical = (float) $line->theoretical_quantity;
                $variance = $counted - $theoretical;
                $unitCost = (float) $line->unit_cost;
                $varianceValue = (int) round($variance * $unitCost);

                $line->update([
                    'variance_quantity' => round($variance, 3),
                    'variance_value' => $varianceValue,
                ]);

                $totalVariance += $varianceValue;

                if (abs($variance) < 0.0005) {
                    continue;
                }

                $this->recordMovement(
                    item: $line->item,
                    type: RestaurantPantryMovement::TYPE_ADJUST,
                    quantity: $counted,
                    reason: RestaurantPantryMovement::REASON_COUNT,
                    unitCost: $unitCost,
                    notes: "Inventaire {$count->reference}",
                );
            }

            $count->update([
                'status' => RestaurantStockCount::STATUS_CLOSED,
                'variance_value' => $totalVariance,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
            ]);

            return $count->fresh(['lines.item']);
        });
    }

    /**
     * Enregistre une perte, un déchet ou un aliment non vendu (gaspillage, avarié, plat brûlé,
     * repas du personnel, casse, portion offerte, etc.)
     *
     * Valorisée rigoureusement au coût moyen pondéré du stock actuel en centimes FCFA.
     */
    public function recordWaste(
        RestaurantPantryItem $item,
        float $quantity,
        string $reason,
        ?string $department = 'cuisine',
        ?string $responsiblePerson = null,
        ?string $notes = null,
        ?CarbonInterface $occurredAt = null,
        ?int $tenantId = null,
    ): RestaurantWasteLog {
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité mise au rebut doit être supérieure à zéro.');
        }

        if (!in_array($reason, RestaurantWasteLog::REASONS, true)) {
            throw new RuntimeException("Motif de perte non reconnu : {$reason}.");
        }

        return DB::transaction(function () use (
            $item, $quantity, $reason, $department, $responsiblePerson, $notes, $occurredAt, $tenantId
        ) {
            $item = RestaurantPantryItem::query()->lockForUpdate()->findOrFail($item->id);

            $unitCost = (float) $item->average_cost;
            $totalCost = (int) round($quantity * $unitCost);
            $datePrefix = ($occurredAt ?? now())->format('Ymd');
            $randomSuffix = strtoupper(Str::random(4));
            $reference = "GSP-{$datePrefix}-{$randomSuffix}";

            $wasteLog = RestaurantWasteLog::create([
                // La perte est celle du restaurant dont le garde-manger perd l'article.
                'point_of_sale_id' => $item->point_of_sale_id,
                'reference' => $reference,
                'restaurant_pantry_item_id' => $item->id,
                'quantity' => round($quantity, 3),
                'unit_cost' => round($unitCost, 4),
                'total_cost' => $totalCost,
                'reason' => $reason,
                'department' => $department ?? RestaurantWasteLog::DEPT_KITCHEN,
                'responsible_person' => $responsiblePerson ? trim($responsiblePerson) : null,
                'notes' => $notes ? trim($notes) : null,
                'recorded_by' => Auth::id(),
                'tenant_id' => $tenantId ?? Auth::user()?->tenant_id,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $this->recordMovement(
                item: $item,
                type: RestaurantPantryMovement::TYPE_OUT,
                quantity: $quantity,
                reason: RestaurantPantryMovement::REASON_WASTE,
                notes: "Perte [{$wasteLog->reference}] : " . ($notes ?? $wasteLog->reasonLabel()),
                occurredAt: $occurredAt,
                restaurantWasteLogId: $wasteLog->id,
            );

            return $wasteLog;
        });
    }

    /**
     * Analyse et rapproche la consommation théorique (recettes POS / PDJ), les entrées magasin (Économat),
     * les pertes déclarées (Gaspillage) et les ajustements d'inventaire sur une période donnée.
     *
     * Permet le calcul du Food Cost % et l'analyse des écarts de matière.
     */
    public function getKitchenConsumptionReport(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        ?int $tenantId = null,
        ?array $restaurants = null,
    ): array {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        // Restreint, s'il le faut, aux restaurants demandés.
        $movements = RestaurantPantryMovement::query()
            ->when($restaurants !== null, fn ($q) => $q->whereHas('item', fn ($i) => $i->whereIn('point_of_sale_id', $restaurants)))
            ->with(['item.category', 'wasteLog', 'order'])
            ->whereBetween('occurred_at', [$start, $end])
            ->get();

        $transfersInCost = 0;
        $purchasesCost = 0;
        $theoreticalSalesCost = 0;
        $wasteCost = 0;
        $inventoryAdjustmentCost = 0;

        $wasteByReason = [];
        $itemStats = [];

        foreach ($movements as $m) {
            $itemId = $m->restaurant_pantry_item_id;
            if (!$m->item) {
                continue;
            }

            if (!isset($itemStats[$itemId])) {
                $itemStats[$itemId] = [
                    'item' => $m->item,
                    'in_transfers_qty' => 0.0,
                    'in_transfers_cost' => 0,
                    'theoretical_sales_qty' => 0.0,
                    'theoretical_sales_cost' => 0,
                    'waste_qty' => 0.0,
                    'waste_cost' => 0,
                    'adjustment_qty' => 0.0,
                    'adjustment_cost' => 0,
                ];
            }

            $cost = (int) $m->total_cost;
            $qty = (float) $m->quantity;

            if ($m->reason === RestaurantPantryMovement::REASON_TRANSFER_IN) {
                $transfersInCost += $cost;
                $itemStats[$itemId]['in_transfers_qty'] += $qty;
                $itemStats[$itemId]['in_transfers_cost'] += $cost;
            } elseif ($m->reason === RestaurantPantryMovement::REASON_PURCHASE) {
                $purchasesCost += $cost;
            } elseif ($m->reason === RestaurantPantryMovement::REASON_SALE) {
                $theoreticalSalesCost += $cost;
                $itemStats[$itemId]['theoretical_sales_qty'] += $qty;
                $itemStats[$itemId]['theoretical_sales_cost'] += $cost;
            } elseif ($m->reason === RestaurantPantryMovement::REASON_SALE_RETURN) {
                $theoreticalSalesCost -= $cost;
                $itemStats[$itemId]['theoretical_sales_qty'] -= $qty;
                $itemStats[$itemId]['theoretical_sales_cost'] -= $cost;
            } elseif ($m->reason === RestaurantPantryMovement::REASON_WASTE) {
                $wasteCost += $cost;
                $itemStats[$itemId]['waste_qty'] += $qty;
                $itemStats[$itemId]['waste_cost'] += $cost;

                $reasonKey = $m->wasteLog?->reason ?? 'other';
                $wasteByReason[$reasonKey] = ($wasteByReason[$reasonKey] ?? 0) + $cost;
            } elseif ($m->reason === RestaurantPantryMovement::REASON_COUNT) {
                $diffCost = ($m->type === RestaurantPantryMovement::TYPE_OUT ? -$cost : $cost);
                $diffQty = ($m->type === RestaurantPantryMovement::TYPE_OUT ? -$qty : $qty);
                $inventoryAdjustmentCost += $diffCost;
                $itemStats[$itemId]['adjustment_qty'] += $diffQty;
                $itemStats[$itemId]['adjustment_cost'] += $diffCost;
            }
        }

        $ordersQuery = RestaurantCustomerOrder::query()
            ->when($restaurants !== null, fn ($q) => $q->whereIn('point_of_sale_id', $restaurants))
            ->whereBetween('placed_at', [$start, $end])
            ->whereNotIn('status', [RestaurantCustomerOrder::STATUS_CANCELED]);

        $totalRevenue = (int) $ordersQuery->sum('total_amount');
        $ordersCount = $ordersQuery->count();
        $ordersFoodCost = (int) $ordersQuery->sum('food_cost');

        $totalKitchenCost = $theoreticalSalesCost + $wasteCost;
        $foodCostRatio = $totalRevenue > 0 ? round(($totalKitchenCost / $totalRevenue) * 100, 2) : 0.0;

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'transfers_in_cost' => $transfersInCost,
            'purchases_cost' => $purchasesCost,
            'theoretical_sales_cost' => $theoreticalSalesCost,
            'waste_cost' => $wasteCost,
            'inventory_adjustment_cost' => $inventoryAdjustmentCost,
            'total_kitchen_cost' => $totalKitchenCost,
            'total_revenue' => $totalRevenue,
            'orders_count' => $ordersCount,
            'orders_food_cost' => $ordersFoodCost,
            'food_cost_ratio' => $foodCostRatio,
            'waste_by_reason' => $wasteByReason,
            'items' => array_values($itemStats),
        ];
    }

    /**
     * Éclate les plats d'une commande en besoins d'ingrédients, cumulés par article
     * (deux plats partageant l'arachide ne font qu'une sortie de stock).
     *
     * @return array<int, array{item: RestaurantPantryItem, quantity: float}>
     */
    private function explodeOrder(RestaurantCustomerOrder $order): array
    {
        $menuItemIds = $order->items->pluck('menu_item_id')->filter()->unique();

        $recipes = RestaurantRecipe::query()
            ->active()
            ->dishes()
            ->whereIn('restaurant_menu_item_id', $menuItemIds)
            ->with('lines.item')
            ->get()
            ->keyBy('restaurant_menu_item_id');

        /** @var array<int, array{item: RestaurantPantryItem, quantity: float}> $requirements */
        $requirements = [];

        foreach ($order->items as $orderItem) {
            $recipe = $recipes->get($orderItem->menu_item_id);

            // Plat sans fiche technique : rien à déduire. C'est volontaire — un plat
            // non fiché ne doit pas bloquer la vente, il n'est simplement pas suivi.
            if (!$recipe) {
                continue;
            }

            $portions = (float) $orderItem->quantity;

            foreach ($recipe->lines as $line) {
                if (!$line->item) {
                    continue;
                }

                $needed = $line->grossQuantity() / $recipe->yield() * $portions;

                $id = $line->item->id;

                if (!isset($requirements[$id])) {
                    $requirements[$id] = ['item' => $line->item, 'quantity' => 0.0];
                }

                $requirements[$id]['quantity'] += $needed;
            }
        }

        return array_values($requirements);
    }
}
