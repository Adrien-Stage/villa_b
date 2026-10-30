<?php

/**
 * Un dépôt de l'économat (une réserve, une cave annexe) s'approvisionne par
 * un bon de transfert du magasin central, sans demande d'un service. La
 * marchandise change de lieu au coût moyen et reste du stock : rien ne passe
 * en charge au transfert. L'annulation la fait revenir, tant que le dépôt l'a.
 */

use App\Models\Role;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\ServiceStoreService;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function agentDuTransfert(string $role): User
{
    $user = User::factory()->create(['is_active' => true, 'name' => 'Agent ' . $role]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Une réserve de l'économat, et de l'eau au magasin central à 400 F la bouteille. */
function reserveEtEau(): array
{
    $reserve = ServiceStore::create(['name' => 'Réserve du sous-sol', 'department' => 'economat', 'is_active' => true]);
    $eau = StockItem::create(['name' => 'Eau minérale 1,5 L', 'unit' => 'btl', 'is_active' => true]);
    app(StockService::class)->recordOpening($eau, 120, 40000);

    return [$reserve, $eau->fresh()];
}

test("l'économe approvisionne la réserve de l'économat : le stock change de lieu, au coût moyen", function () {
    [$reserve, $eau] = reserveEtEau();
    $econome = agentDuTransfert('econome');

    $this->actingAs($econome)->get(route('economat.stores.show', $reserve))->assertOk()->assertSee('Approvisionner ce dépôt');
    $this->actingAs($econome)->get(route('economat.transfers.create', $reserve))->assertOk()->assertSee('Approvisionner le dépôt Réserve du sous-sol');

    $this->actingAs($econome)->post(route('economat.transfers.store', $reserve), [
        'notes' => 'Réassort de la semaine',
        'lines' => [['stock_item_id' => $eau->id, 'quantity' => 48]],
    ])->assertRedirect()->assertSessionHas('success');

    $transfert = StockTransfer::sole();
    expect($transfert->number)->toStartWith('BT-' . now()->year . '-')
        ->and($transfert->total_value)->toBe(48 * 40000)
        ->and((float) $eau->fresh()->current_stock)->toBe(72.0);

    $enReserve = ServiceStoreStock::where('service_store_id', $reserve->id)->where('stock_item_id', $eau->id)->sole();
    expect((float) $enReserve->current_stock)->toBe(48.0)
        ->and((int) $enReserve->average_cost)->toBe(40000);

    expect(StockMovement::where('source_type', StockMovement::SOURCE_TRANSFER)->where('source_id', $transfert->id)->sole()->quantity)->toEqual(-48)
        ->and(ServiceStoreMovement::where('source_type', ServiceStoreMovement::SOURCE_TRANSFER)->where('source_id', $transfert->id)->exists())->toBeTrue();

    // Même compte de stock, seul le lieu change : aucune écriture au grand livre.
    expect(app(LedgerPostingService::class)->postEconomatStock(today()))->toBeNull();

    $this->actingAs($econome)->get(route('economat.transfers.show', $transfert))->assertOk()->assertSee($transfert->number)->assertSee('Eau minérale 1,5 L');
    $this->actingAs($econome)->get(route('economat.transfers.print', $transfert))->assertOk()->assertSee('Bon de transfert')->assertSee('Réserve du sous-sol');
    $this->actingAs($econome)->get(route('economat.stores.show', $reserve))->assertOk()->assertSee($transfert->number);
});

test("un dépôt d'un autre service ne se sert pas par transfert : il passe par une demande", function () {
    $bar = ServiceStore::create(['name' => 'Bar du lobby', 'department' => 'restaurant', 'is_active' => true]);
    $eau = StockItem::create(['name' => 'Eau minérale 1,5 L', 'unit' => 'btl', 'is_active' => true]);
    app(StockService::class)->recordOpening($eau, 120, 40000);
    $econome = agentDuTransfert('econome');

    $this->actingAs($econome)->get(route('economat.stores.show', $bar))->assertOk()->assertDontSee('Approvisionner ce dépôt');
    $this->actingAs($econome)->get(route('economat.transfers.create', $bar))->assertRedirect(route('economat.stores.show', $bar));
    $this->actingAs($econome)->post(route('economat.transfers.store', $bar), ['lines' => [['stock_item_id' => $eau->id, 'quantity' => 10]]])
        ->assertSessionHas('error');

    expect(StockTransfer::count())->toBe(0)->and((float) $eau->fresh()->current_stock)->toBe(120.0);
});

test("un transfert au-delà du stock est refusé en entier", function () {
    [$reserve, $eau] = reserveEtEau();
    $sel = StockItem::create(['name' => 'Sel fin 1 kg', 'unit' => 'kg', 'is_active' => true]);
    app(StockService::class)->recordOpening($sel, 5, 50000);

    $this->actingAs(agentDuTransfert('econome'))->post(route('economat.transfers.store', $reserve), [
        'lines' => [['stock_item_id' => $eau->id, 'quantity' => 10], ['stock_item_id' => $sel->id, 'quantity' => 8]],
    ])->assertSessionHas('error');

    expect(StockTransfer::count())->toBe(0)
        ->and((float) $eau->fresh()->current_stock)->toBe(120.0)
        ->and(ServiceStoreStock::count())->toBe(0);
});

test("transféré en cartons, la quantité se ramène à l'unité de l'article", function () {
    [$reserve] = reserveEtEau();
    $gants = StockItem::create(['name' => 'Gants nitrile', 'unit' => 'pièce', 'is_active' => true]);
    $stock = app(StockService::class);
    $stock->recordOpening($gants, 400, 1000);
    $stock->definirConditionnements($gants, [['nom' => 'carton', 'contenance' => 100, 'fermes' => 4]]);

    $this->actingAs(agentDuTransfert('econome'))->post(route('economat.transfers.store', $reserve), [
        'lines' => [['stock_item_id' => $gants->id, 'quantity' => 2, 'packaging' => 'carton']],
    ])->assertSessionHas('success');

    expect((float) $gants->fresh()->current_stock)->toBe(200.0)
        ->and((float) ServiceStoreStock::where('stock_item_id', $gants->id)->sole()->current_stock)->toBe(200.0)
        ->and(StockTransfer::sole()->lines->sole()->packaging_name)->toBe('carton');
});

test("l'économe annule un transfert tant que le dépôt a la marchandise ; ce qui a été consommé ne revient pas", function () {
    [$reserve, $eau] = reserveEtEau();
    $econome = agentDuTransfert('econome');

    $this->actingAs($econome)->post(route('economat.transfers.store', $reserve), ['lines' => [['stock_item_id' => $eau->id, 'quantity' => 48]]]);
    $premier = StockTransfer::sole();

    $this->actingAs($econome)->post(route('economat.transfers.cancel', $premier), ['cancellation_reason' => 'Mauvais dépôt'])->assertSessionHas('success');
    expect($premier->fresh()->isCancelled())->toBeTrue()
        ->and((float) $eau->fresh()->current_stock)->toBe(120.0)
        ->and((float) ServiceStoreStock::where('stock_item_id', $eau->id)->sole()->current_stock)->toBe(0.0);

    // Un second transfert, dont le dépôt a déjà servi une partie.
    $this->actingAs($econome)->post(route('economat.transfers.store', $reserve), ['lines' => [['stock_item_id' => $eau->id, 'quantity' => 24]]]);
    $second = StockTransfer::latest('id')->first();
    app(ServiceStoreService::class)->issue($reserve, $eau, 6, ServiceStoreMovement::SOURCE_BENEFICIAIRE, null, 'Séminaire');

    $this->actingAs($econome)->post(route('economat.transfers.cancel', $second), ['cancellation_reason' => 'Erreur'])->assertSessionHas('error');
    expect($second->fresh()->isCancelled())->toBeFalse()
        ->and((float) $eau->fresh()->current_stock)->toBe(96.0);
});

test("le magasinier transfère sans annuler ; le service achats et le contrôle consultent sans transférer", function () {
    [$reserve, $eau] = reserveEtEau();

    $this->actingAs(agentDuTransfert('storekeeper'))->post(route('economat.transfers.store', $reserve), ['lines' => [['stock_item_id' => $eau->id, 'quantity' => 12]]])
        ->assertSessionHas('success');
    $transfert = StockTransfer::sole();

    $this->actingAs(agentDuTransfert('storekeeper'))->post(route('economat.transfers.cancel', $transfert), ['cancellation_reason' => 'Test'])
        ->assertSessionHas('access_denied_popup', true);

    foreach (['purchasing_manager', 'controller'] as $role) {
        $this->actingAs(agentDuTransfert($role))->get(route('economat.transfers.show', $transfert))->assertOk();
        $this->actingAs(agentDuTransfert($role))->post(route('economat.transfers.store', $reserve), ['lines' => [['stock_item_id' => $eau->id, 'quantity' => 1]]])
            ->assertSessionHas('access_denied_popup', true);
    }

    expect(StockTransfer::count())->toBe(1);

    // Les Éditions gardent le registre et chaque bon.
    $this->actingAs(agentDuTransfert('controller'))->get(route('editions.show', 'bons-transfert'))->assertOk()->assertSee($transfert->number);
    $this->actingAs(agentDuTransfert('econome'))->get(route('editions.index', ['q' => $transfert->number]))->assertOk()->assertSee('Bon de transfert');
});
