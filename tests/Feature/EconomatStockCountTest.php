<?php

use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createEconomeStaff(): User
{
    $u = User::factory()->create(['role' => 'econome']);
    test()->actingAs($u);
    return $u;
}

test('un econome peut ouvrir un inventaire general figeant les stocks theoriques et les CUMP', function () {
    $econome = createEconomeStaff();

    // 2 articles avec différents stocks et CUMP (en centimes)
    $catBoissons = StockCategory::create(['name' => 'Boissons']);
    $item1 = StockItem::create([
        'stock_category_id' => $catBoissons->id,
        'name' => 'Bière Beaufort 50cl',
        'unit' => 'bouteille',
        'current_stock' => 120,
        'average_cost' => 65000, // 650 F / bouteille
        'is_active' => true,
    ]);

    $item2 = StockItem::create([
        'stock_category_id' => $catBoissons->id,
        'name' => 'Eau Minérale 1.5L',
        'unit' => 'bouteille',
        'current_stock' => 45,
        'average_cost' => 30000, // 300 F / bouteille
        'is_active' => true,
    ]);

    // 1 article inactif ne doit pas être inclus
    StockItem::create([
        'stock_category_id' => $catBoissons->id,
        'name' => 'Ancien jus obsolète',
        'unit' => 'bouteille',
        'current_stock' => 0,
        'average_cost' => 10000,
        'is_active' => false,
    ]);

    $service = app(StockCountService::class);
    $stockCount = $service->open([
        'count_date' => now()->toDateString(),
        'notes' => 'Grand inventaire mensuel',
    ], $econome);

    expect($stockCount)->not->toBeNull()
        ->and($stockCount->status)->toBe(StockCount::STATUS_DRAFT)
        ->and($stockCount->reference)->toStartWith('INV-' . date('Y') . '-')
        ->and($stockCount->lines)->toHaveCount(2)
        // Valeur théorique totale : (120 * 65 000) + (45 * 30 000) = 7 800 000 + 1 350 000 = 9 150 000 centimes
        ->and($stockCount->total_theoretical_value)->toBe(9150000);

    $line1 = $stockCount->lines->firstWhere('stock_item_id', $item1->id);
    expect($line1)->not->toBeNull()
        ->and((float) $line1->theoretical_quantity)->toBe(120.0)
        ->and($line1->unit_cost)->toBe(65000)
        ->and($line1->theoretical_value)->toBe(7800000)
        ->and($line1->counted_quantity)->toBeNull();
});

test('il est impossible d ouvrir deux inventaires simultanement', function () {
    $econome = createEconomeStaff();

    $item = StockItem::create([
        'name' => 'Riz Parfumé 50kg',
        'unit' => 'sac',
        'current_stock' => 10,
        'average_cost' => 2500000,
        'is_active' => true,
    ]);

    $service = app(StockCountService::class);
    $service->open([], $econome);

    // Tentative d'ouvrir un 2e inventaire alors que le 1er est en draft
    expect(fn () => $service->open([], $econome))
        ->toThrow(RuntimeException::class, "déjà en cours");
});

test('un inventaire cible par categorie ne prend que les articles de cette categorie', function () {
    $econome = createEconomeStaff();

    $catBoissons = StockCategory::create(['name' => 'Boissons']);
    $catEpicerie = StockCategory::create(['name' => 'Épicerie']);

    $itemBoisson = StockItem::create([
        'stock_category_id' => $catBoissons->id,
        'name' => 'Vin Rouge Bordeaux',
        'unit' => 'bouteille',
        'current_stock' => 24,
        'average_cost' => 450000,
        'is_active' => true,
    ]);

    $itemEpicerie = StockItem::create([
        'stock_category_id' => $catEpicerie->id,
        'name' => 'Huile d Olive 1L',
        'unit' => 'bouteille',
        'current_stock' => 15,
        'average_cost' => 350000,
        'is_active' => true,
    ]);

    $service = app(StockCountService::class);
    $stockCount = $service->open([
        'stock_category_id' => $catBoissons->id,
    ], $econome);

    expect($stockCount->lines)->toHaveCount(1)
        ->and($stockCount->lines->first()->stock_item_id)->toBe($itemBoisson->id)
        ->and($stockCount->stock_category_id)->toBe($catBoissons->id);
});

