<?php

namespace App\Services;

use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantStockCount;
use App\Models\RestaurantStockCountLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    ): RestaurantPantryMovement {
        return DB::transaction(function () use (
            $item, $type, $quantity, $reason, $unitCost, $notes, $order, $recipe, $occurredAt
        ) {
            // Verrou : deux ventes simultanées ne doivent pas lire le même stock.
            $item = RestaurantPantryItem::query()->lockForUpdate()->findOrFail($item->id);

            $current = (float) $item->current_stock;
            $average = (float) $item->average_cost;

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
                'reason' => $reason,
                'notes' => $notes,
                'recorded_by' => Auth::id(),
                'occurred_at' => $occurredAt ?? now(),
            ]);

            $item->update([
                'current_stock' => round($next, 3),
                'average_cost' => round($average, 4),
            ]);

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
    public function openStockCount(?string $notes = null): RestaurantStockCount
    {
        return DB::transaction(function () use ($notes) {
            $count = RestaurantStockCount::create([
                'reference' => 'INV-' . now()->format('Ymd-His'),
                'status' => RestaurantStockCount::STATUS_DRAFT,
                'notes' => $notes,
                'opened_by' => Auth::id(),
            ]);

            $items = RestaurantPantryItem::query()->active()->orderBy('name')->get();

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
