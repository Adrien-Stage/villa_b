<?php

namespace App\Services;

use App\Mail\PurchaseOrderMail;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cycle de vie d'un bon de commande : envoi au fournisseur et réception des
 * marchandises. L'entrée en stock et le coût moyen pondéré sont délégués au
 * StockService, source unique des mouvements.
 */
class PurchaseOrderService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * Envoie le bon par email au fournisseur et le passe à « envoyé ».
     *
     * L'échec d'envoi n'empêche pas le passage au statut envoyé mais est tracé
     * (send_error) : le bon existe, le fournisseur a été appelé au téléphone
     * dans bien des cas, et l'économe pourra relancer l'email. Bloquer ici
     * ferait perdre le travail de saisie pour une simple panne SMTP.
     */
    public function send(PurchaseOrder $order): bool
    {
        if (!$order->canBeSent()) {
            throw new \RuntimeException('Ce bon ne peut pas être envoyé (statut ou lignes manquantes).');
        }

        $supplier = $order->supplier;
        if (!$supplier || !$supplier->canReceiveOrdersByEmail()) {
            throw new \RuntimeException("Le fournisseur n'a pas d'adresse email : impossible d'envoyer le bon.");
        }

        $order->recalculateTotal();

        $sent  = true;
        $error = null;
        try {
            Mail::to($supplier->email)->send(new PurchaseOrderMail($order));
        } catch (\Throwable $e) {
            $sent  = false;
            $error = $e->getMessage();
            Log::error("Échec envoi bon {$order->number} à {$supplier->email} : {$error}");
        }

        $order->update([
            'status'        => PurchaseOrder::STATUS_SENT,
            'sent_at'       => now(),
            'sent_to_email' => $supplier->email,
            'transmission'  => PurchaseOrder::TRANSMISSION_EMAIL,
            'send_error'    => $error,
        ]);

        return $sent;
    }

    /**
     * Le bon est parvenu au fournisseur sans email : remis en main propre,
     * dicté au téléphone, envoyé par WhatsApp. Il passe à « envoyé » comme
     * après un email, et peut donc être réceptionné : un fournisseur sans
     * adresse ne doit pas bloquer la chaîne d'achat.
     */
    public function markTransmitted(PurchaseOrder $order, string $moyen): void
    {
        if (!array_key_exists($moyen, PurchaseOrder::TRANSMISSIONS_MANUELLES)) {
            throw new \InvalidArgumentException('Moyen de transmission inconnu.');
        }

        DB::transaction(function () use ($order, $moyen) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (!$order->canBeSent()) {
                throw new \RuntimeException('Ce bon ne peut pas être transmis (statut ou lignes manquantes).');
            }

            $order->recalculateTotal();

            $order->update([
                'status'        => PurchaseOrder::STATUS_SENT,
                'sent_at'       => now(),
                'sent_to_email' => null,
                'transmission'  => $moyen,
                'send_error'    => null,
            ]);
        });

        $order->refresh();
    }

    /**
     * Réception (totale ou partielle). $received associe l'id de ligne à la
     * quantité effectivement livrée cette fois-ci. Chaque quantité entre en
     * stock au prix unitaire du bon, et le statut du bon est recalculé.
     *
     * @param  array<int, float>        $received         [line_id => quantité reçue]
     * @param  array<int, string|null>  $conditionnements [line_id => conditionnement saisi (carton…)]
     */
    public function receive(PurchaseOrder $order, array $received, array $conditionnements = []): void
    {
        if (!$order->canBeReceived()) {
            throw new \RuntimeException('Ce bon ne peut pas être réceptionné dans son état actuel.');
        }

        DB::transaction(function () use ($order, $received, $conditionnements) {
            // Verrou sur le bon : deux réceptions simultanées liraient le même
            // reste dû et pourraient, ensemble, dépasser la quantité commandée.
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (!$order->canBeReceived()) {
                throw new \RuntimeException('Ce bon ne peut pas être réceptionné dans son état actuel.');
            }

            $order->load('lines.item.packagings');
            $recu = false;

            foreach ($order->lines as $line) {
                // Reçu en cartons : la quantité se ramène à l'unité de l'article.
                $conditionnement = trim((string) ($conditionnements[$line->id] ?? ''));
                $conditionnement = $conditionnement === '' || $conditionnement === $line->item?->unit ? null : $conditionnement;
                $facteur = $conditionnement !== null ? $line->item->facteurDe($conditionnement) : 1.0;

                $qty = round((float) ($received[$line->id] ?? 0) * $facteur, 3);
                if ($qty <= 0) {
                    continue;
                }

                // On ne reçoit jamais plus que le reste dû sur la ligne : une
                // sur-livraison se traite en ajustement, pas via le bon.
                $qty = min($qty, $line->outstanding());
                if ($qty <= 0) {
                    continue;
                }

                $this->stock->recordIn(
                    $line->item,
                    $qty,
                    $line->unit_price,
                    StockMovement::SOURCE_PURCHASE_ORDER,
                    $order->id,
                    "Réception bon {$order->number}",
                    $conditionnement
                );

                $line->update([
                    'quantity_received' => (float) $line->quantity_received + $qty,
                ]);
                $recu = true;
            }

            // Un formulaire laissé vide n'est pas une réception : le dire,
            // plutôt que d'annoncer une réception qui n'a rien fait entrer.
            if (!$recu) {
                throw new \RuntimeException('Aucune quantité reçue : saisissez les quantités réellement livrées.');
            }

            $order->update(['received_by' => auth()->id()]);
            $order->refreshReceptionStatus();
        });

        $order->refresh();
    }
}
