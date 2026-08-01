<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Étape franchie par un bon de commande fournisseur : parti chez le
 * fournisseur, ou marchandise arrivée. Destinée à la direction et à la
 * comptabilité — l'envoi engage une dépense, la réception appelle une facture.
 */
class PurchaseOrderUpdated extends Notification
{
    use Queueable;

    public function __construct(public PurchaseOrder $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('economat.orders.show', $this->order);
    }

    private function headline(): string
    {
        return match ($this->order->status) {
            PurchaseOrder::STATUS_SENT               => 'Bon de commande envoyé',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Livraison partielle reçue',
            PurchaseOrder::STATUS_RECEIVED           => 'Commande reçue',
            default                                  => 'Bon de commande mis à jour',
        };
    }

    /** Montants stockés en centimes FCFA. */
    private function amount(): string
    {
        return number_format((float) $this->order->total_amount / 100, 0, ',', ' ') . ' FCFA';
    }

    private function detail(): string
    {
        $supplier = $this->order->supplier?->name ?? 'fournisseur inconnu';

        return match ($this->order->status) {
            PurchaseOrder::STATUS_SENT => "Bon {$this->order->number} transmis à {$supplier} — {$this->amount()}.",
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => "Bon {$this->order->number} ({$supplier}) : "
                . 'une partie de la marchandise est entrée en stock, le reste est attendu.',
            PurchaseOrder::STATUS_RECEIVED => "Bon {$this->order->number} ({$supplier}) : "
                . "marchandise entrée en stock — {$this->amount()} à régler.",
            default => "Bon {$this->order->number} ({$supplier}) mis à jour.",
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->order->id,
            'title'   => $this->headline(),
            'message' => $this->detail(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body'  => $this->detail(),
            'url'   => $this->url(),
            // Un tag par bon : l'envoi puis la réception se remplacent.
            'tag'   => 'po-' . $this->order->id,
        ];
    }
}
