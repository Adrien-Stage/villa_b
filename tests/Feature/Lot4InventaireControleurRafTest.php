<?php

use App\Models\ServiceStore;
use App\Models\ServiceStoreCount;
use App\Models\ServiceStoreStock;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ServiceStoreCountService;
use App\Services\StockCountService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant']);

    Tenant::create([
        'name'     => 'Palmier Ebene',
        'slug'     => 'palmier-ebene',
        'currency' => 'XAF',
        'settings' => [],
    ]);

    $this->catNourriture = StockCategory::create(['name' => 'Nourriture']);
    $this->catBoissons   = StockCategory::create(['name' => 'Boissons']);

    $this->riz = StockItem::create([
        'stock_category_id' => $this->catNourriture->id,
        'name'              => 'Riz Parfumé 25kg',
        'unit'              => 'sac',
        'current_stock'     => 10,
        'average_cost'      => 1500000, // 15 000 FCFA
        'is_active'         => true,
    ]);

    $this->biere = StockItem::create([
        'stock_category_id' => $this->catBoissons->id,
        'name'              => 'Bière Castel 50cl',
        'unit'              => 'bouteille',
        'current_stock'     => 50,
        'average_cost'      => 60000, // 600 FCFA
        'is_active'         => true,
    ]);
});

function agentRole(string $role): User
{
    $user = User::factory()->create([
        'role'      => $role,
        'is_active' => true,
    ]);
    test()->actingAs($user);

    return $user;
}

test('1. Seul le contrôleur de gestion peut ouvrir une feuille d inventaire et saisir les comptages physiques', function () {
    $controller = agentRole('controller');

    // Le contrôleur peut ouvrir un inventaire
    $response = $this->post(route('economat.stock_counts.store'), [
        'count_date' => now()->toDateString(),
        'notes'      => 'Inventaire mensuel contradictoire',
    ]);
    $response->assertRedirect();

    $stockCount = StockCount::latest('id')->first();
    expect($stockCount)->not->toBeNull()
        ->and($stockCount->status)->toBe(StockCount::STATUS_DRAFT)
        ->and($stockCount->opened_by)->toBe($controller->id);

    // Le contrôleur peut saisir des quantités comptées
    $line = $stockCount->lines()->where('stock_item_id', $this->riz->id)->first();
    $this->put(route('economat.stock_counts.update', $stockCount), [
        'lines' => [
            $line->id => [
                'counted_quantity' => 12, // +2 sacs d'excédent
                'notes'            => 'Retrouvé au fond du rayon',
            ],
        ],
    ])->assertRedirect();

    expect((float) $line->fresh()->counted_quantity)->toBe(12.0)
        ->and((int) $line->fresh()->variance_value)->toBe(3000000); // +30 000 FCFA

    // L'économe ne peut PAS ouvrir d'inventaire
    $econome = agentRole('econome');
    $this->post(route('economat.stock_counts.store'), [
        'count_date' => now()->toDateString(),
    ])->assertSessionHas('access_denied_popup', true);

    // Le magasinier ne peut PAS saisir de comptages
    $magasinier = agentRole('storekeeper');
    $this->put(route('economat.stock_counts.update', $stockCount), [
        'lines' => [$line->id => ['counted_quantity' => 5]],
    ])->assertSessionHas('access_denied_popup', true);

    // L'acheteur ne peut PAS ouvrir d'inventaire
    $acheteur = agentRole('purchasing_manager');
    $this->post(route('economat.stock_counts.store'), [
        'count_date' => now()->toDateString(),
    ])->assertSessionHas('access_denied_popup', true);
});

