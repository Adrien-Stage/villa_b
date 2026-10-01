<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service de gestion du cycle de vie des Demandes d'Achat internes.
 */
class PurchaseRequestService
{
    /**
     * Crée une nouvelle demande d'achat avec ses lignes chiffrées.
     */
    public function create(array $data, User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $user) {
            $request = PurchaseRequest::create([
                'department'   => $data['department'] ?? 'economat',
                'priority'     => $data['priority'] ?? PurchaseRequest::PRIORITY_NORMAL,
                'status'       => PurchaseRequest::STATUS_PENDING,
                'purpose'      => $data['purpose'] ?? null,
                'requested_by' => $user->id,
                'tenant_id'    => $user->tenant_id ?? \App\Models\Tenant::current()?->id,
            ]);

            $totalEstimated = 0;

            foreach ($data['lines'] as $lineData) {
                $item = StockItem::findOrFail($lineData['stock_item_id']);
                $qty = (float) $lineData['quantity_requested'];

                if ($qty <= 0) {
                    continue;
                }

                // Prix estimé : s'il est spécifié en FCFA, sinon dernier prix d'achat ou CUMP
                $unitPrice = !empty($lineData['estimated_unit_price'])
                    ? (int) round(((float) $lineData['estimated_unit_price']) * 100) // centimes
                    : ($item->last_purchase_price > 0 ? (int) $item->last_purchase_price : (int) $item->average_cost);

                $lineTotal = (int) round($qty * $unitPrice);

                PurchaseRequestLine::create([
                    'purchase_request_id'  => $request->id,
                    'stock_item_id'        => $item->id,
                    'quantity_requested'   => $qty,
                    'estimated_unit_price' => $unitPrice,
                    'notes'                => $lineData['notes'] ?? null,
                ]);

                $totalEstimated += $lineTotal;
            }

            $request->update(['total_estimated_amount' => $totalEstimated]);

            return $request->fresh('lines.item');
        });
    }

    /**
     * Valide / Approuve la demande d'achat.
     */
    public function approve(PurchaseRequest $request, User $reviewer, ?string $notes = null): PurchaseRequest
    {
        if (!$request->canBeReviewed()) {
            throw new RuntimeException("Cette demande d'achat ne peut pas être approuvée dans son statut actuel.");
        }

        $this->refuserSaPropreDemande($request, $reviewer);

        $request->update([
            'status'       => PurchaseRequest::STATUS_APPROVED,
            'reviewed_by'  => $reviewer->id,
            'reviewed_at'  => now(),
            'review_notes' => $notes,
        ]);

        return $request->fresh();
    }

    /**
     * Refuse la demande d'achat avec motif obligatoire.
     */
    public function reject(PurchaseRequest $request, User $reviewer, string $reason): PurchaseRequest
    {
        if (!$request->canBeReviewed()) {
            throw new RuntimeException("Cette demande d'achat ne peut pas être refusée dans son statut actuel.");
        }

        $this->refuserSaPropreDemande($request, $reviewer);

        $request->update([
            'status'           => PurchaseRequest::STATUS_REJECTED,
            'reviewed_by'      => $reviewer->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $request->fresh();
    }

    /**
     * Le demandeur ne décide pas de sa propre demande : approuver la dépense
     * qu'on a soi-même demandée, c'est l'autoriser sans contrôle. Vaut aussi
     * pour le refus, qui reviendrait à la retirer sans trace de décision.
     */
    public static function estSaPropreDemande(PurchaseRequest $request, User $reviewer): bool
    {
        return (int) $request->requested_by === (int) $reviewer->id;
    }

    private function refuserSaPropreDemande(PurchaseRequest $request, User $reviewer): void
    {
        if (self::estSaPropreDemande($request, $reviewer)) {
            throw new RuntimeException("Vous ne pouvez pas décider de votre propre demande d'achat : un autre responsable doit l'examiner.");
        }
    }

    /**
     * Annule la demande d'achat par son demandeur ou l'économe.
     */
    public function cancel(PurchaseRequest $request, User $user): PurchaseRequest
    {
        if (!$request->canBeCancelled()) {
            throw new RuntimeException("Cette demande d'achat ne peut plus être annulée.");
        }

        $request->update([
            'status' => PurchaseRequest::STATUS_CANCELLED,
        ]);

        return $request->fresh();
    }

    /**
     * Convertit une demande d'achat approuvée en bon(s) de commande fournisseur.
     * Groupe les articles par leur fournisseur habituel (ou utilise le fournisseur spécifié).
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function convertToOrders(PurchaseRequest $request, User $user, ?int $supplierId = null): Collection
    {
        if (!$request->canBeConverted()) {
            throw new RuntimeException("Seule une demande d'achat approuvée peut être convertie en bon de commande.");
        }

        return DB::transaction(function () use ($request, $user, $supplierId) {
            $request->loadMissing('lines.item.supplier');
            $createdOrders = collect();

            // Si un fournisseur précis a été imposé pour tous les articles
            if ($supplierId) {
                $supplier = Supplier::findOrFail($supplierId);
                $order = $this->createOrderFromLines($request, $supplier, $request->lines, $user);
                $createdOrders->push($order);
            } else {
                // Groupement automatique par fournisseur habituel de l'article
                $linesBySupplier = $request->lines->groupBy(fn (PurchaseRequestLine $l) => $l->item?->supplier_id ?? 0);

                foreach ($linesBySupplier as $supId => $lines) {
                    $supplier = $supId > 0 ? Supplier::find($supId) : null;
                    if (!$supplier) {
                        // S'il n'y a pas de fournisseur habituel, prendre le premier fournisseur actif ou fournisseur par défaut
                        $supplier = Supplier::active()->first();
                    }

                    if (!$supplier) {
                        throw new RuntimeException("Aucun fournisseur disponible pour convertir cette commande.");
                    }

                    $order = $this->createOrderFromLines($request, $supplier, $lines, $user);
                    $createdOrders->push($order);
                }
            }

            $request->update([
                'status' => PurchaseRequest::STATUS_CONVERTED,
            ]);

            return $createdOrders;
        });
    }

    /**
     * Crée un PurchaseOrder pour un fournisseur et un lot de lignes.
     */
    private function createOrderFromLines(PurchaseRequest $request, Supplier $supplier, Collection $lines, User $user): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'supplier_id'         => $supplier->id,
            'purchase_request_id' => $request->id,
            'status'              => PurchaseOrder::STATUS_DRAFT,
            'notes'               => "Généré automatiquement depuis la demande d'achat {$request->number} (" . $request->departmentLabel() . ")",
            'created_by'          => $user->id,
            'tenant_id'           => $user->tenant_id ?? \App\Models\Tenant::current()?->id,
        ]);

        foreach ($lines as $reqLine) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'stock_item_id'     => $reqLine->stock_item_id,
                'quantity_ordered'  => $reqLine->quantity_requested,
                'unit_price'        => $reqLine->estimated_unit_price, // en centimes
            ]);
        }

        $order->recalculateTotal();

        return $order;
    }
}
