<?php

namespace App\Notifications;

use App\Models\RestaurantPantryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Un produit du garde-manger vient de passer sous son seuil. Destinée au chef
 * cuisinier : sans cet ingrédient, des plats de la carte deviennent
 * indisponibles au service suivant.
 */
class PantryItemLowStock extends Notification
{
    use Queueable;

    public function __construct(public RestaurantPantryItem $item)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'webpush'];
    }

    private function url(): string
    {
        return route('restaurant.pantry.index');
    }

    private function isOutOfStock(): bool
    {
        return (float) $this->item->current_stock <= 0;
    }

    private function headline(): string
    {
        return $this->isOutOfStock() ? 'Ingrédient épuisé' : 'Garde-manger : stock bas';
    }

    /** Quantités décimales : on coupe les zéros inutiles (2,500 → 2,5). */
    private function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, ',', ' '), '0'), ',');
    }

    private function detail(): string
    {
        if ($this->isOutOfStock()) {
            return "{$this->item->name} est épuisé au garde-manger.";
        }

        return "{$this->item->name} : il reste {$this->quantity((float) $this->item->current_stock)} {$this->item->unit}"
            . " (seuil : {$this->quantity((float) $this->item->min_stock)}).";
    }

    public function toArray(object $notifiable): array
    {
        return [
            'pantry_item_id' => $this->item->id,
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
            'tag'   => 'pantry-low-' . $this->item->id,
        ];
    }
}