test('2. Circuit SoD : le contrôleur soumet, le contrôleur ne peut PAS valider, le RAF seul valide et régularise le stock', function () {
    $controller = agentRole('controller');
    $inventaire = app(StockCountService::class)->open(['notes' => 'Inventaire bar'], $controller);

    $ligneBiere = $inventaire->lines()->where('stock_item_id', $this->biere->id)->first();
    app(StockCountService::class)->updateCounts($inventaire, [
        $ligneBiere->id => [
            'counted_quantity' => 45, // -5 bouteilles de perte
            'reason'           => StockCountLine::REASON_WASTE,
            'notes'            => '5 bouteilles cassées lors de la manutention',
        ],
    ]);

    // Le contrôleur soumet l'inventaire au RAF
    $this->actingAs($controller)
        ->post(route('economat.stock_counts.submit', $inventaire))
        ->assertRedirect(route('economat.stock_counts.show', $inventaire));

    $inventaire->refresh();
    expect($inventaire->isSubmitted())->toBeTrue()
        ->and($inventaire->submitted_by)->toBe($controller->id)
        ->and($inventaire->submitted_at)->not->toBeNull();

    // Pendant la soumission, les comptages sont verrouillés pour le contrôleur
    $this->actingAs($controller)
        ->put(route('economat.stock_counts.update', $inventaire), [
            'lines' => [$ligneBiere->id => ['counted_quantity' => 40]],
        ])->assertSessionHas('error');

    // Le contrôleur essaie de valider lui-même son inventaire : REFUS STRICT (SoD S8)
    $this->actingAs($controller)
        ->post(route('economat.stock_counts.close', $inventaire))
        ->assertSessionHas('access_denied_popup', true);

    expect($inventaire->fresh()->isSubmitted())->toBeTrue();
    // Le stock de l'article n'a pas bougé
    expect((float) $this->biere->fresh()->current_stock)->toBe(50.0);

    // Le RAF (manager) valide l'inventaire
    $raf = agentRole('manager');
    $this->actingAs($raf)
        ->post(route('economat.stock_counts.close', $inventaire))
        ->assertRedirect(route('economat.stock_counts.show', $inventaire));

    $inventaire->refresh();
    expect($inventaire->isClosed())->toBeTrue()
        ->and($inventaire->closed_by)->toBe($raf->id)
        ->and($inventaire->validated_by)->toBe($raf->id)
        ->and($inventaire->validated_at)->not->toBeNull();

    // Le stock réel est désormais régularisé à 45 bouteilles
    expect((float) $this->biere->fresh()->current_stock)->toBe(45.0);

    // Un mouvement d'ajustement a été créé
    $mouvement = StockMovement::where('stock_item_id', $this->biere->id)
        ->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
        ->where('source_id', $inventaire->id)
        ->latest('id')
        ->first();

    expect($mouvement)->not->toBeNull()
        ->and((float) $mouvement->quantity)->toBe(-5.0);
});

test('3. Rejet de soumission par le RAF : la feuille est renvoyée en correction au contrôleur avec motif', function () {
    $controller = agentRole('controller');
    $inventaire = app(StockCountService::class)->open([], $controller);
    app(StockCountService::class)->submit($inventaire, $controller);

    // Le RAF rejette l'inventaire avec motif
    $raf = agentRole('manager');
    $this->actingAs($raf)
        ->post(route('economat.stock_counts.reject', $inventaire), [
            'reason' => 'Écart non documenté sur le riz, recompter svp.',
        ])->assertRedirect(route('economat.stock_counts.show', $inventaire));

    $inventaire->refresh();
    expect($inventaire->isRejected())->toBeTrue()
        ->and($inventaire->rejected_by)->toBe($raf->id)
        ->and($inventaire->rejection_reason)->toBe('Écart non documenté sur le riz, recompter svp.');

    // Le contrôleur peut de nouveau corriger la feuille rejetée
    $lineRiz = $inventaire->lines()->where('stock_item_id', $this->riz->id)->first();
    $this->actingAs($controller)
        ->put(route('economat.stock_counts.update', $inventaire), [
            'lines' => [$lineRiz->id => ['counted_quantity' => 10, 'notes' => 'Recomptage : stock conforme']],
        ])->assertRedirect();

    expect((float) $lineRiz->fresh()->counted_quantity)->toBe(10.0);

    // Et le contrôleur peut la soumettre à nouveau
    $this->actingAs($controller)
        ->post(route('economat.stock_counts.submit', $inventaire))
        ->assertRedirect();

    expect($inventaire->fresh()->isSubmitted())->toBeTrue();
});

