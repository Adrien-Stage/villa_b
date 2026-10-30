<?php

namespace App\Notifications;

use App\Models\StockItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * L'économat a transformé un article : ouvert un conditionnement, ou découpé
 * une quantité en portions pour un garde-manger. Destinée au service achats,
 * qui tient le catalogue et voit ainsi le stock se transformer.
 */
class StockItemTransformed extends Notification
{
    use Queueable;

    public function __construct(
        public StockItem $item,
        public string $quoi,
        public string $url,
        public ?string $par = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function message(): string
    {
        return "{$this->item->name} : {$this->quoi}" . ($this->par ? " (par {$this->par})" : '') . '.';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'stock_item_id' => $this->item->id,
            'title' => 'Article transformé',
            'message' => $this->message(),
            'url' => $this->url,
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Article transformé',
            'body' => $this->message(),
            'url' => $this->url,
        ];
    }
}