test('la saisie des comptages calcule en direct les ecarts financiers sans impacter le stock reel', function () {
    $econome = createEconomeStaff();

    $item1 = StockItem::create([
        'name' => 'Whisky Single Malt',
        'unit' => 'bouteille',
        'current_stock' => 10,
        'average_cost' => 2500000, // 25 000 F / bout.
        'is_active' => true,
    ]);

    $item2 = StockItem::create([
        'name' => 'Champagne Brut',
        'unit' => 'bouteille',
        'current_stock' => 20,
        'average_cost' => 3000000, // 30 000 F / bout.
        'is_active' => true,
    ]);

    $service = app(StockCountService::class);
    $stockCount = $service->open([], $econome);

    $line1 = $stockCount->lines->firstWhere('stock_item_id', $item1->id);
    $line2 = $stockCount->lines->firstWhere('stock_item_id', $item2->id);

    // Saisie :
    // Item 1 : 8 trouvées en rayon au lieu de 10 -> écart -2 bouteilles = -5 000 000 centimes (Perte)
    // Item 2 : 23 trouvées en rayon au lieu de 20 -> écart +3 bouteilles = +9 000 000 centimes (Boni/Surplus)
    $linesData = [
        $line1->id => [
            'counted_quantity' => 8,
            'reason' => 'spoilage',
            'notes' => 'Bouteilles cassées en réserve',
        ],
        $line2->id => [
            'counted_quantity' => 23,
            'reason' => 'input_error',
            'notes' => 'Erreur bon de livraison précédent non saisi',
        ],
    ];

    $updatedCount = $service->updateCounts($stockCount, $linesData);

    $line1->refresh();
    $line2->refresh();

    // Vérification des lignes
    expect((float) $line1->counted_quantity)->toBe(8.0)
        ->and((float) $line1->variance_quantity)->toBe(-2.0)
        ->and($line1->variance_value)->toBe(-5000000)
        ->and($line1->reason)->toBe('spoilage')
        ->and($line1->notes)->toBe('Bouteilles cassées en réserve');

    expect((float) $line2->counted_quantity)->toBe(23.0)
        ->and((float) $line2->variance_quantity)->toBe(3.0)
        ->and($line2->variance_value)->toBe(9000000)
        ->and($line2->reason)->toBe('input_error');

    // Vérification de la synthèse financière du stockCount
    // Pertes : 5 000 000 centimes
    // Surplus : 9 000 000 centimes
    // Solde net de variation : +4 000 000 centimes
    expect($updatedCount->loss_value)->toBe(5000000)
        ->and($updatedCount->surplus_value)->toBe(9000000)
        ->and($updatedCount->variance_value)->toBe(4000000);

    // VÉRIFICATION MAJEURE : Les stocks réels dans StockItem ne DOIVENT PAS avoir bougé tant que ce n'est pas clôturé
    expect((float) $item1->fresh()->current_stock)->toBe(10.0)
        ->and((float) $item2->fresh()->current_stock)->toBe(20.0);
});

test('la cloture de l inventaire regularise physiquement les stocks et cree des mouvements d audit stock_count', function () {
    $econome = createEconomeStaff();

    $item1 = StockItem::create([
        'name' => 'Café Grains 1kg',
        'unit' => 'kg',
        'current_stock' => 50,
        'average_cost' => 800000, // 8 000 F / kg
        'is_active' => true,
    ]);

    $item2 = StockItem::create([
        'name' => 'Sucre en Morceaux',
        'unit' => 'kg',
        'current_stock' => 30,
        'average_cost' => 100000, // 1 000 F / kg
        'is_active' => true,
    ]);

    $service = app(StockCountService::class);
    $stockCount = $service->open([], $econome);

    $line1 = $stockCount->lines->firstWhere('stock_item_id', $item1->id);
    $line2 = $stockCount->lines->firstWhere('stock_item_id', $item2->id);

    // Item 1 : 45 comptés (-5 kg de perte coulage)
    // Item 2 : 30 comptés (0 écart, exact)
    $service->updateCounts($stockCount, [
        $line1->id => [
            'counted_quantity' => 45,
            'reason' => 'spoilage',
            'notes' => 'Sacs détériorés par humidité',
        ],
        $line2->id => [
            'counted_quantity' => 30,
        ],
    ]);

    // Clôture de l'inventaire
    $closedCount = $service->close($stockCount, $econome);

    expect($closedCount->status)->toBe(StockCount::STATUS_CLOSED)
        ->and($closedCount->closed_by)->toBe($econome->id)
        ->and($closedCount->closed_at)->not->toBeNull();

    // 1. StockItem 1 doit avoir été mis à jour à 45 kg
    expect((float) $item1->fresh()->current_stock)->toBe(45.0);

    // 2. StockItem 2 n'a pas bougé (30 kg)
    expect((float) $item2->fresh()->current_stock)->toBe(30.0);

    // 3. Un mouvement d'ajustement doit avoir été créé pour Item 1 avec source_type = stock_count
    $movement1 = StockMovement::where('stock_item_id', $item1->id)
        ->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
        ->where('source_id', $stockCount->id)
        ->first();

    expect($movement1)->not->toBeNull()
        ->and($movement1->type)->toBe(StockMovement::TYPE_ADJUSTMENT)
        ->and((float) $movement1->quantity)->toBe(-5.0)
        ->and($movement1->reason)->toContain($stockCount->reference)
        ->and($movement1->reason)->toContain('Périmé / avarié')
        ->and($movement1->user_id)->toBe($econome->id);

    // 4. Aucun mouvement ne doit exister pour Item 2 car écart = 0
    $movement2 = StockMovement::where('stock_item_id', $item2->id)
        ->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
        ->first();

    expect($movement2)->toBeNull();

    // 5. Impossible de re-modifier ou d'annuler un inventaire clôturé
    expect(fn () => $service->updateCounts($closedCount, []))
        ->toThrow(RuntimeException::class, "déjà clôturé");

    expect(fn () => $service->cancel($closedCount))
        ->toThrow(RuntimeException::class, "ne peut pas être annulé");
});

