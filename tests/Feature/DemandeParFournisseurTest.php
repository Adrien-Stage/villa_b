<?php

/**
 * L'économat exprime un besoin, sans fournisseur. Le responsable des achats
 * choisit le fournisseur de chaque article en passant la demande en bons :
 * une carte compare leurs dernières livraisons et leurs prix sur une
 * période, et chaque fournisseur reçoit son bon, au prix de sa dernière
 * livraison.
 */

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierTariffService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
    Notification::fake();
});

function demandeur(string $role = 'econome'): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Une livraison réceptionnée de l'article chez ce fournisseur, à ce prix (centimes), il y a tant de jours. */
function livrer(Supplier $fournisseur, StockItem $item, int $prix, int $ilYa): void
{
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $item->id, 'quantity_ordered' => 2, 'unit_price' => $prix]);
    app(PurchaseOrderService::class)->markTransmitted($bon, 'telephone');
    $bon->update(['sent_at' => now()->subDays($ilYa + 1)]);
    app(GoodsReceiptService::class)->receive($bon->fresh(), [
        'received_at' => now()->subDays($ilYa)->toDateTimeString(),
        'lines' => [$bon->lines()->first()->id => ['quantity_delivered' => 2]],
    ], demandeur());
}

test("la carte montre les fournisseurs de l'article : dernière livraison, date, et prix livrés sur une période", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $cash = Supplier::create(['name' => 'Cash & Carry Bonanjo', 'is_active' => true]);
    $nouveau = Supplier::create(['name' => 'Épicerie Akwa', 'is_active' => true]);
    Supplier::create(['name' => 'Laiterie du Littoral', 'is_active' => true]); // ne livre pas le riz
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);

    livrer($mboppi, $riz, 2100000, 60);
    livrer($mboppi, $riz, 2300000, 5);
    livrer($cash, $riz, 2250000, 10);
    app(SupplierTariffService::class)->fixer($riz, $nouveau, 2400000);

    $acheteur = demandeur('purchasing_manager');
    $this->actingAs(demandeur())->getJson(route('economat.purchase_requests.fournisseurs', $riz))->assertForbidden();
    $carte = $this->actingAs($acheteur)->getJson(route('economat.purchase_requests.fournisseurs', $riz))->assertOk()->json();
    $parNom = collect($carte['fournisseurs'])->keyBy('nom');

    expect(array_keys($parNom->all()))->toBe(['Cash & Carry Bonanjo', 'Mboppi Distribution', 'Épicerie Akwa'])
        ->and($parNom['Mboppi Distribution']['derniere'])->toBe(['prix' => 2300000, 'date' => now()->subDays(5)->toDateString()])
        ->and($parNom['Cash & Carry Bonanjo']['moinsCher'])->toBeTrue()
        ->and($parNom['Épicerie Akwa']['derniere'])->toBeNull()
        ->and($parNom['Épicerie Akwa']['tarif'])->toBe(2400000);

    // Sur une période : les livraisons de chacun, leur prix le plus bas, le plus haut, le dernier.
    $periode = $this->actingAs($acheteur)->getJson(route('economat.purchase_requests.fournisseurs', [
        'item' => $riz, 'du' => now()->subDays(90)->toDateString(), 'au' => now()->toDateString(),
    ]))->assertOk()->json('fournisseurs');
    $mboppiPeriode = collect($periode)->firstWhere('nom', 'Mboppi Distribution')['periode'];

    expect($mboppiPeriode)->toBe(['livraisons' => 2, 'min' => 2100000, 'max' => 2300000, 'dernier' => 2300000]);
});

