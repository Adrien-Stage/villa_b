<?php

use App\Models\JournalEntry;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\LedgerReportService;
use App\Services\RestaurantStockService;
use App\Services\StockService;
use App\Services\SupplierInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Inventaire permanent : chaque mouvement de stock se reflète en classe 3,
 * avec la variation 603x en contrepartie. Montants en centimes FCFA.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 10:00');
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

afterEach(fn () => Carbon::setTestNow());

function articleEconomat(string $nom, ?string $compte, float $stock = 0, int $cump = 0): StockItem
{
    $categorie = $compte === null ? null : StockCategory::create(['name' => "Catégorie {$nom}", 'stock_account' => $compte]);

    return StockItem::create([
        'stock_category_id' => $categorie?->id,
        'name'              => $nom,
        'unit'              => 'kg',
        'current_stock'     => $stock,
        'average_cost'      => $cump,
    ]);
}

function demandeLivree(string $service): StockRequisition
{
    return StockRequisition::create(['department' => $service, 'status' => StockRequisition::STATUS_DELIVERED]);
}

/** Soldes de la balance de juin, indexés par compte. */
function soldesDeJuin(): \Illuminate\Support\Collection
{
    return app(LedgerReportService::class)
        ->balance(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))
        ->keyBy('code')
        ->map(fn ($ligne) => $ligne['balance']);
}

function ecritureStock(string $schema): JournalEntry
{
    return JournalEntry::query()->where('schema', "{$schema}:2026-06-15")->with('lines')->firstOrFail();
}

test('une réception économat entre en classe 3 contre la variation de stock', function () {
    $riz = articleEconomat('Riz', '321000');

    app(StockService::class)->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    expect($soldes['321000'])->toBe(1_000_000)
        ->and($soldes['603200'])->toBe(-1_000_000);
});

