<?php

use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreStock;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\LedgerReportService;
use App\Services\ServiceStoreCountService;
use App\Services\StockRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Inventaire de dépôt : l'écart entre théorique et compté est la
 * consommation du service, passée en charge à la clôture. Centimes FCFA.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 10:00');
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

afterEach(fn () => Carbon::setTestNow());

/** Mini-bar des étages approvisionné de 24 bouteilles d'eau à 250 F. */
function minibarApprovisionne(): ServiceStore
{
    $boissons = StockCategory::create(['name' => 'Boissons', 'stock_account' => '311000']);
    $eau = StockItem::create([
        'stock_category_id' => $boissons->id, 'name' => 'Eau minérale', 'unit' => 'bouteille',
        'current_stock' => 100, 'average_cost' => 25_000,
    ]);
    $minibar = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping']);

    $demande = StockRequisition::create([
        'department' => 'housekeeping', 'service_store_id' => $minibar->id,
        'status' => StockRequisition::STATUS_APPROVED,
    ]);
    $demande->lines()->create(['stock_item_id' => $eau->id, 'quantity_requested' => 24]);
    app(StockRequisitionService::class)->deliver($demande);

    return $minibar;
}

function compterTout(ServiceStoreCount $inventaire, float $quantite): void
{
    app(ServiceStoreCountService::class)->updateCounts(
        $inventaire,
        $inventaire->lines->mapWithKeys(fn ($l) => [$l->id => ['counted_quantity' => $quantite]])->all()
    );
}

test('la clôture cale le dépôt sur le compté et mesure la consommation', function () {
    $minibar = minibarApprovisionne();
    $service = app(ServiceStoreCountService::class);

    $inventaire = $service->open($minibar);
    compterTout($inventaire, 9);
    $inventaire = $service->close($inventaire);

    expect((float) ServiceStoreStock::where('service_store_id', $minibar->id)->value('current_stock'))->toBe(9.0)
        ->and($inventaire->consumptionValue())->toBe(375_000)
        ->and($inventaire->status)->toBe(ServiceStoreCount::STATUS_CLOSED);
});

test('la consommation passe en charge du service au night audit', function () {
    $minibar = minibarApprovisionne();
    $service = app(ServiceStoreCountService::class);
    $inventaire = $service->open($minibar);
    compterTout($inventaire, 9);
    $service->close($inventaire);

    app(LedgerPostingService::class)->postDay(Carbon::parse('2026-06-15'));

    $soldes = app(LedgerReportService::class)
        ->balance(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))
        ->keyBy('code')
        ->map(fn ($l) => $l['balance']);

    // Livraison : aucun effet. Consommation : 15 bouteilles à 250 F.
    expect($soldes['603100'])->toBe(375_000)
        ->and($soldes['311000'])->toBe(-375_000);

    $ligne = \App\Models\JournalEntryLine::query()->where('account_code', '603100')->sole();
    expect($ligne->analytic_center)->toBe('hebergement');
});

test('une ligne non comptée garde son stock', function () {
    $minibar = minibarApprovisionne();
    $service = app(ServiceStoreCountService::class);

    $service->close($service->open($minibar));

    expect((float) ServiceStoreStock::where('service_store_id', $minibar->id)->value('current_stock'))->toBe(24.0)
        ->and($minibar->movements()->where('type', 'adjustment')->count())->toBe(0);
});

test('le dépôt ne reçoit plus de livraison pendant son inventaire', function () {
    $minibar = minibarApprovisionne();
    app(ServiceStoreCountService::class)->open($minibar);
    $eau = StockItem::where('name', 'Eau minérale')->sole();

    $demande = StockRequisition::create([
        'department' => 'housekeeping', 'service_store_id' => $minibar->id,
        'status' => StockRequisition::STATUS_APPROVED,
    ]);
    $demande->lines()->create(['stock_item_id' => $eau->id, 'quantity_requested' => 6]);

    expect(fn () => app(StockRequisitionService::class)->deliver($demande))->toThrow(RuntimeException::class, 'en cours');
    expect((float) $eau->fresh()->current_stock)->toBe(76.0);
});

test('un seul inventaire à la fois par dépôt, et une clôture ne se rejoue pas', function () {
    $minibar = minibarApprovisionne();
    $service = app(ServiceStoreCountService::class);
    $inventaire = $service->open($minibar);

    expect(fn () => $service->open($minibar))->toThrow(RuntimeException::class, 'déjà en cours');

    $ecranPerime = ServiceStoreCount::find($inventaire->id);
    $service->close($inventaire);

    expect(fn () => $service->close($ecranPerime))->toThrow(RuntimeException::class, 'plus en cours');
});

test('l’écran du dépôt ouvre, saisit et clôture l’inventaire', function () {
    $minibar = minibarApprovisionne();

    $this->post(route('economat.stores.counts.store', $minibar))->assertRedirect();
    $inventaire = ServiceStoreCount::sole();

    $this->get(route('economat.stores.counts.show', $inventaire))->assertOk()->assertSee('Eau minérale');

    $this->put(route('economat.stores.counts.update', $inventaire), [
        'lines' => [$inventaire->lines()->value('id') => ['counted_quantity' => 20, 'notes' => 'Chambres 101-110']],
    ])->assertSessionHas('success');

    $this->post(route('economat.stores.counts.close', $inventaire))->assertSessionHas('success');

    expect((float) ServiceStoreStock::where('service_store_id', $minibar->id)->value('current_stock'))->toBe(20.0);
});

test('le magasinier saisit les comptages sans clôturer', function () {
    $minibar = minibarApprovisionne();
    $inventaire = app(ServiceStoreCountService::class)->open($minibar);
    $magasinier = User::factory()->create(['role' => 'storekeeper']);

    $this->actingAs($magasinier)->put(route('economat.stores.counts.update', $inventaire), [
        'lines' => [$inventaire->lines()->value('id') => ['counted_quantity' => 20]],
    ])->assertSessionHas('success');

    $this->actingAs($magasinier)->postJson(route('economat.stores.counts.close', $inventaire))->assertForbidden();
});
