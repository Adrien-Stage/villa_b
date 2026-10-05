<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantStockCount;
use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ShopProduct;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockItem;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Fiches de comptage : la liste papier que chaque service remplit le jour de
 * l'inventaire — économat, garde-manger de chaque restaurant, boutique, et
 * chaque dépôt.
 *
 * Quand un inventaire est déjà ouvert, la fiche reprend son théorique figé :
 * c'est contre lui que le comptage sera rapproché, pas contre le stock du
 * moment. La fiche dit alors à quelle heure il a été figé.
 *
 * Une fiche : ['key', 'title', 'subtitle', 'reference', 'frozen_at', 'groups' => [catégorie => [lignes]]],
 * chaque ligne : ['reference', 'name', 'unit', 'theoretical'].
 */
class CountSheetService
{
    public const ECONOMAT = 'economat';
    /** Garde-manger : « garde-manger-3 » pour celui d'un restaurant quand il y en a plusieurs. */
    public const PANTRY = 'garde-manger';
    public const SHOP = 'boutique';
    public const STORE_PREFIX = 'depot-';
    public const ALL = 'tout';

    /**
     * Services qui peuvent être comptés, dans l'ordre de la tournée.
     *
     * @return array<string, string>  clé => libellé
     */
    public function services(): array
    {
        $services = [self::ECONOMAT => 'Économat — magasin central'];

        // Chaque restaurant qui tient un stock compte son garde-manger : une
        // fiche par cuisine dès qu'il y en a plusieurs, comme chacun a son
        // inventaire.
        $restaurants = $this->restaurantsAvecStock();

        if ($restaurants->count() > 1) {
            foreach ($restaurants as $restaurant) {
                $services[self::PANTRY . '-' . $restaurant->id] = "Cuisine — {$restaurant->name}";
            }
        } elseif ($restaurants->isNotEmpty() || app(RestaurantContext::class)->restaurants()->isEmpty()) {
            $services[self::PANTRY] = 'Cuisine — garde-manger';
        }

        $services[self::SHOP] = 'Boutique';

        foreach (ServiceStore::active()->orderBy('sort_order')->orderBy('name')->get() as $depot) {
            $services[self::STORE_PREFIX . $depot->id] = "{$depot->name} ({$depot->departmentLabel()})";
        }

        return $services;
    }

    /** @return list<array<string, mixed>> */
    public function sheets(string $key, ?int $categoryId = null): array
    {
        if ($key === self::ALL) {
            return array_values(array_filter(array_map(
                fn (string $service) => $this->sheet($service),
                array_keys($this->services())
            )));
        }

        $sheet = $this->sheet($key, $categoryId);

        return $sheet === null ? [] : [$sheet];
    }

    /** @return array<string, mixed>|null */
    public function sheet(string $key, ?int $categoryId = null): ?array
    {
        return match (true) {
            $key === self::ECONOMAT                 => $this->economat($categoryId),
            str_starts_with($key, self::PANTRY)     => $this->pantry($key, $categoryId),
            $key === self::SHOP                     => $this->shop(),
            str_starts_with($key, self::STORE_PREFIX) => $this->store((int) substr($key, strlen(self::STORE_PREFIX))),
            default                                 => null,
        };
    }

    private function economat(?int $categoryId): array
    {
        $categorie = $categoryId ? StockCategory::find($categoryId) : null;
        $inventaire = StockCount::inProgress();

        // Inventaire ouvert : son théorique figé, limité à son périmètre.
        if ($inventaire !== null) {
            $lignes = $inventaire->lines()->with('item.category')->get()
                ->filter(fn ($l) => $l->item && (!$categorie || $l->item->stock_category_id === $categorie->id))
                ->map(fn ($l) => $this->ligne($l->item->reference, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name));
        } else {
            $lignes = StockItem::active()->with('category')
                ->when($categorie, fn ($q) => $q->where('stock_category_id', $categorie->id))
                ->get()
                ->map(fn (StockItem $i) => $this->ligne($i->reference, $i->name, $i->unit, (float) $i->current_stock, $i->category?->name));
        }

        return $this->fiche(
            self::ECONOMAT,
            'Économat — magasin central',
            $categorie?->name,
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at
        );
    }

