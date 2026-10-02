<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service de gestion des Bons de Réception contradictoires (Goods Receipts).
 * Traite les réceptions partielles et totales avec contrôle de conformité.
 */
class GoodsReceiptService
{
    public function __construct(private StockService $stockService)
    {
    }

    /**
     * Enregistre un bon de réception avec contrôle contradictoire (livré, accepté, refusé).
     *
     * @param  PurchaseOrder  $order
     * @param  array          $data   Contient 'delivery_note_number', 'received_at', 'notes', 'lines'
     * @param  User           $user
     * @return GoodsReceipt
     */
    public function receive(PurchaseOrder $order, array $data, User $user): GoodsReceipt
    {
        if (!$order->canBeReceived()) {
            throw new RuntimeException("Ce bon de commande ne peut pas être réceptionné dans son état actuel.");
        }

        return DB::transaction(function () use ($order, $data, $user) {
            // Verrou sur le bon : deux réceptions simultanées liraient le même
            // reste dû et pourraient, ensemble, dépasser la quantité commandée.
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (!$order->canBeReceived()) {
                throw new RuntimeException("Ce bon de commande ne peut pas être réceptionné dans son état actuel.");
            }

            $receivedAt = !empty($data['received_at']) ? Carbon::parse($data['received_at']) : now();

            $receipt = GoodsReceipt::create([
                'purchase_order_id'    => $order->id,
                'supplier_id'          => $order->supplier_id,
                'delivery_note_number' => !empty($data['delivery_note_number']) ? trim((string) $data['delivery_note_number']) : null,
                'received_at'          => $receivedAt,
                'status'               => GoodsReceipt::STATUS_RECEIVED,
                'notes'                => $data['notes'] ?? null,
                'received_by'          => $user->id,
                'receiver_signature'   => $user->signatureName(),
                'tenant_id'            => $user->tenant_id ?? \App\Models\Tenant::current()?->id,
            ]);

            $totalAcceptedAmount = 0;
            $order->load('lines.item');

            foreach ($order->lines as $line) {
                $lineInput = $data['lines'][$line->id] ?? null;
                if (!$lineInput) {
                    continue;
                }

                $delivered = max(0, (float) ($lineInput['quantity_delivered'] ?? 0));
                $rejected  = max(0, (float) ($lineInput['quantity_rejected'] ?? 0));

                if ($delivered <= 0 && $rejected <= 0) {
                    continue;
                }

                // Si la quantité acceptée n'est pas spécifiée explicitement, elle vaut delivered - rejected
                $accepted = isset($lineInput['quantity_accepted']) && $lineInput['quantity_accepted'] !== ''
                    ? max(0, (float) $lineInput['quantity_accepted'])
                    : max(0, $delivered - $rejected);

                // Ne pas recevoir plus que le restant dû sur le bon de commande
                $outstanding = $line->outstanding();
                if ($accepted > $outstanding) {
                    $accepted = $outstanding;
                }

                $unitCost = (int) $line->unit_price;
                $lineCost = (int) round($accepted * $unitCost);
                $reason   = $rejected > 0 ? ($lineInput['rejection_reason'] ?? 'non_compliant') : null;
                $notes    = $lineInput['notes'] ?? null;

                // Création de la ligne de bon de réception
                GoodsReceiptLine::create([
                    'goods_receipt_id'       => $receipt->id,
                    'purchase_order_line_id' => $line->id,
                    'stock_item_id'          => $line->stock_item_id,
                    'quantity_ordered'       => (float) $line->quantity_ordered,
                    'quantity_delivered'     => $delivered,
                    'quantity_accepted'      => $accepted,
                    'quantity_rejected'      => $rejected,
                    'rejection_reason'       => $reason,
                    'unit_cost'              => $unitCost,
                    'total_cost'             => $lineCost,
                    'notes'                  => $notes,
                ]);

                // Mise à jour de la ligne de bon de commande
                if ($accepted > 0) {
                    $line->update([
                        'quantity_received' => (float) $line->quantity_received + $accepted,
                    ]);

                    // Entrée physique en stock valorisée au CUMP
                    $refBl = $receipt->delivery_note_number ? " (BL: {$receipt->delivery_note_number})" : "";
                    $this->stockService->recordIn(
                        item: $line->item,
                        quantity: $accepted,
                        unitCost: $unitCost,
                        sourceType: StockMovement::SOURCE_GOODS_RECEIPT,
                        sourceId: $receipt->id,
                        reason: "Réception {$receipt->number}{$refBl} sur BC {$order->number}"
                    );

                    $totalAcceptedAmount += $lineCost;
                }
            }

            $receipt->update(['total_amount' => $totalAcceptedAmount]);

            $order->update(['received_by' => $user->id]);
            $order->refreshReceptionStatus();

            return $receipt->fresh(['lines.item', 'supplier', 'purchaseOrder']);
        });
    }

    /**
     * Annule un bon de réception (en cas d'erreur de saisie immédiate avant facturation).
     */
    public function cancel(GoodsReceipt $receipt, User $user): GoodsReceipt
    {
        if ($receipt->status === GoodsReceipt::STATUS_CANCELLED) {
            throw new RuntimeException("Ce bon de réception est déjà annulé.");
        }

        return DB::transaction(function () use ($receipt, $user) {
            // Verrou sur le bon d'entrée : un double clic ne doit pas sortir
            // deux fois la même marchandise du stock.
            $receipt = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->status === GoodsReceipt::STATUS_CANCELLED) {
                throw new RuntimeException("Ce bon de réception est déjà annulé.");
            }

            $receipt->load('lines.item', 'lines.purchaseOrderLine', 'purchaseOrder');

            foreach ($receipt->lines as $line) {
                if ($line->quantity_accepted > 0 && $line->item) {
                    // Annuler l'entrée en stock au coût auquel elle était entrée
                    $this->stockService->reverseIn(
                        item: $line->item,
                        quantity: (float) $line->quantity_accepted,
                        unitCost: (int) $line->unit_cost,
                        sourceType: StockMovement::SOURCE_GOODS_RECEIPT,
                        sourceId: $receipt->id,
                        reason: "Annulation réception {$receipt->number}"
                    );

                    // Déduire de la ligne de commande
                    if ($line->purchaseOrderLine) {
                        $line->purchaseOrderLine->update([
                            'quantity_received' => max(0, (float) $line->purchaseOrderLine->quantity_received - (float) $line->quantity_accepted),
                        ]);
                    }
                }
            }

            $receipt->update([
                'status' => GoodsReceipt::STATUS_CANCELLED,
            ]);

            $receipt->purchaseOrder->refreshReceptionStatus();

            return $receipt->fresh();
        });
    }
}
