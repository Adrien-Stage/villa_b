<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
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
     * Réception directe : la marchandise est arrivée sans bon de commande
     * (achat au comptant, livraison imprévue, urgence).
     *
     * Un bon de régularisation est établi pour ce qui est gardé, puis
     * réceptionné par le circuit ordinaire. Le stock, le coût moyen, le
     * rapprochement de la facture fournisseur et l'annulation fonctionnent
     * donc comme pour toute réception. Le fournisseur et les articles
     * absents du magasin se créent au passage : une application encore vide
     * ne doit pas empêcher d'enregistrer ce qui vient d'arriver.
     *
     * @param  array  $data  supplier_id ou nouveau_fournisseur, motif, delivery_note_number,
     *                       received_at, notes, et lines : stock_item_id ou nouvel_article,
     *                       quantity_delivered, quantity_rejected, rejection_reason, unit_price (FCFA), notes
     */
    public function receiveDirect(array $data, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $user) {
            $tenantId = $user->tenant_id ?? Tenant::current()?->id;
            $supplier = $this->fournisseur($data, $tenantId);
            $motif    = PurchaseOrder::MOTIFS_REGULARISATION[$data['motif'] ?? ''] ?? null;

            $order = PurchaseOrder::create([
                'supplier_id'      => $supplier->id,
                // « Envoyé » le temps d'être réceptionné juste après : la
                // réception le soldera.
                'status'           => PurchaseOrder::STATUS_SENT,
                'sent_at'          => now(),
                'transmission'     => PurchaseOrder::TRANSMISSION_REGULARISATION,
                'notes'            => 'Régularisation d\'une réception directe' . ($motif ? " — {$motif}" : '') . '.',
                'created_by'       => $user->id,
                'issuer_signature' => $user->signatureName(),
                'tenant_id'        => $tenantId,
            ]);

            $pointage = [];
            $nouveaux = [];

            foreach ($data['lines'] as $ligne) {
                $livre   = max(0, (float) ($ligne['quantity_delivered'] ?? 0));
                $refuse  = max(0, (float) ($ligne['quantity_rejected'] ?? 0));
                // Le bon de régularisation porte ce qui est gardé : il sera
                // soldé par cette réception, et la facture se rapproche de lui.
                $accepte = round($livre - $refuse, 3);

                if ($accepte <= 0) {
                    $nom = $ligne['nouvel_article']['name'] ?? StockItem::find($ligne['stock_item_id'] ?? null)?->name ?? 'Un article';
                    throw new RuntimeException(
                        "« {$nom} » : rien n'est gardé. Une marchandise refusée en entier repart avec le livreur, sans bon d'entrée."
                    );
                }

                $item = $this->article($ligne, $supplier, $tenantId, $nouveaux);

                $line = PurchaseOrderLine::create([
                    'purchase_order_id' => $order->id,
                    'stock_item_id'     => $item->id,
                    'quantity_ordered'  => $accepte,
                    'unit_price'        => (int) round((float) $ligne['unit_price'] * 100),
                ]);

                $pointage[$line->id] = [
                    'quantity_delivered' => $livre,
                    'quantity_accepted'  => $accepte,
                    'quantity_rejected'  => $refuse,
                    'rejection_reason'   => $ligne['rejection_reason'] ?? null,
                    'notes'              => $ligne['notes'] ?? null,
                ];
            }

            $order->recalculateTotal();

            return $this->receive($order, [
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'received_at'          => $data['received_at'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'lines'                => $pointage,
            ], $user);
        });
    }

    private function fournisseur(array $data, ?int $tenantId): Supplier
    {
        if (!empty($data['supplier_id'])) {
            return Supplier::findOrFail($data['supplier_id']);
        }

        $nom = trim((string) ($data['nouveau_fournisseur']['name'] ?? ''));
        if ($nom === '') {
            throw new RuntimeException('Choisissez le fournisseur, ou donnez le nom du nouveau.');
        }

        if (Supplier::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])->exists()) {
            throw new RuntimeException("Le fournisseur « {$nom} » existe déjà : choisissez-le dans la liste.");
        }

        return Supplier::create([
            'name'      => $nom,
            'phone'     => trim((string) ($data['nouveau_fournisseur']['phone'] ?? '')) ?: null,
            'is_active' => true,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * L'article de la ligne, créé s'il n'existe pas encore. Deux lignes qui
     * nomment le même nouvel article le partagent.
     *
     * @param  array<string, StockItem>  $nouveaux
     */
    private function article(array $ligne, Supplier $supplier, ?int $tenantId, array &$nouveaux): StockItem
    {
        if (!empty($ligne['stock_item_id'])) {
            $item = StockItem::findOrFail($ligne['stock_item_id']);

            // Comme à la commande : l'article sans fournisseur habituel
            // prend celui qui vient de le livrer.
            if (empty($item->supplier_id)) {
                $item->update(['supplier_id' => $supplier->id]);
            }

            return $item;
        }

        $nom = trim((string) ($ligne['nouvel_article']['name'] ?? ''));
        if ($nom === '') {
            throw new RuntimeException("Chaque ligne nomme un article : choisissez-le, ou donnez le nom du nouveau.");
        }

        $cle = mb_strtolower($nom);
        if (isset($nouveaux[$cle])) {
            return $nouveaux[$cle];
        }

        if (StockItem::query()->whereRaw('LOWER(name) = ?', [$cle])->exists()) {
            throw new RuntimeException("L'article « {$nom} » existe déjà : choisissez-le dans la liste.");
        }

        return $nouveaux[$cle] = StockItem::create([
            'name'              => $nom,
            'unit'              => trim((string) ($ligne['nouvel_article']['unit'] ?? '')) ?: 'pièce',
            'stock_category_id' => $ligne['nouvel_article']['stock_category_id'] ?? null,
            'supplier_id'       => $supplier->id,
            // Le stock et le coût moyen naissent de la réception qui suit.
            'current_stock'     => 0,
            'average_cost'      => 0,
            'min_stock'         => 0,
            'is_active'         => true,
            'tenant_id'         => $tenantId,
        ]);
    }

    /**
     * Annule un bon de réception (en cas d'erreur de saisie immédiate avant facturation).
     * Refusé dès que la facturation du bon dépasserait ce qui resterait reçu.
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

            // Même verrou que la saisie d'une facture sur ce bon : l'annulation
            // et la facturation ne peuvent pas se croiser.
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($receipt->purchase_order_id);
            $factureSurLeBon = $order->invoicedAmount();

            if ($factureSurLeBon > $order->receivedAmount() - (int) $receipt->total_amount) {
                throw new RuntimeException(
                    "Le bon {$order->number} est déjà facturé pour "
                    . number_format($factureSurLeBon / 100, 0, ',', ' ') . ' FCFA : annuler cette réception '
                    . 'laisserait une facture sans marchandise reçue. Obtenez d\'abord un avoir du fournisseur.'
                );
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

            // Un bon de régularisation n'attend aucune livraison : sa
            // réception annulée, il l'est aussi.
            if ($receipt->purchaseOrder->isRegularisation()
                && $receipt->purchaseOrder->status === PurchaseOrder::STATUS_SENT) {
                $receipt->purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
            }

            return $receipt->fresh();
        });
    }
}
