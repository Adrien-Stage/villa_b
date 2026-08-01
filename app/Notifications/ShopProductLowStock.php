<?php

namespace App\Notifications;

use App\Models\ShopProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un article de la boutique vient de passer sous son seuil de réassort après
 * une vente. Destinée au gérant : une rupture en boutique, c'est une vente
 * perdue le jour même.
 */
class ShopProductLowStock extends Notification
{
    use Queueable;

    public function __construct(public ShopProduct $product)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('shop.products.index');
    }

    private function isOutOfStock(): bool
    {
        return (int) $this->product->stock_quantity <= 0;
    }

    private function headline(): string
    {
        return $this->isOutOfStock() ? 'Rupture en boutique' : 'Stock boutique bas';
    }

    private function detail(): string
    {
        if ($this->isOutOfStock()) {
            return "{$this->product->name} est épuisé en boutique.";
        }

        return "{$this->product->name} : il reste {$this->product->stock_quantity} unité(s)"
            . " (seuil de réassort : {$this->product->reorder_level}).";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shop_product_id' => $this->product->id,
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
            'tag'   => 'shop-low-' . $this->product->id,
        ];
    }
}
