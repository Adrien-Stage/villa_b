<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\RestaurantStockCount;
use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreMovement;
use App\Models\ShopOrderItem;
use App\Models\ShopProduct;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;
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
 * Les articles à compter se choisissent : tous les articles actifs, ceux qui
 * sont en stock, ou ceux en stock plus ceux tombés à 0 après un mouvement
 * depuis une date (par défaut, le dernier inventaire du service). Un article
 * à 0 qui a bougé doit être vérifié : il est peut-être encore en rayon.
 *
 * Une fiche : ['key', 'title', 'subtitle', 'reference', 'frozen_at', 'selection', 'groups' => [catégorie => [lignes]]],
 * chaque ligne : ['id', 'reference', 'name', 'unit', 'theoretical'].
 */
class CountSheetService
{
    public const ECONOMAT = 'economat';
    /** Garde-manger : « garde-manger-3 » pour celui d'un restaurant quand il y en a plusieurs. */
    public const PANTRY = 'garde-manger';
    public const SHOP = 'boutique';
    public const STORE_PREFIX = 'depot-';
    public const ALL = 'tout';

    public const ARTICLES_TOUS = 'tous';
    public const ARTICLES_EN_STOCK = 'en_stock';
    public const ARTICLES_EN_STOCK_ET_MOUVEMENTES = 'mouvementes';

    /** Les articles qu'une fiche peut lister. */
    public const SELECTIONS = [
        self::ARTICLES_TOUS                   => 'Tous les articles actifs',
        self::ARTICLES_EN_STOCK               => 'Uniquement les articles en stock',
        self::ARTICLES_EN_STOCK_ET_MOUVEMENTES => 'Les articles en stock, et ceux tombés à 0 après un mouvement',
    ];

    /** Sélection en cours : posée par sheets()/sheet(), lue par chaque fiche. */
    private string $selection = self::ARTICLES_TOUS;

    private ?CarbonInterface $depuis = null;

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

    /**
     * @param  string  $selection  une clé de SELECTIONS
     * @param  CarbonInterface|null  $depuis  début de la période des mouvements ; par défaut, le dernier inventaire du service
     * @return list<array<string, mixed>>
     */
    public function sheets(string $key, ?int $categoryId = null, string $selection = self::ARTICLES_TOUS, ?CarbonInterface $depuis = null): array
    {
        if ($key === self::ALL) {
            return array_values(array_filter(array_map(
                fn (string $service) => $this->sheet($service, null, $selection, $depuis),
                array_keys($this->services())
            )));
        }

        $sheet = $this->sheet($key, $categoryId, $selection, $depuis);

        return $sheet === null ? [] : [$sheet];
    }

    /** @return array<string, mixed>|null */
    public function sheet(string $key, ?int $categoryId = null, string $selection = self::ARTICLES_TOUS, ?CarbonInterface $depuis = null): ?array
    {
        $this->selection = array_key_exists($selection, self::SELECTIONS) ? $selection : self::ARTICLES_TOUS;
        $this->depuis = $depuis;

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
            $lignes = $inventaire->lines()->with('item.category', 'item.packagings')->get()
                ->filter(fn ($l) => $l->item && (!$categorie || $l->item->stock_category_id === $categorie->id))
                ->map(fn ($l) => $this->ligne($l->item->id, $l->item->reference, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name)
                    + ['conditionnements' => $l->item->packagings->sortByDesc('factor')->pluck('name')->all()]);
        } else {
            $lignes = StockItem::active()->with('category', 'packagings')
                ->when($categorie, fn ($q) => $q->where('stock_category_id', $categorie->id))
                ->get()
                ->map(fn (StockItem $i) => $this->ligne($i->id, $i->reference, $i->name, $i->unit, (float) $i->current_stock, $i->category?->name)
                    // Un article conditionné se compte par niveau : cartons fermés, paquets, vrac.
                    + ['conditionnements' => $i->packagings->sortByDesc('factor')->pluck('name')->all()]);
        }

        $dernierInventaire = StockCount::query()->where('status', StockCount::STATUS_CLOSED)
            ->where(fn ($q) => $q->whereNull('stock_category_id')->when($categorie, fn ($c) => $c->orWhere('stock_category_id', $categorie->id)))
            ->max('closed_at');

        [$lignes, $libelle] = $this->selectionner($lignes, $dernierInventaire, fn (CarbonInterface $debut) => StockMovement::query()
            ->where('occurred_at', '>=', $debut)->distinct()->pluck('stock_item_id'));

        return $this->fiche(
            self::ECONOMAT,
            'Économat — magasin central',
            $categorie?->name,
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at,
            $libelle
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
                ->map(fn ($l) => $this->ligne($l->item->id, null, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name));
        } else {
            $lignes = RestaurantPantryItem::active()->duRestaurant($restaurant)->with('category')
                ->when($categorie, fn ($q) => $q->where('restaurant_pantry_category_id', $categorie->id))
                ->get()
                ->map(fn (RestaurantPantryItem $i) => $this->ligne($i->id, null, $i->name, $i->unit, (float) $i->current_stock, $i->category?->name));
        }

        $dernierInventaire = RestaurantStockCount::query()->duRestaurant($restaurant)
            ->where('status', RestaurantStockCount::STATUS_CLOSED)->max('closed_at');

