<?php

namespace App\Http\Controllers;

use App\Models\PointOfSale;
use App\Models\RestaurantBarDrink;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantCustomerOrderItem;
use App\Models\RestaurantMenuItem;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement as Movement;
use App\Models\StockItem;
use App\Services\RestaurantBarStock;
use App\Services\RestaurantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RestaurantBarController extends Controller
{
    public function __construct(private RestaurantBarStock $bar) {}

    public function depot(Request $request): View
    {
        $restaurant = app(RestaurantContext::class)->courant($request->user());
        $store = $restaurant ? $this->bar->storeFor($restaurant) : null;
        $stocks = $store?->stocks()->with('item')->get()->sortBy('item.name') ?? collect();
        $volumes = $store?->barBottles()->where('remaining_ml', '>', 0)->selectRaw('stock_item_id, SUM(remaining_ml) AS volume')
            ->groupBy('stock_item_id')->pluck('volume', 'stock_item_id');

        return view('restaurant.bar.depot', [
            'restaurant' => $restaurant,
            'store' => $store,
            'stocks' => $stocks,
            'volumes' => $volumes,
            'bottles' => $store?->barBottles()->with('item')
                ->orderByRaw('CASE WHEN remaining_ml > 0 THEN 0 ELSE 1 END')->orderBy('id')->paginate(20)->withQueryString(),
            'shortages' => $store ? $this->bar->unallocated($store) : collect(),
        ]);
    }

    public function bottle(Request $request, int $bottle): View
    {
        $store = $this->activeStore($request);
        $bottle = $store->barBottles()->with(['item', 'opener'])->findOrFail($bottle);

        return view('restaurant.bar.bottle', [
            'bottle' => $bottle,
            'history' => $store->movements()->whereIn('source_type', Movement::BAR_SOURCES)
                ->where('details->bottle_id', $bottle->id)->with('user')->latest('id')->paginate(30),
            'shortages' => $this->bar->unallocated($store, $bottle->stock_item_id),
        ]);
    }

    public function menu(Request $request): View
    {
        $restaurant = app(RestaurantContext::class)->courant($request->user());
        $store = $restaurant ? $this->bar->storeFor($restaurant) : null;
        $focused = $request->integer('item');
        $drinks = $store ? RestaurantMenuItem::duRestaurant($restaurant)->where('type', 'drink')->with('barDrink')
            ->when($focused, fn ($query) => $query->whereKey($focused))
            ->orderBy('name')->paginate(20)->withQueryString() : null;

        return view('restaurant.bar.menu', [
            'restaurant' => $restaurant,
            'store' => $store,
            'drinks' => $drinks,
            'focused' => $focused,
            'items' => $store ? StockItem::active()->orderBy('name')->get(['id', 'name', 'unit'])->keyBy('id') : collect(),
        ]);
    }

    public function sales(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $to = $filters['to'] ?? now()->toDateString();
        $from = $filters['from'] ?? Carbon::parse($to)->startOfMonth()->toDateString();
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => 'La date de début doit précéder ou être égale à la date de fin.']);
        }
        $restaurant = app(RestaurantContext::class)->courant($request->user());
        $store = $restaurant ? $this->bar->storeFor($restaurant) : null;
        $sales = $store ? RestaurantCustomerOrderItem::query()->auBar()
            ->selectRaw('item_name, SUM(quantity) AS quantity, SUM(total_price) AS total')
            ->where(fn ($query) => $query->where('is_beverage', true)->orWhere(fn ($legacy) => $legacy
                ->whereNull('is_beverage')->whereHas('menuItem', fn ($item) => $item->where('type', 'drink'))))
            ->whereHas('order', fn ($query) => $query->duRestaurant($restaurant)
                ->where('payment_status', 'paid')->where('status', '!=', RestaurantCustomerOrder::STATUS_CANCELED)
                ->where('paid_at', '>=', $from.' 00:00:00')
                ->where('paid_at', '<', Carbon::parse($to)->addDay()->startOfDay()))
            ->groupBy('item_name')->orderBy('item_name')->paginate(30)->withQueryString() : null;

        // Un bar sans cuisine reçoit aussi les plats. Le suivi des boissons
        // utilise le type d'origine (les libellés et les prix viennent du bon).
        return view('restaurant.bar.sales', compact('restaurant', 'store', 'sales', 'from', 'to'));
    }

    public function open(Request $request): RedirectResponse
    {
        $store = $this->activeStore($request);
        $data = $request->validate([
            'stock_item_id' => ['required', 'integer'],
            'capacity_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            'opening_key' => ['required', 'uuid'],
        ]);
        $item = StockItem::active()->findOrFail($data['stock_item_id']);
        $bottle = $this->bar->openBottle($store, $item, $data['capacity_ml'], $data['opening_key']);

        return redirect()->route('restaurant.bar.bottles.show', $bottle)->with('success', 'Bouteille ouverte. Étiquetez-la avec son numéro.');
    }

    public function count(Request $request, int $bottle): RedirectResponse
    {
        $store = $this->activeStore($request);
        $bottle = $store->barBottles()->findOrFail($bottle);
        $data = $request->validate([
            'volume_ml' => ['required', 'integer', 'min:0', 'max:'.$bottle->capacity_ml],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->bar->countBottle($store, $bottle, $data['volume_ml'], $data['reason']);

        return back()->with('success', 'Comptage enregistré.');
    }

    public function allocate(Request $request, int $bottle): RedirectResponse
    {
        $store = $this->activeStore($request);
        $bottle = $store->barBottles()->findOrFail($bottle);
        $data = $request->validate([
            'movement_id' => ['required', 'integer'],
            'volume_ml' => ['required', 'integer', 'min:1', 'max:100000'],
            'allocation_key' => ['required', 'uuid'],
        ]);
        $this->bar->allocate($store, $bottle, $data['movement_id'], $data['volume_ml'], $data['allocation_key']);

        return back()->with('success', 'Prélèvement rattaché à cette bouteille.');
    }

    public function configure(Request $request, int $item): RedirectResponse
    {
        $store = $this->activeStore($request);
        $menuItem = RestaurantMenuItem::duRestaurant($store->point_of_sale_id)->where('type', 'drink')->findOrFail($item);
        $recipe = in_array($request->input('format'), ['cocktail', 'mocktail'], true);
        if ($recipe && is_array($request->input('ingredients'))) {
            $request->merge(['ingredients' => collect($request->input('ingredients'))->reject(
                fn ($row) => is_array($row) && empty($row['stock_item_id']) && empty($row['quantity'])
            )->values()->all()]);
        }
        $data = $request->validate([
            'format' => ['required', Rule::in(array_keys(RestaurantBarDrink::FORMATS))],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'stock_item_id' => [Rule::excludeIf($recipe), 'required', 'integer'],
            'ingredients' => [Rule::excludeIf(! $recipe), 'required', 'array', 'min:1', 'max:30'],
            'ingredients.*' => ['array:stock_item_id,measure,quantity'],
            'ingredients.*.stock_item_id' => ['required', 'integer'],
            'ingredients.*.measure' => ['required', Rule::in(['ml', 'stock'])],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
        ]);
        $ingredients = $recipe ? $data['ingredients'] : [[
            'stock_item_id' => $data['stock_item_id'],
            'measure' => $data['format'] === 'glass' ? 'ml' : 'stock',
            'quantity' => $data['format'] === 'glass' ? RestaurantBarDrink::GLASS_ML : 1,
        ]];

        $items = StockItem::active()->whereKey(array_column($ingredients, 'stock_item_id'))->get()->keyBy('id');
        foreach ($ingredients as $ingredient) {
            $stockItem = $items->get($ingredient['stock_item_id']);
            if (! $stockItem || (($ingredient['measure'] === 'ml' || $data['format'] === 'bottle') && ! RestaurantBarStock::canOpen($stockItem))) {
                throw ValidationException::withMessages(['ingredients' => 'Choisissez un article actif. Les doses et bouteilles entières exigent un article stocké à la bouteille ou à la pièce.']);
            }
            if ($ingredient['measure'] === 'ml' && floor((float) $ingredient['quantity']) !== (float) $ingredient['quantity']) {
                throw ValidationException::withMessages(['ingredients' => 'Les volumes des fiches techniques se saisissent en millilitres entiers.']);
            }
        }

        DB::transaction(function () use ($menuItem, $store, $data, $ingredients) {
            abort_unless(ServiceStore::active()->lockForUpdate()->find($store->id), 404);
            $menuItem->barDrink()->updateOrCreate([], [
                'format' => $data['format'],
                'ingredients' => $ingredients,
                'instructions' => $data['instructions'] ?? null,
            ]);
        });

        return back()->with('success', 'Fiche de boisson enregistrée.');
    }

    private function activeStore(Request $request): ServiceStore
    {
        $restaurant = app(RestaurantContext::class)->exigerPourSaisie($request->user());

        return $this->bar->storeFor($restaurant) ?? abort(404, 'Le bar de ce restaurant est désactivé.');
    }
}
