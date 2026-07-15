<?php

namespace App\Notifications;

use App\Models\RestaurantCustomerOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * La cuisine signale un plat prêt. Destinée au serveur responsable de la
 * commande : à lui d'aller le chercher et de l'apporter à la table.
 */
class RestaurantOrderReady extends Notification
{
    use Queueable;

    public function __construct(public RestaurantCustomerOrder $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/restaurant/orders/' . $this->order->id;
    }

    private function table(): string
    {
        return $this->order->table_number ? "Table {$this->order->table_number}" : 'Sans table';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'title' => 'Plat prêt à servir',
            'message' => "Commande #{$this->order->id} ({$this->table()}) est prête. À apporter à la table.",
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Plat prêt à servir',
            'body' => "{$this->table()} · commande #{$this->order->id} prête",
            'url' => $this->url(),
            'tag' => 'resto-ready-' . $this->order->id,
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}