        [$lignes, $libelle] = $this->selectionner($lignes, $dernierInventaire, fn (CarbonInterface $debut) => RestaurantPantryMovement::query()
            ->where('occurred_at', '>=', $debut)->distinct()->pluck('restaurant_pantry_item_id'));

        return $this->fiche(
            $key,
            $restaurant ? "Cuisine — {$restaurant->name}" : 'Cuisine — garde-manger',
            $categorie?->name,
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at,
            $libelle
        );
    }

    private function shop(): array
    {
        $lignes = ShopProduct::query()->where('is_active', true)->with('category')->get()
            ->map(fn (ShopProduct $p) => $this->ligne($p->id, $p->sku, $p->name, 'pièce', (float) $p->stock_quantity, $p->category?->name));

        // La boutique n'a pas d'inventaire ni de journal : un produit a bougé
        // s'il a été vendu sur la période.
        [$lignes, $libelle] = $this->selectionner($lignes, null, fn (CarbonInterface $debut) => ShopOrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('created_at', '>=', $debut))->distinct()->pluck('shop_product_id'));

        return $this->fiche(self::SHOP, 'Boutique', null, null, $lignes, null, $libelle);
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
                ->map(fn ($l) => $this->ligne($l->item->id, $l->item->reference, $l->item->name, $l->item->unit, (float) $l->theoretical_quantity, $l->item->category?->name))
            : $depot->stocks()->with('item.category')->get()->filter(fn ($s) => $s->item)
                ->map(fn ($s) => $this->ligne($s->item->id, $s->item->reference, $s->item->name, $s->item->unit, (float) $s->current_stock, $s->item->category?->name));

        $dernierInventaire = ServiceStoreCount::query()->where('service_store_id', $depot->id)
            ->where('status', ServiceStoreCount::STATUS_CLOSED)->max('closed_at');

        [$lignes, $libelle] = $this->selectionner($lignes, $dernierInventaire, fn (CarbonInterface $debut) => ServiceStoreMovement::query()
            ->where('service_store_id', $depot->id)->where('occurred_at', '>=', $debut)->distinct()->pluck('stock_item_id'));

        return $this->fiche(
            self::STORE_PREFIX . $depot->id,
            $depot->name,
            $depot->departmentLabel(),
            $inventaire?->reference,
            $lignes,
            $inventaire?->created_at,
            $libelle
        );
    }

    /** @return Collection<int, PointOfSale> */
    private function restaurantsAvecStock(): Collection
    {
        return app(RestaurantContext::class)->restaurants()
            ->filter(fn (PointOfSale $restaurant): bool => $restaurant->offre(PointOfSale::SERVICE_STOCK))
            ->values();
    }

    /**
     * Retient les lignes de la sélection en cours.
     *
     * « En stock » vaut un stock différent de 0 : un stock négatif (garde-manger)
     * signale une anomalie, il se compte aussi.
     *
     * @param  callable(CarbonInterface): Collection  $bougesDepuis  identifiants des articles mouvementés depuis cette date
     * @return array{0: Collection, 1: ?string}  [lignes retenues, ce que la fiche liste]
     */
    private function selectionner(Collection $lignes, mixed $dernierInventaire, callable $bougesDepuis): array
    {
        $enStock = fn (array $l): bool => abs($l['theoretical']) >= 0.0005;

        if ($this->selection === self::ARTICLES_EN_STOCK) {
            return [$lignes->filter($enStock), 'Articles en stock uniquement'];
        }

        if ($this->selection !== self::ARTICLES_EN_STOCK_ET_MOUVEMENTES) {
            return [$lignes, null];
        }

        $debut = $this->depuis?->copy()->startOfDay()
            ?? ($dernierInventaire ? Carbon::parse($dernierInventaire) : now()->startOfMonth());
        $bouges = $bougesDepuis($debut)->map(fn ($id) => (int) $id)->flip();

        $libelle = 'Articles en stock, et articles tombés à 0 après un mouvement depuis le ' . $debut->format('d/m/Y')
            . ($this->depuis === null && $dernierInventaire ? ' (dernier inventaire)' : '');

        return [$lignes->filter(fn (array $l): bool => $enStock($l) || isset($bouges[(int) $l['id']])), $libelle];
    }

    /** @return array<string, mixed> */
    private function ligne(int $id, ?string $reference, string $name, ?string $unit, float $theoretical, ?string $category): array
    {
        return [
            'id'          => $id,
            'reference'   => $reference,
            'name'        => $name,
            'unit'        => $unit,
            'theoretical' => $theoretical,
            'category'    => $category ?: 'Sans catégorie',
        ];
    }

    /** @return array<string, mixed> */
    private function fiche(string $key, string $title, ?string $subtitle, ?string $reference, $lignes, ?DateTimeInterface $frozenAt = null, ?string $selection = null): array
    {
        return [
            'key'       => $key,
            'title'     => $title,
            'subtitle'  => $subtitle,
            'reference' => $reference,
            'frozen_at' => $frozenAt,
            'selection' => $selection,
            'groups'    => collect($lignes)
                // Ordre naturel : « article 2 » avant « article 10 », comme on range une étagère.
                ->sortBy(fn (array $l) => $l['category'] . "\0" . $l['name'], SORT_NATURAL | SORT_FLAG_CASE)
                ->groupBy('category')
                ->map(fn ($groupe) => $groupe->values()->all())
                ->all(),
        ];
    }
}
