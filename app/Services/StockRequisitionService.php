<?php

namespace App\Services;

use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\ServiceStoreMovement;
use App\Models\ShopProduct;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'une demande d'un département à l'économat : validation par l'économe,
 * puis livraison qui déstocke réellement les articles et met à jour les stocks
 * du département de destination.
 *
 * La séparation validation / livraison est volontaire : l'économe peut
 * approuver le principe, puis servir plus tard, et ajuster à la livraison les
 * quantités réellement disponibles.
 */
class StockRequisitionService
{
    private RestaurantStockService $restaurantStock;

    private ServiceStoreService $serviceStores;

    public function __construct(
        private StockService $stock,
        ?RestaurantStockService $restaurantStock = null,
        ?ServiceStoreService $serviceStores = null
    ) {
        $this->restaurantStock = $restaurantStock ?? app(RestaurantStockService::class);
        $this->serviceStores = $serviceStores ?? app(ServiceStoreService::class);
    }

    public function approve(StockRequisition $requisition, ?string $notes = null): void
    {
        DB::transaction(function () use ($requisition, $notes) {
            $requisition = $this->lockForReview($requisition);

            // Pendant un inventaire, l'économat ne s'engage pas à servir : il
            // ne pourrait pas livrer avant la clôture.
            if ($inventaire = StockCount::inProgress()) {
                throw new \RuntimeException(
                    "Inventaire {$inventaire->reference} en cours : les demandes se valident après sa clôture."
                );
            }

            $requisition->update([
                'status'       => StockRequisition::STATUS_APPROVED,
                'review_notes' => $notes,
                'reviewed_by'  => auth()->id(),
                'reviewed_at'  => now(),
            ]);
        });

        // L'appelant garde son instance : on lui rend l'état écrit sous verrou.
        $requisition->refresh();
    }

    public function reject(StockRequisition $requisition, ?string $notes = null): void
    {
        DB::transaction(function () use ($requisition, $notes) {
            $requisition = $this->lockForReview($requisition);

            $requisition->update([
                'status'       => StockRequisition::STATUS_REJECTED,
                'review_notes' => $notes,
                'reviewed_by'  => auth()->id(),
                'reviewed_at'  => now(),
            ]);
        });

        // L'appelant garde son instance : on lui rend l'état écrit sous verrou.
        $requisition->refresh();
    }

    /** Relit la demande sous verrou : deux validations simultanées se suivent. */
    private function lockForReview(StockRequisition $requisition): StockRequisition
    {
        $requisition = StockRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

        if (!$requisition->canBeReviewed()) {
            throw new \RuntimeException('Cette demande a déjà été traitée.');
        }

        return $requisition;
    }

