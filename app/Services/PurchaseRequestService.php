<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseRequestUpdated;
use App\Support\RoleCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service de gestion du cycle de vie des Demandes d'Achat internes.
 *
 * Le circuit : un service ou l'économat demande ; le contrôleur de gestion
 * — ou le responsable financier à sa place — approuve, en ajustant au besoin
 * les quantités, ou refuse ; le responsable des achats tire les bons de
 * commande des quantités approuvées et les envoie. Chaque étape est annoncée
 * à qui doit agir ensuite.
 */
class PurchaseRequestService
{
    /** Ceux qui décident de la dépense. */
    public const APPROBATEURS = ['controller', 'finance_manager'];

    /** Ceux qui commandent. */
    public const ACHETEURS = ['purchasing_manager'];

    /** L'économat, qui reçoit ce qui est commandé et suit chaque décision. */
    public const ECONOMAT = ['econome'];

    public function __construct(private Notifier $notifier)
    {
    }

    /** La demande nouvelle part aux approbateurs. */
    public function annoncer(PurchaseRequest $request): void
    {
        $this->notifier->toRoles(self::APPROBATEURS, new PurchaseRequestUpdated($request, PurchaseRequestUpdated::SOUMISE), $request->requested_by);
    }

    /** Le demandeur et l'économat apprennent la décision ; le dossier approuvé passe aux achats. */
    private function annoncerDecision(PurchaseRequest $request, User $decideur): void
    {
        $request->loadMissing(['lines.item', 'requestedBy', 'reviewedBy']);
        $etape = $request->status === PurchaseRequest::STATUS_APPROVED
            ? PurchaseRequestUpdated::APPROUVEE
            : PurchaseRequestUpdated::REFUSEE;

        $economat = User::query()
            ->havingRole(RoleCatalog::avecCeuxQuiLesIncluent(self::ECONOMAT))
            ->active()
            ->where('id', '!=', $decideur->id)
            ->get();
        $this->notifier->send($economat->push($request->requestedBy)->filter(), new PurchaseRequestUpdated($request, $etape));

        if ($etape === PurchaseRequestUpdated::APPROUVEE) {
            $this->notifier->toRoles(self::ACHETEURS, new PurchaseRequestUpdated($request, PurchaseRequestUpdated::A_COMMANDER), $decideur->id);
        }
    }

