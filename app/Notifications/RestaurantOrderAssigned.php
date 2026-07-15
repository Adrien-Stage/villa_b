<?php

namespace App\Notifications;

use App\Models\RestaurantCustomerOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Une commande du portail est confiée à un serveur (ou, faute de serveur en
 * service, proposée à tous). Le serveur doit la transmettre en cuisine.
 *
 * Canaux : database (cloche in-app) + webpush (notification système).
 */
class RestaurantOrderAssigned extends Notification
{
    use Queueable;

    public function __construct(
        public RestaurantCustomerOrder $order,
        public bool $needsPickup = false,
    ) {
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
        $title = $this->needsPickup ? 'Commande à prendre en charge' : 'Nouvelle commande à servir';

        $message = $this->needsPickup
            ? "Commande #{$this->order->id} ({$this->table()}) reçue du portail — aucun serveur en service, à prendre en charge."
            : "Commande #{$this->order->id} ({$this->table()}) vous est confiée. Transmettez-la en cuisine.";

        return [
            'order_id' => $this->order->id,
            'title' => $title,
            'message' => $message,
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->needsPickup ? 'Commande à prendre en charge' : 'Nouvelle commande à servir',
            'body' => "{$this->table()} · commande #{$this->order->id} du portail",
            'url' => $this->url(),
            'tag' => 'resto-order-' . $this->order->id,
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}
