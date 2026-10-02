<?php

use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\StockCountService;
use App\Services\StockRequisitionService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Inventaire ouvert : le magasin est gelé. Aucun mouvement de stock, aucune
 * validation de demande, jusqu'à la clôture ou l'annulation de la feuille.
 */

beforeEach(function () {
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

function rizEnStock(): StockItem
{
    return StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 100, 'average_cost' => 100_000]);
}

test('aucune entrée, sortie ni ajustement pendant un inventaire ouvert', function (string $operation) {
    $riz = rizEnStock();
    app(StockCountService::class)->open([]);
    $stock = app(StockService::class);

    $mouvement = match ($operation) {
        'entrée'     => fn () => $stock->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1),
        'sortie'     => fn () => $stock->recordOut($riz, 10, StockMovement::SOURCE_REQUISITION, 1),
        'ajustement' => fn () => $stock->adjust($riz, 90, 'Casse'),
        'annulation' => fn () => $stock->reverseIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1),
    };

    expect($mouvement)->toThrow(RuntimeException::class, 'en cours');
    expect((float) $riz->fresh()->current_stock)->toBe(100.0)
        ->and(StockMovement::count())->toBe(0);
})->with(['entrée', 'sortie', 'ajustement', 'annulation']);

test('une demande ne se valide ni ne se livre pendant l’inventaire', function () {
    $riz = rizEnStock();
    $enAttente = StockRequisition::create(['department' => 'housekeeping']);
    $validee = StockRequisition::create(['department' => 'housekeeping', 'status' => StockRequisition::STATUS_APPROVED]);
    $validee->lines()->create(['stock_item_id' => $riz->id, 'quantity_requested' => 5]);

    app(StockCountService::class)->open([]);
    $service = app(StockRequisitionService::class);

    expect(fn () => $service->approve($enAttente))->toThrow(RuntimeException::class, 'en cours');
    expect(fn () => $service->deliver($validee))->toThrow(RuntimeException::class, 'en cours');

    expect($enAttente->fresh()->status)->toBe(StockRequisition::STATUS_PENDING)
        ->and($validee->fresh()->status)->toBe(StockRequisition::STATUS_APPROVED)
        ->and((float) $riz->fresh()->current_stock)->toBe(100.0);
});

test('la clôture régularise le stock malgré le gel', function () {
    $riz = rizEnStock();
    $service = app(StockCountService::class);
    $feuille = $service->open([]);
    $service->updateCounts($feuille, [$feuille->lines->first()->id => ['counted_quantity' => 97]]);

    $service->close($feuille);

    expect((float) $riz->fresh()->current_stock)->toBe(97.0)
        ->and(StockCount::inProgress())->toBeNull();
});

test('après clôture ou annulation, les mouvements reprennent', function (string $fin) {
    $riz = rizEnStock();
    $service = app(StockCountService::class);
    $feuille = $service->open([]);

    $fin === 'clôture' ? $service->close($feuille) : $service->cancel($feuille);

    app(StockService::class)->recordOut($riz, 10, StockMovement::SOURCE_REQUISITION, 1);

    expect((float) $riz->fresh()->current_stock)->toBe(90.0);
})->with(['clôture', 'annulation']);

test('les écrans de l’économat signalent l’inventaire en cours', function () {
    rizEnStock();
    $feuille = app(StockCountService::class)->open([]);

    $this->get(route('economat.items.index'))
        ->assertOk()
        ->assertSee($feuille->reference)
        ->assertSee('suspendus');
});
