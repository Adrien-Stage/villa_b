<?php

namespace App\Notifications;

use App\Models\RestaurantCustomerOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un serveur transmet un bon de commande à la cuisine. Destinée aux cuisiniers
 * (et au chef) : c'est leur file de préparation qui se remplit.
 */
class RestaurantOrderSentToKitchen extends Notification
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
        return rtrim((string) config('app.url'), '/') . '/restaurant/kitchen';
    }

    private function table(): string
    {
        return $this->order->table_number ? "Table {$this->order->table_number}" : 'Sans table';
    }

    public function toArray(object $notifiable): array
    {
        $this->order->loadMissing('items');
        $itemsCount = $this->order->items->sum('quantity');

        return [
            'order_id' => $this->order->id,
            'title' => 'Nouveau bon de commande',
            'message' => "Bon #{$this->order->id} ({$this->table()}) — {$itemsCount} article(s) à préparer.",
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Nouveau bon de commande',
            'body' => "{$this->table()} · bon #{$this->order->id} à préparer",
            'url' => $this->url(),
            'tag' => 'resto-kitchen-' . $this->order->id,
            'icon' => rtrim((string) config('app.url'), '/') . '/favicon.ico',
        ];
    }
}
