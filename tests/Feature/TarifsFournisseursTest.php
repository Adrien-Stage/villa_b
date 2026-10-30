<?php

/**
 * Tarifs fournisseurs : un article a plusieurs fournisseurs, chacun avec son
 * prix. Le catalogue se tient à la fiche du fournisseur ; la demande d'achat
 * prend le prix du fournisseur choisi ; la réception met à jour le tarif de
 * celui qui livre.
 */

use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseRequestService;
use App\Services\SupplierTariffService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function agentTarifs(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function articleTarifs(string $nom, array $plus = []): StockItem
{
    return StockItem::create($plus + ['name' => $nom, 'unit' => 'kg', 'is_active' => true]);
}

test("la page des fournisseurs s'ouvre et montre le catalogue de chacun avec ses prix", function () {
    $grossiste = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = articleTarifs('Riz parfumé');
    app(SupplierTariffService::class)->fixer($riz, $grossiste, 2200000);

    $page = $this->actingAs(agentTarifs('purchasing_manager'))->get(route('economat.suppliers.index'))
        ->assertOk()->assertSee('Mboppi Distribution')->assertSee('1 article(s)');

    $catalogue = $page->viewData('suppliers')->firstWhere('id', $grossiste->id)->stockItems;
    expect($catalogue->pluck('name')->all())->toBe(['Riz parfumé'])
        ->and((int) $catalogue->first()->pivot->purchase_price)->toBe(2200000);
});

test("un fournisseur se crée avec les articles qu'il livre, chacun à son prix ; un prix manque, rien n'est créé", function () {
    $riz = articleTarifs('Riz parfumé', ['unit' => 'sac']);
    $huile = articleTarifs('Huile végétale', ['unit' => 'bidon']);
    $acheteur = agentTarifs('purchasing_manager');
    $fiche = ['name' => 'Mboppi Distribution', 'is_active' => '1'];

    $this->actingAs($acheteur)->from(route('economat.suppliers.index'))->post(route('economat.suppliers.store'), $fiche + [
        'linked_items' => [['id' => $riz->id, 'price' => '22000'], ['id' => $huile->id, 'price' => '']],
    ])->assertSessionHasErrors('linked_items.1.price');
    expect(Supplier::where('name', 'Mboppi Distribution')->exists())->toBeFalse();

    $this->actingAs($acheteur)->from(route('economat.suppliers.index'))->post(route('economat.suppliers.store'), $fiche + [
        'linked_items' => [['id' => $riz->id, 'price' => '22000'], ['id' => $huile->id, 'price' => '9000']],
    ])->assertSessionHas('success');

    $grossiste = Supplier::where('name', 'Mboppi Distribution')->sole();
    expect($riz->fresh()->supplierPriceFor($grossiste))->toBe(2200000)
        ->and($huile->fresh()->supplierPriceFor($grossiste))->toBe(900000)
        // Premier fournisseur : il devient le fournisseur habituel. L'unité ne change pas.
        ->and($riz->fresh()->supplier_id)->toBe($grossiste->id)
        ->and($riz->fresh()->unit)->toBe('sac')
        ->and(DB::table('stock_item_supplier')->where('supplier_id', $grossiste->id)->whereNotNull('priced_at')->count())->toBe(2);
});

test("le formulaire d'un article ne propose plus de fournisseur, et l'enregistrement l'ignore", function () {
    $grossiste = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $econome = agentTarifs('econome');

    $this->actingAs($econome)->get(route('economat.items.index'))->assertOk()
        ->assertDontSee('name="supplier_id"', false)->assertDontSee('Fournisseur habituel');

    $this->actingAs($econome)->post(route('economat.items.store'), [
        'name' => 'Sel fin', 'unit' => 'kg', 'supplier_id' => $grossiste->id,
    ])->assertSessionHas('success');

    expect(StockItem::where('name', 'Sel fin')->sole()->supplier_id)->toBeNull();
});

test("la demande d'achat prend le prix du fournisseur choisi, pas la moyenne ; hors catalogue, le prix saisi, sans rien ajouter", function () {
    $cher = Supplier::create(['name' => 'Supérette du Port', 'is_active' => true]);
    $moinsCher = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $autre = Supplier::create(['name' => 'Laiterie du Littoral', 'is_active' => true]);
    $riz = articleTarifs('Riz parfumé');
    $tarifs = app(SupplierTariffService::class);
    $tarifs->fixer($riz, $cher, 2600000);
    $tarifs->fixer($riz, $moinsCher, 2200000);

    $econome = agentTarifs('econome');
    $service = app(PurchaseRequestService::class);

    $chezLeMoinsCher = $service->create(['supplier_id' => $moinsCher->id, 'lines' => [
        ['stock_item_id' => $riz->id, 'quantity_requested' => 2, 'estimated_unit_price' => 99999],
    ]], $econome);
    expect((int) $chezLeMoinsCher->lines->sole()->estimated_unit_price)->toBe(2200000);

    $horsCatalogue = $service->create(['supplier_id' => $autre->id, 'lines' => [
        ['stock_item_id' => $riz->id, 'quantity_requested' => 2, 'estimated_unit_price' => 25000],
    ]], $econome);
    expect((int) $horsCatalogue->lines->sole()->estimated_unit_price)->toBe(2500000)
        ->and($riz->fresh()->supplierPriceFor($autre))->toBeNull();

    // Le formulaire connaît le prix de chaque fournisseur, pour suivre le choix.
    $formulaire = $this->actingAs($econome)->get(route('economat.purchase_requests.create'))->assertOk()
        ->assertSee('Hors catalogue de ce fournisseur');
    $connus = $formulaire->viewData('items')->firstWhere('id', $riz->id)->tarifsFcfa();
    ksort($connus);
    expect($connus)->toBe([$cher->id => 26000, $moinsCher->id => 22000]);
});

test("la réception met à jour le tarif du fournisseur qui livre, à la date de livraison", function () {
    $grossiste = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = articleTarifs('Riz parfumé');
    app(SupplierTariffService::class)->fixer($riz, $grossiste, 2200000, now()->subMonths(3));

    $bon = PurchaseOrder::create(['supplier_id' => $grossiste->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $riz->id, 'quantity_ordered' => 4, 'unit_price' => 2350000]);
    app(\App\Services\PurchaseOrderService::class)->markTransmitted($bon, 'telephone');

    $livraison = now()->subHours(2)->startOfMinute();
    app(GoodsReceiptService::class)->receive($bon->fresh(), [
        'received_at' => $livraison->toDateTimeString(),
        'lines' => [$bon->lines()->first()->id => ['quantity_delivered' => 4]],
    ], agentTarifs('econome'));

    $tarif = DB::table('stock_item_supplier')->where('stock_item_id', $riz->id)->where('supplier_id', $grossiste->id)->sole();
    expect((int) $tarif->purchase_price)->toBe(2350000)
        ->and(\Illuminate\Support\Carbon::parse($tarif->priced_at)->equalTo($livraison))->toBeTrue();
});

test("retirer le fournisseur habituel donne la place au moins cher des autres", function () {
    $habituel = Supplier::create(['name' => 'Supérette du Port', 'is_active' => true]);
    $cher = Supplier::create(['name' => 'Épicerie Akwa', 'is_active' => true]);
    $moinsCher = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = articleTarifs('Riz parfumé');
    $tarifs = app(SupplierTariffService::class);
    $tarifs->fixer($riz, $habituel, 2400000);
    $tarifs->fixer($riz, $cher, 2700000);
    $tarifs->fixer($riz, $moinsCher, 2200000);
    expect($riz->fresh()->supplier_id)->toBe($habituel->id);

    $this->actingAs(agentTarifs('econome'))
        ->delete(route('economat.items.suppliers.unlink', [$riz, $habituel]))->assertSessionHas('success');

    expect($riz->fresh()->supplier_id)->toBe($moinsCher->id)
        ->and($riz->fresh()->supplierPriceFor($habituel))->toBeNull();
});

test("l'économe et le responsable des achats fixent un prix sur la fiche de l'article ; le magasinier non", function () {
    $grossiste = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = articleTarifs('Riz parfumé');

    foreach (['econome' => 2200000, 'purchasing_manager' => 2300000] as $role => $prix) {
        $this->actingAs(agentTarifs($role))->post(route('economat.items.suppliers.link', $riz), [
            'supplier_id' => $grossiste->id, 'purchase_price' => $prix / 100,
        ])->assertSessionHas('success');
        expect($riz->fresh()->supplierPriceFor($grossiste))->toBe($prix);
    }

    $this->actingAs(agentTarifs('storekeeper'))->post(route('economat.items.suppliers.link', $riz), [
        'supplier_id' => $grossiste->id, 'purchase_price' => 1,
    ])->assertSessionHas('access_denied_popup', true);
    expect($riz->fresh()->supplierPriceFor($grossiste))->toBe(2300000);
});

test("la migration retire les tarifs inventés par sa première version, et garde le reste", function () {
    $a = Supplier::create(['name' => 'Fournisseur A', 'is_active' => true]);
    $b = Supplier::create(['name' => 'Fournisseur B', 'is_active' => true]);
    $riz = articleTarifs('Riz', ['supplier_id' => $b->id, 'last_purchase_price' => 2000000]);
    $sel = articleTarifs('Sel');
    $instant = now()->subDay()->startOfSecond();
    $ligne = fn ($item, $supplier, $prix, $quand = null) => DB::table('stock_item_supplier')->insert([
        'stock_item_id' => $item->id, 'supplier_id' => $supplier->id, 'purchase_price' => $prix,
        'created_at' => $quand ?? $instant, 'updated_at' => $quand ?? $instant,
    ]);

    // Empreinte de la première version : le prix, puis +10 %, au même instant.
    $ligne($riz, $a, 2000000);
    $ligne($riz, $b, 2200000);
    $ligne($sel, $a, 50000);
    $ligne($sel, $b, 55000);
    // Un vrai tarif saisi depuis : il reste.
    $ligne($sel, Supplier::create(['name' => 'Fournisseur C', 'is_active' => true]), 30000, now());

    (require database_path('migrations/2026_10_30_090000_tarifs_fournisseurs.php'))->up();

    expect(DB::table('stock_item_supplier')->where('stock_item_id', $riz->id)->pluck('supplier_id')->all())->toBe([$b->id])
        ->and($riz->fresh()->supplierPriceFor($b))->toBe(2000000)
        ->and(DB::table('stock_item_supplier')->where('stock_item_id', $sel->id)->count())->toBe(1)
        ->and((int) DB::table('stock_item_supplier')->where('stock_item_id', $sel->id)->value('purchase_price'))->toBe(30000);
});
