<?php

/**
 * Les zones de rangement de l'économat : cave, épicerie, chambres froides,
 * magasin central. Chaque article se range dans une zone ; son stock reste
 * un seul chiffre. Les fiches de comptage, les inventaires et les alertes se
 * lisent zone par zone, et la réception dit où ranger chaque article.
 */

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StorageZone;
use App\Models\User;
use App\Services\CountSheetService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function agentDesZones(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("un établissement démarre avec le magasin central, où se rangent les articles déjà là", function () {
    $central = StorageZone::sole();

    expect($central->name)->toBe('Magasin central')
        ->and(StockItem::create(['name' => 'Riz', 'unit' => 'sac', 'is_active' => true])->storage_zone_id)->toBe($central->id);
});

test("l'économe et la direction tiennent la liste des zones ; une zone qui range des articles ne se supprime pas", function () {
    $econome = agentDesZones('econome');

    $this->actingAs($econome)->post(route('economat.zones.store'), ['name' => 'Cave'])->assertSessionHas('success');
    $this->actingAs(agentDesZones('manager'))->post(route('economat.zones.store'), ['name' => 'Chambre froide 1'])->assertSessionHas('success');
    $this->actingAs($econome)->post(route('economat.zones.store'), ['name' => 'Cave'])->assertSessionHasErrors('name');

    $cave = StorageZone::where('name', 'Cave')->sole();
    $this->actingAs($econome)->put(route('economat.zones.update', $cave), ['name' => 'Cave à boissons', 'is_active' => 1])->assertSessionHas('success');
    expect($cave->fresh()->name)->toBe('Cave à boissons');

    StockItem::create(['name' => 'Eau minérale 1,5 L', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $cave->id]);
    $this->actingAs($econome)->delete(route('economat.zones.destroy', $cave))->assertSessionHasErrors('zone');
    expect($cave->fresh())->not->toBeNull();

    $froide = StorageZone::where('name', 'Chambre froide 1')->sole();
    $this->actingAs($econome)->delete(route('economat.zones.destroy', $froide))->assertSessionHas('success');
    expect(StorageZone::find($froide->id))->toBeNull();

    $this->actingAs($econome)->get(route('settings.index', ['tab' => 'economat']))->assertOk()
        ->assertSee('Zones de rangement')->assertSee('Cave à boissons')->assertSee('Ajouter une zone de rangement');

    // Le service achats propose la zone à la création, sans tenir la liste.
    $this->actingAs(agentDesZones('purchasing_manager'))->post(route('economat.zones.store'), ['name' => 'Épicerie'])
        ->assertSessionHas('access_denied_popup', true);
    expect(StorageZone::where('name', 'Épicerie')->exists())->toBeFalse();
});

test("le service achats propose la zone à la création ; l'économe déplace l'article sans toucher à son stock", function () {
    $cave = StorageZone::create(['name' => 'Cave', 'sort_order' => 1]);
    $froide = StorageZone::create(['name' => 'Chambre froide 1', 'sort_order' => 2]);
    $acheteur = agentDesZones('purchasing_manager');
    $econome = agentDesZones('econome');

    $this->actingAs($acheteur)->post(route('economat.items.store'), ['name' => 'Sirop de menthe', 'unit' => 'pcs', 'storage_zone_id' => $cave->id])
        ->assertSessionHas('success');
    $sirop = StockItem::where('name', 'Sirop de menthe')->sole();
    expect($sirop->storage_zone_id)->toBe($cave->id);

    app(StockService::class)->recordOpening($sirop, 12, 150000);

    $this->actingAs($econome)->get(route('economat.items.index'))->assertOk()
        ->assertSee('Changer de zone')->assertSee('Toutes les zones');
    $this->actingAs($econome)->put(route('economat.items.zone', $sirop), ['storage_zone_id' => $froide->id])->assertSessionHas('success');

    $sirop->refresh();
    expect($sirop->storage_zone_id)->toBe($froide->id)
        ->and((float) $sirop->current_stock)->toBe(12.0);

    $this->actingAs($econome)->get(route('economat.items.show', $sirop))->assertOk()
        ->assertSee('Zone de rangement')->assertSee('Chambre froide 1');

    // Une zone hors service ne reçoit plus d'article.
    $cave->update(['is_active' => false]);
    $this->actingAs($econome)->put(route('economat.items.zone', $sirop), ['storage_zone_id' => $cave->id])->assertSessionHasErrors('storage_zone_id');

    // Ranger n'est pas un geste du service achats.
    $this->actingAs($acheteur)->put(route('economat.items.zone', $sirop), ['storage_zone_id' => null])->assertSessionHas('access_denied_popup', true);
    expect($sirop->fresh()->storage_zone_id)->toBe($froide->id);
});

test("la liste des articles se filtre par zone", function () {
    $cave = StorageZone::create(['name' => 'Cave', 'sort_order' => 1]);
    StockItem::create(['name' => 'Eau gazeuse 1 L', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $cave->id]);
    StockItem::create(['name' => 'Détergent 5 L', 'unit' => 'pcs', 'is_active' => true]);

    $this->actingAs(agentDesZones('econome'))->get(route('economat.items.index', ['zone' => $cave->id]))->assertOk()
        ->assertSee('Eau gazeuse 1 L')->assertDontSee('Détergent 5 L');
});

test("la fiche de comptage se tire pour une zone, ou une fiche par zone", function () {
    $central = StorageZone::sole();
    $cave = StorageZone::create(['name' => 'Cave', 'sort_order' => 1]);
    StockItem::create(['name' => 'Eau gazeuse 1 L', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $cave->id]);
    StockItem::create(['name' => 'Détergent 5 L', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $central->id]);
    StockItem::create(['name' => 'Bougies', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => null]);

    $fiches = app(CountSheetService::class);
    $noms = fn (array $fiche) => collect($fiche['groups'])->flatten(1)->pluck('name')->all();

    [$fiche] = $fiches->sheets(CountSheetService::ECONOMAT, null, CountSheetService::ARTICLES_TOUS, null, (string) $cave->id);
    expect($noms($fiche))->toBe(['Eau gazeuse 1 L'])->and($fiche['subtitle'])->toBe('Cave');

    $parZone = $fiches->sheets(CountSheetService::ECONOMAT, null, CountSheetService::ARTICLES_TOUS, null, CountSheetService::ZONE_CHAQUE);
    expect(array_column($parZone, 'subtitle'))->toBe(['Magasin central', 'Cave', 'Sans zone de rangement'])
        ->and($noms($parZone[2]))->toBe(['Bougies']);

    $this->actingAs(agentDesZones('econome'))->get(route('economat.count_sheets.index'))->assertOk()->assertSee('Une fiche par zone');
    $this->actingAs(agentDesZones('econome'))
        ->get(route('economat.count_sheets.print', ['service' => 'economat', 'zone' => CountSheetService::ZONE_CHAQUE]))
        ->assertOk()->assertSee('Eau gazeuse 1 L')->assertSee('Détergent 5 L');
});

test("un inventaire s'ouvre sur une seule zone, et se combine avec la catégorie", function () {
    $cave = StorageZone::create(['name' => 'Cave', 'sort_order' => 1]);
    $boissons = StockCategory::create(['name' => 'Boissons']);
    StockItem::create(['name' => 'Eau gazeuse 1 L', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $cave->id, 'stock_category_id' => $boissons->id]);
    StockItem::create(['name' => 'Sirop de menthe', 'unit' => 'pcs', 'is_active' => true, 'storage_zone_id' => $cave->id]);
    StockItem::create(['name' => 'Jus en brique', 'unit' => 'pcs', 'is_active' => true, 'stock_category_id' => $boissons->id]);

    $inventaire = app(StockCountService::class)->open(['storage_zone_id' => $cave->id], agentDesZones('econome'));
    expect($inventaire->lines()->with('item')->get()->pluck('item.name')->sort()->values()->all())->toBe(['Eau gazeuse 1 L', 'Sirop de menthe'])
        ->and($inventaire->perimetre())->toBe('Cave');

    app(StockCountService::class)->cancel($inventaire);

    $croise = app(StockCountService::class)->open(['storage_zone_id' => $cave->id, 'stock_category_id' => $boissons->id], agentDesZones('econome'));
    expect($croise->lines()->count())->toBe(1)
        ->and($croise->fresh()->perimetre())->toBe('Cave · Boissons');

    $this->actingAs(agentDesZones('econome'))->get(route('economat.stock_counts.show', $croise))->assertOk()->assertSee('Cave · Boissons');
});

test("la réception dit où ranger chaque article, à la saisie, sur le bon et à l'impression", function () {
    $froide = StorageZone::create(['name' => 'Chambre froide 1', 'sort_order' => 1]);
    $fournisseur = \App\Models\Supplier::create(['name' => 'Boucherie du Port', 'phone' => '690000000', 'is_active' => true]);
    $poulet = StockItem::create(['name' => 'Poulet entier', 'unit' => 'kg', 'is_active' => true, 'storage_zone_id' => $froide->id]);
    $bon = \App\Models\PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => \App\Models\PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $poulet->id, 'quantity_ordered' => 20, 'unit_price' => 250000]);
    app(\App\Services\PurchaseOrderService::class)->markTransmitted($bon, 'telephone');
    $bon->update(['sent_at' => now()->subDay()]);
    $econome = agentDesZones('econome');

    $this->actingAs($econome)->get(route('economat.receipts.create', $bon))->assertOk()->assertSee('Ranger en :')->assertSee('Chambre froide 1');

    $recu = app(\App\Services\GoodsReceiptService::class)->receive($bon->fresh('lines'), ['lines' => [$bon->lines()->first()->id => ['quantity_delivered' => 20]]], $econome);

    $this->actingAs($econome)->get(route('economat.receipts.show', $recu))->assertOk()->assertSee('Ranger en :')->assertSee('Chambre froide 1');
    $this->actingAs($econome)->get(route('economat.receipts.print', $recu))->assertOk()->assertSee('Ranger en : Chambre froide 1');
});

test("les alertes de stock se rangent par zone, dans l'ordre de la tournée", function () {
    $cave = StorageZone::create(['name' => 'Cave', 'sort_order' => 1]);
    StockItem::create(['name' => 'Eau gazeuse 1 L', 'unit' => 'pcs', 'is_active' => true, 'min_stock' => 10, 'storage_zone_id' => $cave->id]);
    StockItem::create(['name' => 'Détergent 5 L', 'unit' => 'pcs', 'is_active' => true, 'min_stock' => 4]);

    $this->actingAs(agentDesZones('econome'))->get(route('economat.index'))->assertOk()
        ->assertSeeInOrder(['Magasin central', 'Détergent 5 L', 'Cave', 'Eau gazeuse 1 L']);
});
