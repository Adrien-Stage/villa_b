<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/** Ce qui s'est vendu à la boutique, produit par produit. */
class VentesBoutiqueProduits extends Edition
{
    public function cle(): string { return 'ventes-boutique-produits'; }

    public function famille(): string { return self::RESTAURATION; }

    public function titre(): string { return 'Ventes de la boutique par produit'; }

    public function description(): string
    {
        return "Les produits vendus sur la période, du plus vendu au moins vendu : quantités et chiffre d'affaires.";
    }

    public function module(): ?string { return 'shop'; }

    public function droits(): array { return ['shop.orders.voir', 'accounting.voir']; }

    public function filtres(): array
    {
        return [Filtre::periode('mois')];
    }

    public function document(array $valeurs, User $user): Document
    {
        [$du, $au] = $valeurs['periode'];

        $lignes = ShopOrderItem::query()
            ->join('shop_orders as o', 'o.id', '=', 'shop_order_items.shop_order_id')
            ->leftJoin('shop_products as p', 'p.id', '=', 'shop_order_items.shop_product_id')
            ->where('o.payment_status', '!=', 'cancelled')
            ->whereBetween('o.created_at', [$du->startOfDay(), $au->endOfDay()])
            ->selectRaw("COALESCE(p.name, 'Produit supprimé') as produit, SUM(shop_order_items.quantity) as quantite, SUM(shop_order_items.item_total) as montant")
            ->groupBy('p.name')
            ->orderByDesc('montant')
            ->get()
            ->map(fn ($l) => ['produit' => $l->produit, 'quantite' => (float) $l->quantite, 'montant' => (int) $l->montant]);

        return $this->base($valeurs, $user)
            ->colonnes([
                Colonne::texte('produit', 'Produit'),
                Colonne::nombre('quantite', 'Quantité'),
                Colonne::montant('montant', "Chiffre d'affaires"),
            ])
            ->lignes($lignes)
            ->totaux(['quantite' => (float) $lignes->sum('quantite'), 'montant' => (int) $lignes->sum('montant')]);
    }
}