    /**
     * Livraison : sort du stock les quantités réellement servies et clôt la
     * demande. Met également à jour le stock du département destinataire
     * (garde-manger restaurant, boutique) de façon atomique.
     *
     * @param  array<int, float>  $issued  [line_id => quantité servie]
     */
    public function deliver(StockRequisition $requisition, array $issued = []): void
    {
        DB::transaction(function () use ($requisition, $issued) {
            // Verrou sur la demande : un double clic livrerait deux fois et
            // déstockerait deux fois. Le statut se relit sous verrou.
            $requisition = StockRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if (!$requisition->canBeDelivered()) {
                throw new \RuntimeException('La demande doit être validée avant d\'être livrée.');
            }

            $requisition->load('lines.item', 'serviceStore');

            foreach ($requisition->lines as $line) {
                if (!$line->item) {
                    continue;
                }

                // Par défaut on sert ce qui a été demandé ; l'économe peut
                // réduire, mais jamais servir plus que le stock présent.
                $qty = array_key_exists($line->id, $issued)
                    ? (float) $issued[$line->id]
                    : (float) $line->quantity_requested;

                $qty = $line->item->availableFor($qty);
                if ($qty <= 0) {
                    $line->update(['quantity_issued' => 0]);
                    continue;
                }

                $movement = $this->stock->recordOut(
                    $line->item,
                    $qty,
                    StockMovement::SOURCE_REQUISITION,
                    $requisition->id,
                    "Demande {$requisition->number} — {$requisition->departmentLabel()}"
                );

                $line->update(['quantity_issued' => $qty]);

                // Transfert vers le sous-stock du département de destination.
                // Un dépôt désigné l'emporte : le bar relève du restaurant, mais
                // ce qu'on lui livre entre dans son dépôt, pas au garde-manger.
                if ($requisition->serviceStore !== null) {
                    $this->serviceStores->receive(
                        $requisition->serviceStore,
                        $line->item,
                        $qty,
                        (int) $movement->unit_cost,
                        ServiceStoreMovement::SOURCE_REQUISITION,
                        $requisition->id,
                        "Livraison économat — demande {$requisition->number}"
                    );
                } elseif ($requisition->department === 'restaurant') {
                    $this->creditRestaurantPantry($line->item, $qty, (int) $movement->unit_cost, $requisition);
                } elseif ($requisition->department === 'boutique') {
                    $this->creditShopProduct($line->item, $qty);
                }
            }

            $requisition->update([
                'status'       => StockRequisition::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
        });

        $requisition->refresh();
    }

    /**
     * Crédite le garde-manger du restaurant lors d'un transfert interne.
     */
    protected function creditRestaurantPantry(
        StockItem $stockItem,
        float $economatQty,
        int $economatUnitCost,
        StockRequisition $requisition
    ): void {
        // Chaque restaurant a son garde-manger : celui que la demande désigne.
        $pantryItem = $this->resolveOrCreatePantryItem($stockItem, $requisition->point_of_sale_id);

        // Facteur de conversion : stock garde-manger = stock économat * ratio
        $conversion = (float) $pantryItem->conversion();

        $economatUnit = mb_strtolower(trim($stockItem->unit ?? ''));
        $pantryUnit = mb_strtolower(trim($pantryItem->unit ?? ''));

        if ($conversion <= 1.0) {
            if ($economatUnit === 'kg' && $pantryUnit === 'g') {
                $conversion = 1000.0;
            } elseif ($economatUnit === 'l' && $pantryUnit === 'ml') {
                $conversion = 1000.0;
            }
        }

        $pantryQty = round($economatQty * $conversion, 3);
        $pantryUnitCost = $conversion > 0 ? ((float) $economatUnitCost / $conversion) : (float) $economatUnitCost;

        $this->restaurantStock->receiveFromEconomat(
            item: $pantryItem,
            quantity: $pantryQty,
            unitCost: $pantryUnitCost,
            requisition: $requisition,
            notes: "Transfert Économat (BS #{$requisition->number})",
        );
    }

    /**
     * Retrouve ou crée automatiquement l'article du garde-manger correspondant à l'article de l'économat.
     */
    protected function resolveOrCreatePantryItem(StockItem $stockItem, ?int $restaurant = null): RestaurantPantryItem
    {
        // 1. Recherche par stock_item_id
        $item = RestaurantPantryItem::duRestaurant($restaurant)->where('stock_item_id', $stockItem->id)->first();
        if ($item) {
            return $item;
        }

        // 2. Recherche par nom identique
        $trimmedName = trim($stockItem->name);
        $item = RestaurantPantryItem::duRestaurant($restaurant)->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($trimmedName)])->first();
        if ($item) {
            if (!$item->stock_item_id) {
                $item->update(['stock_item_id' => $stockItem->id]);
            }
            return $item;
        }

        // 3. Création automatique si introuvable dans le garde-manger
        $defaultCategory = RestaurantPantryCategory::first();

        return RestaurantPantryItem::create([
            'point_of_sale_id'              => $restaurant,
            'stock_item_id'                 => $stockItem->id,
            'restaurant_pantry_category_id' => $defaultCategory?->id,
            'name'                          => $trimmedName,
            'unit'                          => $stockItem->unit ?: 'pcs',
            'purchase_unit'                 => $stockItem->unit ?: 'pcs',
            'purchase_conversion'           => 1.0,
            'current_stock'                 => 0,
            'min_stock'                     => 0,
            'cost_price'                    => $stockItem->average_cost,
            'average_cost'                  => $stockItem->average_cost,
            'is_prepared'                   => false,
            'is_active'                     => true,
        ]);
    }

    /**
     * Incrémente le stock de la boutique si un produit correspondant existe.
     *
     * La référence de l'article (= SKU du produit) fait foi avant le nom, qui
     * peut varier d'un module à l'autre. Sans produit correspondant, la
     * livraison est une fourniture consommée par la boutique (sacs, tickets…)
     * et n'entre dans aucun stock de vente.
     */
    protected function creditShopProduct(StockItem $stockItem, float $qty): void
    {
        $product = null;

        if (!empty($stockItem->reference)) {
            $product = ShopProduct::where('sku', $stockItem->reference)->first();
        }

        if (!$product) {
            $trimmedName = trim($stockItem->name);
            $product = ShopProduct::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($trimmedName)])->first();
        }

        if (!$product) {
            return;
        }

        // La boutique compte en unités entières : arrondir ferait apparaître
        // ou disparaître de la marchandise entre les deux stocks.
        if (abs($qty - round($qty)) > 0.0005) {
            throw new \RuntimeException(
                "« {$stockItem->name} » se vend à l'unité en boutique : "
                . "servez une quantité entière ({$qty} {$stockItem->unit} demandé(s))."
            );
        }

        $product->increment('stock_quantity', (int) round($qty));
    }
}
