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
            'send_error'    => $error,
        ]);

        return $sent;
    }

    /**
     * Réception (totale ou partielle). $received associe l'id de ligne à la
     * quantité effectivement livrée cette fois-ci. Chaque quantité entre en
     * stock au prix unitaire du bon, et le statut du bon est recalculé.
     *
     * @param  array<int, float>  $received  [line_id => quantité reçue]
     */
    public function receive(PurchaseOrder $order, array $received): void
    {
        if (!$order->canBeReceived()) {
            throw new \RuntimeException('Ce bon ne peut pas être réceptionné dans son état actuel.');
        }

        DB::transaction(function () use ($order, $received) {
            foreach ($order->lines as $line) {
                $qty = (float) ($received[$line->id] ?? 0);
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
                    "Réception bon {$order->number}"
                );

                $line->update([
                    'quantity_received' => (float) $line->quantity_received + $qty,
                ]);
            }

            $order->update(['received_by' => auth()->id()]);
            $order->refreshReceptionStatus();
        });
    }
}