test('chaque catégorie valorise l’article sur son compte, et l’article sans compte tombe en 332000', function () {
    $javel = articleEconomat('Javel', '331000');
    $stylos = articleEconomat('Stylos', null);
    $biere = articleEconomat('Bière', '311000');

    $stock = app(StockService::class);
    $stock->recordIn($javel, 4, 50_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);
    $stock->recordIn($stylos, 10, 2_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);
    $stock->recordIn($biere, 24, 60_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    expect($soldes['331000'])->toBe(200_000)
        ->and($soldes['603300'])->toBe(-220_000)
        ->and($soldes['332000'])->toBe(20_000)
        ->and($soldes['311000'])->toBe(1_440_000)
        ->and($soldes['603100'])->toBe(-1_440_000);
});

test('une livraison à un service passe en charge sur le centre du service', function () {
    $javel = articleEconomat('Javel', '331000', 10, 50_000);

    app(StockService::class)->recordOut($javel, 2, StockMovement::SOURCE_REQUISITION, demandeLivree('housekeeping')->id);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $charge = ecritureStock(LedgerPostingService::SCHEMA_ECONOMAT_STOCK)->lines->firstWhere('account_code', '603300');
    expect($charge->debit)->toBe(100_000)
        ->and($charge->analytic_center)->toBe('hebergement')
        ->and(soldesDeJuin()['331000'])->toBe(-100_000);
});

test('un transfert vers la cuisine change le stock de compte sans charge', function () {
    $huile = articleEconomat('Huile', null, 10, 25_000);
    $riz = articleEconomat('Riz', '321000', 10, 100_000);

    $stock = app(StockService::class);
    $stock->recordOut($huile, 4, StockMovement::SOURCE_REQUISITION, demandeLivree('restaurant')->id);
    $stock->recordOut($riz, 2, StockMovement::SOURCE_REQUISITION, demandeLivree('restaurant')->id);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    // Le riz est déjà en 321000 : son transfert ne produit aucune ligne.
    $soldes = soldesDeJuin();
    expect($soldes['321000'])->toBe(100_000)
        ->and($soldes['332000'])->toBe(-100_000)
        ->and($soldes->keys()->filter(fn ($code) => str_starts_with($code, '6'))->all())->toBe([]);
});

test('les écarts d’inventaire économat passent en variation de stock', function () {
    $riz = articleEconomat('Riz', '321000', 10, 100_000);
    $javel = articleEconomat('Javel', '331000', 4, 50_000);

    $stock = app(StockService::class);
    $stock->adjust($riz, 9.5, 'Inventaire', StockMovement::SOURCE_STOCK_COUNT, 1);
    $stock->adjust($javel, 5, 'Inventaire', StockMovement::SOURCE_STOCK_COUNT, 1);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    expect($soldes['321000'])->toBe(-50_000)
        ->and($soldes['603200'])->toBe(50_000)
        ->and($soldes['331000'])->toBe(50_000)
        ->and($soldes['603300'])->toBe(-50_000);
});

test('l’annulation d’une réception ressort le stock de la classe 3', function () {
    $riz = articleEconomat('Riz', '321000');

    $stock = app(StockService::class);
    $stock->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);
    $stock->recordOut($riz, 10, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    expect($soldes['321000'])->toBe(0)
        ->and($soldes['603200'])->toBe(0);
});

test('la comptabilisation du stock économat est idempotente', function () {
    $riz = articleEconomat('Riz', '321000');
    app(StockService::class)->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    $posting = app(LedgerPostingService::class);
    $premiere = $posting->postEconomatStock(Carbon::parse('2026-06-15'));
    $seconde = $posting->postEconomatStock(Carbon::parse('2026-06-15'));

    expect($premiere)->not->toBeNull()
        ->and($seconde)->toBeNull()
        ->and(JournalEntry::count())->toBe(1);
});

test('le garde-manger débite le 321000 pour un achat direct et pour un manquant crédite', function () {
    $categorie = RestaurantPantryCategory::create(['name' => 'Épicerie']);
    $tomate = RestaurantPantryItem::create([
        'restaurant_pantry_category_id' => $categorie->id,
        'name'          => 'Tomate',
        'unit'          => 'kg',
        'current_stock' => 0,
        'average_cost'  => 0,
        'is_active'     => true,
    ]);

    $cuisine = app(RestaurantStockService::class);
    $cuisine->recordMovement($tomate, RestaurantPantryMovement::TYPE_IN, 10, RestaurantPantryMovement::REASON_PURCHASE, 50_000);
    // Inventaire : 8 kg constatés sur 10 attendus, soit 2 kg manquants.
    $cuisine->recordMovement($tomate, RestaurantPantryMovement::TYPE_ADJUST, 8, RestaurantPantryMovement::REASON_COUNT);

    app(LedgerPostingService::class)->postPantryStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    expect($soldes['321000'])->toBe(400_000)
        ->and($soldes['603200'])->toBe(-400_000);

    $manquant = ecritureStock(LedgerPostingService::SCHEMA_PANTRY_STOCK)->lines
        ->first(fn ($l) => $l->account_code === '603200' && $l->debit > 0);
    expect($manquant->debit)->toBe(100_000)
        ->and($manquant->analytic_center)->toBe('restaurant');
});

test('le transfert reçu de l’économat n’est pas comptabilisé deux fois côté cuisine', function () {
    $categorie = RestaurantPantryCategory::create(['name' => 'Épicerie']);
    $riz = RestaurantPantryItem::create([
        'restaurant_pantry_category_id' => $categorie->id,
        'name'          => 'Riz',
        'unit'          => 'kg',
        'current_stock' => 0,
        'average_cost'  => 0,
        'is_active'     => true,
    ]);

    app(RestaurantStockService::class)->recordMovement(
        $riz,
        RestaurantPantryMovement::TYPE_IN,
        5,
        RestaurantPantryMovement::REASON_TRANSFER_IN,
        100_000,
        stockRequisitionId: demandeLivree('restaurant')->id,
    );

    expect(app(LedgerPostingService::class)->postPantryStock(Carbon::parse('2026-06-15')))->toBeNull();
});

test('achat facturé, réception puis consommation : la charge vaut la seule consommation', function () {
    $riz = articleEconomat('Riz', '321000');

    // Facture fournisseur : D 602000 / C 401000, hors TVA.
    app(SupplierInvoiceService::class)->record([
        'supplier'         => Supplier::create(['name' => 'Grossiste', 'email' => 'grossiste@test.cm', 'is_active' => true]),
        'number'           => 'FA-STOCK-1',
        'invoice_date'     => Carbon::parse('2026-06-15'),
        'charge_account'   => '602000',
        'label'            => 'Riz',
        'amount_ttc'       => 1_000_000,
        'withholding_type' => null,
    ]);

    app(StockService::class)->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);
    app(StockService::class)->recordOut($riz, 10, StockMovement::SOURCE_REQUISITION, demandeLivree('restaurant')->id);

    // La cuisine consomme 3 kg sur les 10 reçus.
    $categorie = RestaurantPantryCategory::create(['name' => 'Épicerie']);
    $rizCuisine = RestaurantPantryItem::create([
        'restaurant_pantry_category_id' => $categorie->id,
        'stock_item_id' => $riz->id,
        'name'          => 'Riz',
        'unit'          => 'kg',
        'current_stock' => 10,
        'average_cost'  => 100_000,
        'is_active'     => true,
    ]);
    app(RestaurantStockService::class)->recordMovement($rizCuisine, RestaurantPantryMovement::TYPE_OUT, 3, RestaurantPantryMovement::REASON_KITCHEN);

    app(LedgerPostingService::class)->postDay(Carbon::parse('2026-06-15'));

    $soldes = soldesDeJuin();
    $charges = $soldes->filter(fn ($solde, $code) => str_starts_with($code, '60'))->sum();

    expect($charges)->toBe(300_000)
        ->and($soldes['321000'])->toBe(700_000);

    $totaux = app(LedgerReportService::class)->balanceTotals(
        app(LedgerReportService::class)->balance(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))
    );
    expect($totaux['balanced'])->toBeTrue();
});