    /**
     * Crée une nouvelle demande d'achat avec ses lignes chiffrées.
     */
    public function create(array $data, User $user): PurchaseRequest
    {
        $request = DB::transaction(function () use ($data, $user) {
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

        $this->annoncer($request);

        return $request;
    }

    /**
     * Met à jour une demande d'achat (notamment quantités, articles, motif, priorité).
     */
    public function update(PurchaseRequest $request, array $data, User $user): PurchaseRequest
    {
        if (!$request->canBeEdited()) {
            throw new RuntimeException("Cette demande d'achat ne peut plus être modifiée dans son statut actuel.");
        }

        return DB::transaction(function () use ($request, $data) {
            $request->update([
                'department' => $data['department'] ?? $request->department,
                'priority'   => $data['priority'] ?? $request->priority,
                'purpose'    => $data['purpose'] ?? $request->purpose,
            ]);

            $totalEstimated = 0;
            $keptLineIds = [];

            foreach ($data['lines'] as $lineData) {
                $item = StockItem::findOrFail($lineData['stock_item_id']);
                $qty = (float) $lineData['quantity_requested'];

                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = !empty($lineData['estimated_unit_price'])
                    ? (int) round(((float) $lineData['estimated_unit_price']) * 100)
                    : ($item->last_purchase_price > 0 ? (int) $item->last_purchase_price : (int) $item->average_cost);

                $lineTotal = (int) round($qty * $unitPrice);

                $lineAttributes = [
                    'stock_item_id'        => $item->id,
                    'quantity_requested'   => $qty,
                    'estimated_unit_price' => $unitPrice,
                    'notes'                => $lineData['notes'] ?? null,
                ];

                if ($request->isApproved()) {
                    $lineAttributes['quantity_approved'] = $qty;
                }

                if (!empty($lineData['id'])) {
                    $line = $request->lines()->where('id', $lineData['id'])->first();
                    if ($line) {
                        $line->update($lineAttributes);
                        $keptLineIds[] = $line->id;
                    } else {
                        $newLine = $request->lines()->create($lineAttributes);
                        $keptLineIds[] = $newLine->id;
                    }
                } else {
                    $newLine = $request->lines()->create($lineAttributes);
                    $keptLineIds[] = $newLine->id;
                }

                $totalEstimated += $lineTotal;
            }

            // Supprimer les lignes non conservées
            $request->lines()->whereNotIn('id', $keptLineIds)->delete();

            $request->update(['total_estimated_amount' => $totalEstimated]);

            return $request->fresh(['lines.item', 'requestedBy', 'reviewedBy']);
        });
    }

    /**
     * Approuve la demande d'achat.
     *
     * L'approbateur peut ajuster la quantité de chaque ligne ($quantites,
     * par identifiant de ligne) ; zéro écarte l'article. La quantité demandée
     * reste écrite à côté : l'économat voit ce qui a changé.
     *
     * @param  array<int|string, int|float|string|null>  $quantites
     */
    public function approve(PurchaseRequest $request, User $reviewer, ?string $notes = null, array $quantites = []): PurchaseRequest
    {
        if (!$request->canBeReviewed()) {
            throw new RuntimeException("Cette demande d'achat ne peut pas être approuvée dans son statut actuel.");
        }

        $this->refuserSaPropreDemande($request, $reviewer);

        DB::transaction(function () use ($request, $reviewer, $notes, $quantites) {
            $retenu = 0.0;

            foreach ($request->lines()->get() as $ligne) {
                $saisie = $quantites[$ligne->id] ?? null;
                $quantite = ($saisie === null || $saisie === '') ? (float) $ligne->quantity_requested : (float) $saisie;

                if ($quantite < 0) {
                    throw new RuntimeException('Une quantité approuvée ne peut pas être négative.');
                }

                $ligne->update(['quantity_approved' => $quantite]);
                $retenu += $quantite;
            }

            if ($retenu <= 0) {
                throw new RuntimeException("Toutes les quantités sont à zéro : refusez plutôt la demande, avec son motif.");
            }

            $request->update([
                'status'       => PurchaseRequest::STATUS_APPROVED,
                'reviewed_by'  => $reviewer->id,
                'reviewed_at'  => now(),
                'review_notes' => $notes,
            ]);
            $request->recalculateTotal();
        });

        $request = $request->fresh(['lines.item', 'requestedBy', 'reviewedBy']);
        $this->annoncerDecision($request, $reviewer);

        return $request;
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

        $request = $request->fresh(['lines.item', 'requestedBy', 'reviewedBy']);
        $this->annoncerDecision($request, $reviewer);

        return $request;
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
     * Convertit une demande d'achat approuvée en bon(s) de commande fournisseur,
     * aux quantités approuvées ; un article ramené à zéro n'est pas commandé.
     * Groupe les articles par fournisseur (soit par ligne, soit fournisseur global, soit fournisseur habituel).
     *
     * @param  array<int|string, int|string>  $lineSuppliers
     * @return Collection<int, PurchaseOrder>
     */
    public function convertToOrders(PurchaseRequest $request, User $user, ?int $supplierId = null, array $lineSuppliers = []): Collection
    {
        if (!$request->canBeConverted()) {
            throw new RuntimeException("Seule une demande d'achat approuvée peut être convertie en bon de commande.");
        }

        return DB::transaction(function () use ($request, $user, $supplierId, $lineSuppliers) {
            $request->loadMissing('lines.item.supplier');
            $createdOrders = collect();
            $aCommander = $request->lines->filter(fn (PurchaseRequestLine $l) => $l->quantiteRetenue() > 0);

            if ($aCommander->isEmpty()) {
                throw new RuntimeException("Aucun article avec une quantité positive à commander.");
            }

            // Si des fournisseurs précis par ligne sont spécifiés
            if (!empty($lineSuppliers)) {
                $linesBySupplier = $aCommander->groupBy(function (PurchaseRequestLine $l) use ($lineSuppliers, $supplierId) {
                    if (!empty($lineSuppliers[$l->id])) {
                        return (int) $lineSuppliers[$l->id];
                    }
                    if ($supplierId) {
                        return (int) $supplierId;
                    }
                    return (int) ($l->item?->supplier_id ?? 0);
                });
            } elseif ($supplierId) {
                // Si un fournisseur précis a été imposé pour tous les articles
                $linesBySupplier = collect([(int) $supplierId => $aCommander]);
            } else {
                // Groupement automatique par fournisseur habituel de l'article
                $linesBySupplier = $aCommander->groupBy(fn (PurchaseRequestLine $l) => (int) ($l->item?->supplier_id ?? 0));
            }

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
            // Le responsable des achats signe le bon qu'il émet.
            'issuer_signature'    => $user->signatureName(),
            'tenant_id'           => $user->tenant_id ?? \App\Models\Tenant::current()?->id,
        ]);

        foreach ($lines as $reqLine) {
            PurchaseOrderLine::create([
                'purchase_order_id' => $order->id,
                'stock_item_id'     => $reqLine->stock_item_id,
                'quantity_ordered'  => $reqLine->quantiteRetenue(),
                'unit_price'        => $reqLine->estimated_unit_price, // en centimes
            ]);
        }

        $order->recalculateTotal();

        return $order;
    }
}