test('4. Verrou temporel de saisie des 48h : modification acceptée à H+47h59, refusée à H+48h01', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 08:00:00'));

    $controller = agentRole('controller');
    $inventaire = app(StockCountService::class)->open(['notes' => 'Inventaire daté'], $controller);
    $line = $inventaire->lines()->first();

    // À H+47h59 : la saisie est encore permise
    Carbon::setTestNow(Carbon::parse('2026-11-03 07:59:00'));
    $this->actingAs($controller)
        ->put(route('economat.stock_counts.update', $inventaire), [
            'lines' => [$line->id => ['counted_quantity' => 8]],
        ])->assertRedirect();

    expect((float) $line->fresh()->counted_quantity)->toBe(8.0);

    // À H+48h01 : le délai de 48h est expiré, la modification est rejetée
    Carbon::setTestNow(Carbon::parse('2026-11-03 08:01:00'));
    $this->actingAs($controller)
        ->put(route('economat.stock_counts.update', $inventaire), [
            'lines' => [$line->id => ['counted_quantity' => 9]],
        ])->assertSessionHas('error');

    // La quantité reste inchangée à 8
    expect((float) $line->fresh()->counted_quantity)->toBe(8.0);

    Carbon::setTestNow();
});

test('5. Annulation et contre-passation par le RAF sous 48h : rétablissement parfait des stocks antérieurs', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

    $controller = agentRole('controller');
    $raf        = agentRole('manager');

    $inventaire = app(StockCountService::class)->open([], $controller);
    $ligneBiere = $inventaire->lines()->where('stock_item_id', $this->biere->id)->first();

    // Saisie : 42 bouteilles (variance -8 bouteilles)
    app(StockCountService::class)->updateCounts($inventaire, [
        $ligneBiere->id => ['counted_quantity' => 42, 'reason' => StockCountLine::REASON_WASTE],
    ]);
    app(StockCountService::class)->submit($inventaire, $controller);

    // Validation par le RAF à 11h
    Carbon::setTestNow(Carbon::parse('2026-11-01 11:00:00'));
    app(StockCountService::class)->close($inventaire, $raf);

    expect((float) $this->biere->fresh()->current_stock)->toBe(42.0);

    // Le contrôleur tente d'annuler cet inventaire validé : REFUSÉ
    $this->actingAs($controller)
        ->post(route('economat.stock_counts.cancel', $inventaire))
        ->assertSessionHas('access_denied_popup', true);

    expect($inventaire->fresh()->isClosed())->toBeTrue();

    // À H+24h après validation : le RAF annule l'inventaire avec contre-passation
    Carbon::setTestNow(Carbon::parse('2026-11-02 11:00:00'));
    $this->actingAs($raf)
        ->post(route('economat.stock_counts.cancel', $inventaire), [
            'reason' => 'Erreur matérielle dans le comptage du casier B',
        ])->assertRedirect(route('economat.stock_counts.index'));

    $inventaire->refresh();
    expect($inventaire->isCancelled())->toBeTrue()
        ->and($inventaire->cancelled_by)->toBe($raf->id)
        ->and($inventaire->cancellation_reason)->toBe('Erreur matérielle dans le comptage du casier B');

    // VÉRIFICATION COMPTABLE : Le stock est revenu à sa valeur d'origine (50 bouteilles) !
    expect((float) $this->biere->fresh()->current_stock)->toBe(50.0);

    // Un mouvement de contre-passation compensatoire a été enregistré dans le grand livre de stock
    $contreMouvement = StockMovement::where('stock_item_id', $this->biere->id)
        ->where('source_type', StockMovement::SOURCE_STOCK_COUNT)
        ->where('source_id', $inventaire->id)
        ->where('quantity', '>', 0)
        ->latest('id')
        ->first();

    expect($contreMouvement)->not->toBeNull()
        ->and((float) $contreMouvement->quantity)->toBe(8.0)
        ->and($contreMouvement->reason)->toContain('Contre-passation annulation inventaire');

    Carbon::setTestNow();
});