test('l annulation d un inventaire en cours annule la feuille sans modifier les stocks', function () {
    $econome = createEconomeStaff();

    $item = StockItem::create([
        'name' => 'Lait Concentré Sucré',
        'unit' => 'boite',
        'current_stock' => 100,
        'average_cost' => 80000,
        'is_active' => true,
    ]);

    $service = app(StockCountService::class);
    $stockCount = $service->open([], $econome);

    $line = $stockCount->lines->first();
    $service->updateCounts($stockCount, [
        $line->id => [
            'counted_quantity' => 80, // écart de -20
            'reason' => 'theft',
        ],
    ]);

    // Annulation
    $service->cancel($stockCount);

    expect($stockCount->fresh()->status)->toBe(StockCount::STATUS_CANCELLED)
        // Le stock réel reste 100
        ->and((float) $item->fresh()->current_stock)->toBe(100.0);

    // Zéro mouvement d'ajustement
    $movement = StockMovement::where('source_type', StockMovement::SOURCE_STOCK_COUNT)->first();
    expect($movement)->toBeNull();
});

test('les routes http d inventaire et le rapport PV fonctionnent et respectent les permissions', function () {
    $econome = createEconomeStaff();

    $item = StockItem::create([
        'name' => 'Jus d Ananas Frais',
        'unit' => 'litre',
        'current_stock' => 50,
        'average_cost' => 120000,
        'is_active' => true,
    ]);

    // 1. Accès à la liste
    $response = test()->get(route('economat.stock_counts.index'));
    $response->assertOk()
        ->assertViewIs('economat.stock_counts.index');

    // 2. Accès au formulaire de création
    $response = test()->get(route('economat.stock_counts.create'));
    $response->assertOk()
        ->assertViewIs('economat.stock_counts.create');

    // 3. Ouverture via POST
    $response = test()->post(route('economat.stock_counts.store'), [
        'count_date' => now()->toDateString(),
        'notes' => 'Inventaire hebdomadaire bar',
    ]);
    $response->assertRedirect();

    $stockCount = StockCount::latest('id')->first();
    expect($stockCount)->not->toBeNull();

    // 4. Consultation de la feuille de comptage
    $response = test()->get(route('economat.stock_counts.show', $stockCount));
    $response->assertOk()
        ->assertViewIs('economat.stock_counts.show')
        ->assertSee('Jus d Ananas Frais');

    // 5. Saisie du comptage via POST
    $line = $stockCount->lines->first();
    $response = test()->put(route('economat.stock_counts.update', $stockCount), [
        'lines' => [
            $line->id => [
                'counted_quantity' => 48,
                'reason' => 'waste',
                'notes' => 'Pertes au bar',
            ],
        ],
    ]);
    $response->assertRedirect();
    expect((float) $line->fresh()->counted_quantity)->toBe(48.0);

    // 6. Clôture de l'inventaire via POST
    $response = test()->post(route('economat.stock_counts.close', $stockCount));
    $response->assertRedirect(route('economat.stock_counts.show', $stockCount));
    expect($stockCount->fresh()->status)->toBe(StockCount::STATUS_CLOSED)
        ->and((float) $item->fresh()->current_stock)->toBe(48.0);

    // 7. Consultation du PV officiel imprimable
    $response = test()->get(route('economat.stock_counts.report', $stockCount));
    $response->assertOk()
        ->assertViewIs('economat.stock_counts.report')
        ->assertSee($stockCount->reference)
        ->assertSee('Procès-Verbal', false)
        ->assertSee('Inventaire Physique')
        ->assertSee('Jus d Ananas Frais');
});
