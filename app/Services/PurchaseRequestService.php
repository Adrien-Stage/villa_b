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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Service de gestion du cycle de vie des Demandes d'Achat internes.
 *
 * Le circuit : l'économat (ou un service) exprime le besoin ; la demande
 * part au responsable des achats, qui la vérifie et peut en modifier les
 * quantités — le demandeur est averti de chaque changement ; il en tire les
 * bons de commande et les transmet aux fournisseurs, ce qui ouvre les bons de
 * réception en attente que l'économat remplira à la livraison. Le contrôle de
 * gestion et le responsable financier suivent tout le circuit.
 */
class PurchaseRequestService
{
    /** Ceux qui vérifient les demandes et commandent. */
    public const ACHETEURS = ['purchasing_manager'];

    /** Qui vérifie la demande du responsable des achats lui-même. */
    public const SUPERIEUR_DES_ACHATS = ['finance_manager'];

    public function __construct(private Notifier $notifier)
    {
    }

    /**
     * La demande nouvelle part au responsable des achats. La sienne propre,
     * il ne la vérifie pas lui-même : elle part aussi au responsable
     * financier, qui l'encadre.
     */
    public function annoncer(PurchaseRequest $request): void
    {
        $notification = new PurchaseRequestUpdated($request, PurchaseRequestUpdated::SOUMISE);
        $this->notifier->toRoles(self::ACHETEURS, $notification, $request->requested_by);

        if ($request->requestedBy?->exerce(self::ACHETEURS)) {
            $this->notifier->toRoles(self::SUPERIEUR_DES_ACHATS, $notification, $request->requested_by);
        }
    }

    /** Le demandeur apprend la décision et ses ajustements ; le dossier approuvé passe aux achats. */
    private function annoncerDecision(PurchaseRequest $request, User $decideur): void
    {
        $request->loadMissing(['lines.item', 'requestedBy', 'reviewedBy']);
        $etape = $request->status === PurchaseRequest::STATUS_APPROVED
            ? PurchaseRequestUpdated::APPROUVEE
            : PurchaseRequestUpdated::REFUSEE;

        if ($request->requestedBy && (int) $request->requestedBy->id !== (int) $decideur->id) {
            $this->notifier->send($request->requestedBy, new PurchaseRequestUpdated($request, $etape));
        }

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
                'supplier_id'  => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
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

                $unitPrice = $this->prixEstime($item, $request->supplier_id, $lineData['estimated_unit_price'] ?? null);

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

        $request->loadMissing('lines.item');
        $avant = $request->lines->mapWithKeys(fn (PurchaseRequestLine $l) => [$l->stock_item_id => [
            'nom' => $l->item?->name ?? 'Article', 'unite' => $l->item?->unit, 'quantite' => $l->quantiteRetenue(),
        ]])->all();

        $request = DB::transaction(function () use ($request, $data) {
            $supplierId = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : $request->supplier_id;
            $request->update([
                'supplier_id' => $supplierId,
                'department'  => $data['department'] ?? $request->department,
                'priority'    => $data['priority'] ?? $request->priority,
                'purpose'     => $data['purpose'] ?? $request->purpose,
            ]);

            $totalEstimated = 0;
            $keptLineIds = [];

            foreach ($data['lines'] as $lineData) {
                $item = StockItem::findOrFail($lineData['stock_item_id']);
                $qty = (float) $lineData['quantity_requested'];

                if ($qty <= 0) {
                    continue;
                }

                $unitPrice = $this->prixEstime($item, $request->supplier_id, $lineData['estimated_unit_price'] ?? null);

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

        // Le demandeur apprend ce qui a changé dans sa demande.
        $changements = $this->changements($avant, $request);
        if ($changements !== '' && $request->requestedBy && (int) $request->requestedBy->id !== (int) $user->id) {
            $this->notifier->send($request->requestedBy, new PurchaseRequestUpdated($request, PurchaseRequestUpdated::MODIFIEE, $changements, $user->name));
        }

        return $request;
    }

    /**
     * « Riz 50 → 40 kg ; Huile : article retiré ; Sucre : article ajouté (10 kg) » : les lignes
     * qu'une modification a changées.
     *
     * @param  array<int, array{nom: string, unite: ?string, quantite: float}>  $avant
     */
    private function changements(array $avant, PurchaseRequest $request): string
    {
        $qte = fn (float $v) => rtrim(rtrim(number_format($v, 3, ',', ' '), '0'), ',');
        $apres = $request->lines->mapWithKeys(fn (PurchaseRequestLine $l) => [$l->stock_item_id => [
            'nom' => $l->item?->name ?? 'Article', 'unite' => $l->item?->unit, 'quantite' => $l->quantiteRetenue(),
        ]])->all();

        $changements = [];
        foreach ($avant as $article => $ligne) {
            if (! isset($apres[$article])) {
                $changements[] = "{$ligne['nom']} : article retiré";
            } elseif (abs($apres[$article]['quantite'] - $ligne['quantite']) > 0.0005) {
                $changements[] = "{$ligne['nom']} {$qte($ligne['quantite'])} → {$qte($apres[$article]['quantite'])}" . ($ligne['unite'] ? " {$ligne['unite']}" : '');
            }
        }
        foreach ($apres as $article => $ligne) {
            if (! isset($avant[$article])) {
                $changements[] = "{$ligne['nom']} : article ajouté ({$qte($ligne['quantite'])}" . ($ligne['unite'] ? " {$ligne['unite']}" : '') . ')';
            }
        }

        return implode(' ; ', $changements);
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

    /**
     * Prix d'une ligne de demande, en centimes : le tarif du fournisseur de la
     * demande. Un article absent de son catalogue prend le prix saisi — la
     * demande le signale, et rien n'est ajouté au catalogue en silence —,
     * sinon son dernier prix payé, sinon son coût moyen.
     */
    private function prixEstime(StockItem $item, ?int $supplierId, mixed $saisi): int
    {
        if ($supplierId && ($tarif = $item->supplierPriceFor($supplierId)) !== null) {
            return $tarif;
        }

        if (is_numeric($saisi) && (float) $saisi > 0) {
            return (int) round((float) $saisi * 100);
        }

        return (int) $item->last_purchase_price > 0 ? (int) $item->last_purchase_price : (int) $item->average_cost;
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

            $supplierId = $supplierId ?: $request->supplier_id;

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
                // Le prix du fournisseur qui reçoit le bon, sinon celui de la demande.
                'unit_price'        => $reqLine->item?->supplierPriceFor($supplier) ?? $reqLine->estimated_unit_price, // en centimes
            ]);
        }

        $order->recalculateTotal();

        return $order;
    }
}
