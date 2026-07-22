<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\StockRequisition;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'une demande d'un département à l'économat : validation par l'économe,
 * puis livraison qui déstocke réellement les articles.
 *
 * La séparation validation / livraison est volontaire : l'économe peut
 * approuver le principe, puis servir plus tard, et ajuster à la livraison les
 * quantités réellement disponibles.
 */
class StockRequisitionService
{
    public function __construct(private StockService $stock)
    {
    }

    public function approve(StockRequisition $requisition, ?string $notes = null): void
    {
        if (!$requisition->canBeReviewed()) {
            throw new \RuntimeException('Cette demande a déjà été traitée.');
        }

        $requisition->update([
            'status'       => StockRequisition::STATUS_APPROVED,
            'review_notes' => $notes,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
        ]);
    }

    public function reject(StockRequisition $requisition, ?string $notes = null): void
    {
        if (!$requisition->canBeReviewed()) {
            throw new \RuntimeException('Cette demande a déjà été traitée.');
        }

        $requisition->update([
            'status'       => StockRequisition::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
        ]);
    }

    /**
     * Livraison : sort du stock les quantités réellement servies et clôt la
     * demande. $issued associe l'id de ligne à la quantité servie ; en son
     * absence, on sert la quantité demandée.
     *
     * @param  array<int, float>  $issued  [line_id => quantité servie]
     */
    public function deliver(StockRequisition $requisition, array $issued = []): void
    {
        if (!$requisition->canBeDelivered()) {
            throw new \RuntimeException('La demande doit être validée avant d\'être livrée.');
        }

        DB::transaction(function () use ($requisition, $issued) {
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

                $this->stock->recordOut(
                    $line->item,
                    $qty,
                    StockMovement::SOURCE_REQUISITION,
                    $requisition->id,
                    "Demande {$requisition->number} — {$requisition->departmentLabel()}"
                );

                $line->update(['quantity_issued' => $qty]);
            }

            $requisition->update([
                'status'       => StockRequisition::STATUS_DELIVERED,
                'delivered_at' => now(),
            ]);
        });
    }
}