test('6. Verrou d annulation RAF après 48h : rejet strict et inventaire définitivement scellé', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

    $controller = agentRole('controller');
    $raf        = agentRole('manager');

    $inventaire = app(StockCountService::class)->open([], $controller);
    $ligneRiz = $inventaire->lines()->where('stock_item_id', $this->riz->id)->first();
    app(StockCountService::class)->updateCounts($inventaire, [
        $ligneRiz->id => ['counted_quantity' => 15],
    ]);
    app(StockCountService::class)->submit($inventaire, $controller);
    app(StockCountService::class)->close($inventaire, $raf);

    // À H+48h01 après validation : le RAF tente d'annuler
    Carbon::setTestNow(Carbon::parse('2026-11-03 10:01:00'));

    $this->actingAs($raf)
        ->post(route('economat.stock_counts.cancel', $inventaire), [
            'reason' => 'Trop tard pour annuler',
        ])->assertSessionHas('error');

    // L'inventaire reste clôturé et le stock reste à 15
    expect($inventaire->fresh()->isClosed())->toBeTrue()
        ->and((float) $this->riz->fresh()->current_stock)->toBe(15.0);

    Carbon::setTestNow();
});

test('7. Isolation des périmètres : l inventaire d un dépôt de service n immobilise pas le magasin central', function () {
    $service = ServiceStore::create([
        'name'       => 'Mini-Bar Étage 1',
        'code'       => 'MB-ET1',
        'department' => 'housekeeping',
    ]);

    ServiceStoreStock::create([
        'service_store_id' => $service->id,
        'stock_item_id'    => $this->biere->id,
        'current_stock'    => 10,
        'average_cost'     => 60000,
    ]);

    $controller = agentRole('controller');

    // Ouverture d'un inventaire sur le mini-bar
    $inventaireDepot = app(ServiceStoreCountService::class)->open($service, $controller);
    expect($inventaireDepot)->not->toBeNull();

    // Le magasin central n'est PAS gelé : les mouvements continuent normalement
    $stockService = app(StockService::class);
    $mouvementMagasin = $stockService->adjust(
        item: $this->biere,
        countedQuantity: 52,
        reason: 'Livraison normale magasin central',
    );

    expect($mouvementMagasin)->not->toBeNull()
        ->and((float) $this->biere->fresh()->current_stock)->toBe(52.0);
});

test('8. Dépôts de service : cycle complet Contrôleur soumet -> RAF valide -> RAF annule sous 48h avec contre-écritures', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 14:00:00'));

    $service = ServiceStore::create([
        'name'       => 'Housekeeping Central',
        'code'       => 'HK-CENTRAL',
        'department' => 'housekeeping',
    ]);

    $stock = ServiceStoreStock::create([
        'service_store_id' => $service->id,
        'stock_item_id'    => $this->riz->id,
        'current_stock'    => 6,
        'average_cost'     => 1500000,
    ]);

    $controller = agentRole('controller');
    $raf        = agentRole('manager');

    // Contrôleur ouvre et saisit 4 sacs (-2 sacs consommés)
    $inv = app(ServiceStoreCountService::class)->open($service, $controller);
    $ligne = $inv->lines()->first();
    app(ServiceStoreCountService::class)->updateCounts($inv, [
        $ligne->id => ['counted_quantity' => 4],
    ]);

    // Contrôleur soumet au RAF
    $this->actingAs($controller)
        ->post(route('economat.stores.counts.submit', $inv))
        ->assertRedirect(route('economat.stores.counts.show', $inv));

    expect($inv->fresh()->isSubmitted())->toBeTrue();

    // Contrôleur ne peut pas valider
    $this->actingAs($controller)
        ->post(route('economat.stores.counts.close', $inv))
        ->assertSessionHas('access_denied_popup', true);

    // RAF valide
    $this->actingAs($raf)
        ->post(route('economat.stores.counts.close', $inv))
        ->assertRedirect();

    expect($inv->fresh()->isClosed())->toBeTrue()
        ->and((float) $stock->fresh()->current_stock)->toBe(4.0);

    // RAF annule à H+10h : stock rétabli à 6 sacs
    Carbon::setTestNow(Carbon::parse('2026-11-02 00:00:00'));
    $this->actingAs($raf)
        ->post(route('economat.stores.counts.cancel', $inv), ['reason' => 'Erreur saisie HK'])
        ->assertRedirect();

    expect($inv->fresh()->isCancelled())->toBeTrue()
        ->and((float) $stock->fresh()->current_stock)->toBe(6.0);

    Carbon::setTestNow();
});

