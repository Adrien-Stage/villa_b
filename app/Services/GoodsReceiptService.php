<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockUnit;
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
    public function __construct(
        private StockService $stockService,
        private SupplierTariffService $tarifs,
    ) {
    }

    /**
     * Joint les bordereaux de livraison au bon : rangés hors de l'espace
     * public (disque local), un dossier par bon.
     *
     * @param  list<\Illuminate\Http\UploadedFile>  $fichiers
     * @return int  bordereaux joints
     */
    public function joindre(GoodsReceipt $receipt, array $fichiers, User $user): int
    {
        $joints = 0;
        foreach ($fichiers as $fichier) {
            if (! $fichier instanceof \Illuminate\Http\UploadedFile || ! $fichier->isValid()) {
                continue;
            }
            $receipt->attachments()->create([
                'disk'          => 'local',
                'path'          => $fichier->store("bordereaux/{$receipt->id}", 'local'),
                'original_name' => mb_substr($fichier->getClientOriginalName() ?: 'bordereau', 0, 255),
                'mime_type'     => $fichier->getMimeType(),
                'size'          => (int) $fichier->getSize(),
                'uploaded_by'   => $user->id,
            ]);
            $joints++;
        }

        if ($joints > 0) {
            \App\Models\AuditLog::record($user->id, 'bordereau_joint',
                "{$joints} bordereau(x) de livraison joint(s) au bon {$receipt->number}", 'economat',
                ['goods_receipt_id' => $receipt->id]);
        }

        return $joints;
    }

    /** Le bon de réception qui attend la livraison de ce bon de commande, s'il y en a un. */
    public function attenteDe(PurchaseOrder $order): ?GoodsReceipt
    {
        return GoodsReceipt::query()->enAttente()->where('purchase_order_id', $order->id)->latest('id')->first();
    }

    /**
     * Ouvre — ou remet à jour — le bon de réception en attente d'un bon de
     * commande parti chez le fournisseur : une ligne par article encore dû,
     * avec la quantité attendue. Un bon qui n'attend plus rien (soldé, annulé)
     * voit son bon en attente annulé plutôt qu'effacé : la numérotation des
     * bons de réception reste continue.
     */
    public function ouvrirAttente(PurchaseOrder $order): ?GoodsReceipt
    {
        return DB::transaction(function () use ($order) {
            $order = PurchaseOrder::query()->lockForUpdate()->with('lines')->findOrFail($order->id);
            $attente = $this->attenteDe($order);
            $attendues = $order->canBeReceived() && !$order->isRegularisation()
                ? $order->lines->filter(fn ($l) => $l->outstanding() > 0)
                : collect();

            if ($attendues->isEmpty()) {
                $attente?->update([
                    'status' => GoodsReceipt::STATUS_CANCELLED,
                    'notes'  => 'Sans objet : le bon de commande n\'attend plus de livraison.',
                ]);

                return null;
            }

            $attente ??= GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                'supplier_id'       => $order->supplier_id,
                'status'            => GoodsReceipt::STATUS_PENDING,
                'total_amount'      => 0,
                'tenant_id'         => $order->tenant_id ?? \App\Models\Tenant::current()?->id,
            ]);

            $attente->lines()->delete();
            foreach ($attendues as $ligne) {
                GoodsReceiptLine::create([
                    'goods_receipt_id'       => $attente->id,
                    'purchase_order_line_id' => $ligne->id,
                    'stock_item_id'          => $ligne->stock_item_id,
                    'quantity_ordered'       => $ligne->outstanding(),
                    'quantity_delivered'     => 0,
                    'quantity_accepted'      => 0,
                    'quantity_rejected'      => 0,
                    'unit_cost'              => (int) $ligne->unit_price,
                    'total_cost'             => 0,
                ]);
            }

            return $attente->fresh('lines');
        });
    }

    /**
     * Enregistre un bon de réception avec contrôle contradictoire (livré, accepté, refusé).
     *
     * Le bon en attente du bon de commande est rempli ; à défaut (bon envoyé
     * avant les bons en attente, régularisation), un bon est créé. L'économat
     * dit si la livraison est partielle — le reste reste attendu, dans un
     * nouveau bon en attente — ou complète : le bon de commande est soldé,
     * même si des quantités manquent.
     *
     * @param  PurchaseOrder  $order
     * @param  array          $data   Contient 'delivery_note_number', 'received_at', 'notes', 'lines', 'livraison'
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

            $entete = [
                'purchase_order_id'    => $order->id,
                'supplier_id'          => $order->supplier_id,
                'delivery_note_number' => !empty($data['delivery_note_number']) ? trim((string) $data['delivery_note_number']) : null,
                'received_at'          => $receivedAt,
                'status'               => GoodsReceipt::STATUS_RECEIVED,
                'notes'                => $data['notes'] ?? null,
                'received_by'          => $user->id,
                'receiver_signature'   => $user->signatureName(),
            ];

            // Le bon en attente garde son numéro : c'est lui qu'on remplit.
            $receipt = $this->attenteDe($order);
            if ($receipt) {
                $receipt->lines()->delete();
                $receipt->update($entete);
            } else {
                $receipt = GoodsReceipt::create($entete + [
                    'tenant_id' => $user->tenant_id ?? \App\Models\Tenant::current()?->id,
                ]);
            }

            $totalAcceptedAmount = 0;
            $order->load('lines.item.packagings');

            foreach ($order->lines as $line) {
                $lineInput = $data['lines'][$line->id] ?? null;
                if (!$lineInput) {
                    continue;
                }

                // Saisi en cartons ou en paquets : on ramène à l'unité de l'article.
                $conditionnement = $this->conditionnementSaisi($line->item, $lineInput['packaging'] ?? null);
                $facteur = $conditionnement !== null ? $line->item->facteurDe($conditionnement) : 1.0;

                $delivered = round(max(0, (float) ($lineInput['quantity_delivered'] ?? 0)) * $facteur, 3);
                $rejected  = round(max(0, (float) ($lineInput['quantity_rejected'] ?? 0)) * $facteur, 3);

                if ($delivered <= 0 && $rejected <= 0) {
                    continue;
                }

                // Si la quantité acceptée n'est pas spécifiée explicitement, elle vaut delivered - rejected
                $accepted = isset($lineInput['quantity_accepted']) && $lineInput['quantity_accepted'] !== ''
                    ? round(max(0, (float) $lineInput['quantity_accepted']) * $facteur, 3)
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
                    'packaging_name'         => $conditionnement,
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
                        reason: "Réception {$receipt->number}{$refBl} sur BC {$order->number}",
                        // Des cartons reçus entiers entrent comme cartons fermés.
                        niveau: $conditionnement
                    );

                    // Le prix payé devient le tarif de ce fournisseur, à la date
                    // de la livraison — et l'article sans fournisseur habituel
                    // prend celui qui vient de le livrer.
                    if ($unitCost > 0) {
                        $this->tarifs->fixer($line->item, (int) $order->supplier_id, $unitCost, $receivedAt);
                    }

                    $totalAcceptedAmount += $lineCost;
                }
            }

            if ($receipt->lines()->doesntExist()) {
                throw new RuntimeException('Aucune quantité livrée : saisissez ce qui est réellement arrivé.');
            }

            $order->update(['received_by' => $user->id]);
            $order->refreshReceptionStatus();

            // Partielle ou complète : l'économat le dit ; sans réponse, la
            // livraison est complète quand plus rien n'est dû.
            $resteDu = $order->lines()->get()->contains(fn ($l) => $l->outstanding() > 0);
            $livraison = $data['livraison'] ?? ($resteDu ? GoodsReceipt::DELIVERY_PARTIAL : GoodsReceipt::DELIVERY_COMPLETE);
            $receipt->update(['total_amount' => $totalAcceptedAmount, 'delivery_type' => $livraison]);

            if ($livraison === GoodsReceipt::DELIVERY_COMPLETE && $resteDu) {
                // Livraison déclarée complète : le bon est soldé, le manquant
                // n'est plus attendu.
                $order->update(['status' => PurchaseOrder::STATUS_RECEIVED, 'received_at' => $order->received_at ?? now()]);
            }

            // Le reste d'une livraison partielle est attendu sous un nouveau bon.
            $this->ouvrirAttente($order);

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

                // Reçu en cartons à 25 000 F le carton : le bon de régularisation
                // compte en unités de l'article, au prix de l'unité.
                $conditionnement = $this->conditionnementSaisi($item->loadMissing('packagings'), $ligne['packaging'] ?? null);
                $facteur = $conditionnement !== null ? $item->facteurDe($conditionnement) : 1.0;

                $line = PurchaseOrderLine::create([
                    'purchase_order_id' => $order->id,
                    'stock_item_id'     => $item->id,
                    'quantity_ordered'  => round($accepte * $facteur, 3),
                    'unit_price'        => (int) round((float) $ligne['unit_price'] * 100 / $facteur),
                ]);

                $pointage[$line->id] = [
                    'quantity_delivered' => $livre,
                    'quantity_accepted'  => $accepte,
                    'quantity_rejected'  => $refuse,
                    'packaging'          => $conditionnement,
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
            // Son tarif chez ce fournisseur — et, s'il n'en avait pas, son
            // fournisseur habituel — naissent de la réception qui suit.
            return StockItem::findOrFail($ligne['stock_item_id']);
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
            'unit'              => StockUnit::symbolFor($ligne['nouvel_article']['unit'] ?? 'pcs'),
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
        if ($receipt->estEnAttente()) {
            throw new RuntimeException("Ce bon attend encore sa livraison : il se clôt avec son bon de commande.");
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
                        reason: "Annulation réception {$receipt->number}",
                        // Reçus en cartons : ce sont des cartons fermés qui repartent.
                        niveau: $line->packaging_name
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

            // Ce qui avait été reçu est de nouveau attendu.
            $this->ouvrirAttente($receipt->purchaseOrder);

            return $receipt->fresh();
        });
    }

    /** Le conditionnement saisi sur une ligne, s'il en est un de l'article ; null pour son unité. */
    private function conditionnementSaisi(?\App\Models\StockItem $item, ?string $saisi): ?string
    {
        $saisi = trim((string) $saisi);
        if ($item === null || $saisi === '' || $saisi === $item->unit) {
            return null;
        }

        $item->facteurDe($saisi); // un conditionnement inconnu de l'article est refusé

        return $saisi;
    }
}
