<?php

namespace App\Notifications;

use App\Models\StockItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un article vient de passer sous son seuil d'alerte après une sortie.
 * Destinée à l'économe : c'est le moment de lancer un réapprovisionnement,
 * avant la rupture qui bloquerait un département.
 */
class StockItemBelowThreshold extends Notification
{
    use Queueable;

    public function __construct(public StockItem $item)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('economat.items.show', $this->item);
    }

    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }

    private function detail(): string
    {
        $stock = (float) $this->item->current_stock;

        if ($stock <= 0) {
            return "{$this->item->name} est en rupture de stock.";
        }

        return "{$this->item->name} : il reste {$this->quantity($stock)} {$this->item->unit}"
            . " (seuil : {$this->quantity((float) $this->item->min_stock)}).";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'stock_item_id' => $this->item->id,
            'title'   => (float) $this->item->current_stock <= 0 ? 'Rupture de stock' : 'Stock bas',
            'message' => $this->detail(),
            'url'     => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => (float) $this->item->current_stock <= 0 ? 'Rupture de stock' : 'Stock bas',
            'body'  => $this->detail(),
            'url'   => $this->url(),
            // Un tag par article : les alertes successives sur le même article
            // se remplacent au lieu de s'accumuler.
            'tag'   => 'stock-low-' . $this->item->id,
        ];
    }
}