test("l'économe exprime un besoin sans fournisseur ; l'acheteur choisit le fournisseur de chaque article et tire un bon par fournisseur", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $laiterie = Supplier::create(['name' => 'Laiterie du Littoral', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true, 'average_cost' => 1500000]);
    $beurre = StockItem::create(['name' => 'Beurre doux', 'unit' => 'paquet', 'is_active' => true]);
    livrer($mboppi, $riz, 2300000, 5);
    livrer($laiterie, $beurre, 150000, 3);
    // Un tarif saisi depuis : la dernière livraison l'emporte.
    \Illuminate\Support\Facades\DB::table('stock_item_supplier')->where('stock_item_id', $riz->id)->update(['purchase_price' => 2000000]);

    // L'économe : articles et quantités, ni fournisseur ni carte.
    $econome = demandeur();
    $this->actingAs($econome)->get(route('economat.purchase_requests.create'))->assertOk()
        ->assertDontSee("Fournisseurs de l'article", false)->assertDontSee('name="supplier_id"', false)->assertDontSee('Prix moyen');
    $this->actingAs($econome)->post(route('economat.purchase_requests.store'), [
        'priority' => 'normal',
        'lines' => [
            ['stock_item_id' => $riz->id, 'quantity_requested' => 4],
            ['stock_item_id' => $beurre->id, 'quantity_requested' => 10],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    $demande = PurchaseRequest::with('lines')->sole();
    expect($demande->supplier_id)->toBeNull();

    // L'acheteur approuve, puis passe la demande en bons : la carte est là, et l'économe n'y a pas la main.
    $acheteur = demandeur('purchasing_manager');
    app(\App\Services\PurchaseRequestService::class)->approve($demande, $acheteur);
    $this->actingAs($acheteur)->get(route('economat.purchase_requests.show', $demande))->assertOk()
        ->assertSee("Fournisseurs de l'article", false)->assertSee('Prendre le moins cher partout');
    $this->actingAs($econome)->post(route('economat.purchase_requests.convert', $demande), ['line_suppliers' => []])
        ->assertSessionHas('access_denied_popup', true);

    $ligneRiz = $demande->lines->firstWhere('stock_item_id', $riz->id);
    $ligneBeurre = $demande->lines->firstWhere('stock_item_id', $beurre->id);
    $this->actingAs($acheteur)->post(route('economat.purchase_requests.convert', $demande), [
        'line_suppliers' => [$ligneRiz->id => $mboppi->id, $ligneBeurre->id => $laiterie->id],
    ])->assertRedirect(route('economat.orders.index'));

    $bons = PurchaseOrder::with('lines')->where('purchase_request_id', $demande->id)->orderBy('supplier_id')->get();
    expect($bons)->toHaveCount(2)
        ->and($bons->pluck('supplier_id')->all())->toBe([$mboppi->id, $laiterie->id])
        ->and((int) $bons[0]->lines->sole()->unit_price)->toBe(2300000)
        ->and((int) $bons[1]->lines->sole()->unit_price)->toBe(150000);
});

test("la conversion refuse un article sans fournisseur, et ne tire aucun bon", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);
    $sel = StockItem::create(['name' => 'Sel fin', 'unit' => 'kg', 'is_active' => true]);
    $demande = app(\App\Services\PurchaseRequestService::class)->create(['lines' => [
        ['stock_item_id' => $riz->id, 'quantity_requested' => 1],
        ['stock_item_id' => $sel->id, 'quantity_requested' => 1],
    ]], demandeur());
    $acheteur = demandeur('purchasing_manager');
    app(\App\Services\PurchaseRequestService::class)->approve($demande, $acheteur);

    $this->actingAs($acheteur)->post(route('economat.purchase_requests.convert', $demande), [
        'line_suppliers' => [$demande->lines->firstWhere('stock_item_id', $riz->id)->id => $mboppi->id],
    ])->assertSessionHas('error', fn ($m) => str_contains($m, 'Sel fin'));

    expect(PurchaseOrder::count())->toBe(0);
});

test("la fiche d'un article ne parle plus de prix moyen des fournisseurs, mais garde le CUMP du stock", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);
    livrer($mboppi, $riz, 2300000, 5);

    $this->actingAs(demandeur())->get(route('economat.items.show', $riz))->assertOk()
        ->assertDontSee('Prix moyen fournisseurs')->assertDontSee('Écart vs Moyenne')
        ->assertSee('Coût moyen (CUMP)')->assertSee('Dernière livraison')->assertSee('23 000 F', false);
});