    private function pantry(string $key, ?int $categoryId): ?array
    {
        $restaurants = $this->restaurantsAvecStock();

        // « garde-manger » tout court : celui de l'unique restaurant, ou tout
        // le garde-manger quand aucun restaurant n'est déclaré.
        $restaurant = $key === self::PANTRY
            ? ($restaurants->count() === 1 ? $restaurants->first() : null)
            : $restaurants->firstWhere('id', (int) substr($key, strlen(self::PANTRY) + 1));

        if ($restaurant === null && $key !== self::PANTRY) {
            return null;
        }

        $categorie = $categoryId ? RestaurantPantryCategory::find($categoryId) : null;
        $inventaire = RestaurantStockCount::query()
            ->duRestaurant($restaurant)
            ->where('status', RestaurantStockCount::STATUS_DRAFT)
            ->latest('id')
            ->first();

        if ($inventaire !== null) {
            $lignes = $inventaire->lines()->with('item.category')->get()
                ->filter(fn ($l) => $l->item && (!$categorie || $l->item->restaurant_pantry_category_id === $categorie->id))
                ->map(fn ($l) => $this->ligne(null, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name));
        } else {
            $lignes = RestaurantPantryItem::active()->duRestaurant($restaurant)->with('category')
                ->when($categorie, fn ($q) => $q->where('restaurant_pantry_category_id', $categorie->id))
                ->get()
                ->map(fn (RestaurantPantryItem $i) => $this->ligne(null, $i->name, $i->unit, (float) $i->current_stock, $i->category?->name));
        }

        return $this->fiche(
            $key,
            $restaurant ? "Cuisine — {$restaurant->name}" : 'Cuisine — garde-manger',
            $categorie?->name,
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at
        );
    }

    private function shop(): array
    {
        $lignes = ShopProduct::query()->where('is_active', true)->with('category')->get()
            ->map(fn (ShopProduct $p) => $this->ligne($p->sku, $p->name, 'pièce', (float) $p->stock_quantity, $p->category?->name));

        return $this->fiche(self::SHOP, 'Boutique', null, null, $lignes);
    }

    private function store(int $id): ?array
    {
        $depot = ServiceStore::find($id);

        if ($depot === null) {
            return null;
        }

        $inventaire = ServiceStoreCountService::inProgress($depot);

        $lignes = $inventaire !== null
            ? $inventaire->lines()->with('item.category')->get()->filter(fn ($l) => $l->item)
                ->map(fn ($l) => $this->ligne($l->item->reference, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name))
            : $depot->stocks()->with('item.category')->get()->filter(fn ($s) => $s->item)
                ->map(fn ($s) => $this->ligne($s->item->reference, $s->item->name, $s->item->unit, (float) $s->current_stock, $s->item->category?->name));

        return $this->fiche(
            self::STORE_PREFIX . $depot->id,
            $depot->name,
            $depot->departmentLabel(),
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at
        );
    }

    /** @return Collection<int, PointOfSale> */
    private function restaurantsAvecStock(): Collection
    {
        return app(RestaurantContext::class)->restaurants()
            ->filter(fn (PointOfSale $restaurant): bool => $restaurant->offre(PointOfSale::SERVICE_STOCK))
            ->values();
    }

    /** @return array<string, mixed> */
    private function ligne(?string $reference, string $name, ?string $unit, float $theoretical, ?string $category): array
    {
        return [
            'reference'   => $reference,
            'name'        => $name,
            'unit'        => $unit,
            'theoretical' => $theoretical,
            'category'    => $category ?: 'Sans catégorie',
        ];
    }

    /** @return array<string, mixed> */
    private function fiche(string $key, string $title, ?string $subtitle, ?string $reference, $lignes, ?DateTimeInterface $frozenAt = null): array
    {
        return [
            'key'       => $key,
            'title'     => $title,
            'subtitle'  => $subtitle,
            'reference' => $reference,
            'frozen_at' => $frozenAt,
            'groups'    => collect($lignes)
                // Ordre naturel : « article 2 » avant « article 10 », comme on range une étagère.
                ->sortBy(fn (array $l) => $l['category'] . "\0" . $l['name'], SORT_NATURAL | SORT_FLAG_CASE)
                ->groupBy('category')
                ->map(fn ($groupe) => $groupe->values()->all())
                ->all(),
        ];
    }
}