test('9. Restaurants : cycle complet Contrôleur soumet -> RAF valide -> RAF annule sous 48h avec contre-passation', function () {
    $restaurant = \App\Models\PointOfSale::create([
        'code'      => 'RESTO',
        'slug'      => 'resto-le-palmier',
        'name'      => 'Restaurant Le Palmier',
        'kind'      => \App\Models\PointOfSale::KIND_RESTAURATION,
        'is_active' => true,
    ]);

    $catPantry = \App\Models\RestaurantPantryCategory::create([
        'point_of_sale_id' => $restaurant->id,
        'name'             => 'Épicerie Cuisine',
    ]);

    $pantryItem = \App\Models\RestaurantPantryItem::create([
        'point_of_sale_id'              => $restaurant->id,
        'restaurant_pantry_category_id' => $catPantry->id,
        'name'                          => 'Farine de Blé 1kg',
        'unit'                          => 'kg',
        'current_stock'                 => 20,
        'average_cost'                  => 75000, // 750 FCFA
        'is_active'                     => true,
    ]);

    $controller = agentRole('controller');
    $raf        = agentRole('manager');

    // Contrôleur ouvre l'inventaire du restaurant et saisit 16kg (-4kg écart)
    $stockService = app(\App\Services\RestaurantStockService::class);
    $count = $stockService->openStockCount('Inventaire trimestriel cuisine', $restaurant);
    $ligne = $count->lines()->where('restaurant_pantry_item_id', $pantryItem->id)->first();

    $this->actingAs($controller)
        ->put(route('restaurant.stock_counts.update', $count), [
            'lines' => [$ligne->id => ['counted_quantity' => 16, 'notes' => '4kg périmés']],
        ])->assertRedirect();

    // Contrôleur soumet au RAF
    $this->actingAs($controller)
        ->post(route('restaurant.stock_counts.submit', $count))
        ->assertRedirect();

    expect($count->fresh()->isSubmitted())->toBeTrue();

    // Le chef de restaurant ne peut PAS valider ni clôturer
    $chef = agentRole('restaurant_chief');
    $chef->restaurants()->attach($restaurant->id);
    $this->actingAs($chef)
        ->post(route('restaurant.stock_counts.close', $count))
        ->assertSessionHas('access_denied_popup', true);

    // RAF valide
    $this->actingAs($raf)
        ->post(route('restaurant.stock_counts.close', $count))
        ->assertRedirect();

    expect($count->fresh()->isClosed())->toBeTrue()
        ->and((float) $pantryItem->fresh()->current_stock)->toBe(16.0);

    // RAF annule sous 48h
    $this->actingAs($raf)
        ->post(route('restaurant.stock_counts.cancel', $count), ['reason' => 'Erreur inventaire restaurant'])
        ->assertRedirect();

    expect($count->fresh()->isCancelled())->toBeTrue()
        // Stock rétabli à 20kg
        ->and((float) $pantryItem->fresh()->current_stock)->toBe(20.0);
});

test('10. Idempotence & Concurrence : une seconde validation ne rejoue jamais les ajustements de stock', function () {
    $controller = agentRole('controller');
    $raf        = agentRole('manager');

    $inventaire = app(StockCountService::class)->open([], $controller);
    $ligne = $inventaire->lines()->where('stock_item_id', $this->biere->id)->first();
    app(StockCountService::class)->updateCounts($inventaire, [
        $ligne->id => ['counted_quantity' => 40], // -10 bières
    ]);
    app(StockCountService::class)->submit($inventaire, $controller);

    // Première validation par le RAF
    app(StockCountService::class)->close($inventaire, $raf);
    expect((float) $this->biere->fresh()->current_stock)->toBe(40.0);

    // Tentative de rejouer la validation : doit être rejetée sans double écriture
    expect(fn () => app(StockCountService::class)->close($inventaire, $raf))
        ->toThrow(\RuntimeException::class);

    // Le stock reste exactement à 40 (et non 30)
    expect((float) $this->biere->fresh()->current_stock)->toBe(40.0);
});
