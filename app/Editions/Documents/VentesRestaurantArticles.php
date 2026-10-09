<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\User;
use App\Services\RestaurantContext;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Ce qui s'est vendu au restaurant, article par article : quantités et chiffre d'affaires. */
class VentesRestaurantArticles extends Edition
{
    public function cle(): string { return 'ventes-restaurant-articles'; }

    public function famille(): string { return self::RESTAURATION; }

    public function titre(): string { return 'Ventes du restaurant par article'; }

    public function description(): string
    {
        return "Les plats et boissons vendus sur la période, du plus vendu au moins vendu : quantités, chiffre d'affaires et part de chacun. Pour ajuster la carte et les commandes.";
    }

    public function module(): ?string { return 'restaurant'; }

    public function droits(): array { return ['restaurant.billing.voir', 'restaurant.consumption.voir', 'accounting.voir']; }

    public function filtres(): array
    {
        return [
            Filtre::periode('mois'),
            Filtre::choix('restaurant', 'Restaurant', fn (User $u) => app(RestaurantContext::class)->accessibles($u)->pluck('name', 'id')
                ->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom])->all(), 'Tous les restaurants'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];
        $restaurants = $valeurs['restaurant'] !== ''
            ? [(int) $valeurs['restaurant']]
            : app(RestaurantContext::class)->accessibles($user)->pluck('id')->all();

        $lignes = RestaurantCustomerOrderItem::query()
            ->join('restaurant_customer_orders as c', 'c.id', '=', 'restaurant_customer_order_items.restaurant_customer_order_id')
            ->where('c.status', '!=', 'canceled')
            ->whereBetween('c.placed_at', [$du->startOfDay(), $au->endOfDay()])
            ->when($restaurants !== [], fn ($q) => $q->whereIn('c.point_of_sale_id', $restaurants))
            ->selectRaw('restaurant_customer_order_items.item_name as article, SUM(restaurant_customer_order_items.quantity) as quantite, SUM(restaurant_customer_order_items.total_price) as montant')
            ->groupBy('restaurant_customer_order_items.item_name')
            ->orderByDesc('montant')
            ->get()
            ->map(fn ($l) => ['article' => $l->article, 'quantite' => (float) $l->quantite, 'montant' => (int) $l->montant]);

        $total = max(1, (int) $lignes->sum('montant'));
        $lignes = $lignes->map(fn ($l) => $l + ['part' => round($l['montant'] * 100 / $total, 1) . ' %']);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('article', 'Article'),
                Colonne::nombre('quantite', 'Quantité'),
                Colonne::montant('montant', "Chiffre d'affaires"),
                Colonne::texte('part', 'Part'),
            ])
            ->lignes($lignes)
            ->totaux(['quantite' => (float) $lignes->sum('quantite'), 'montant' => (int) $lignes->sum('montant')]);
    }
}
