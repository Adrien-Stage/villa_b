<?php

/**
 * La demande d'achat se chiffre au prix du fournisseur à la livraison :
 * une carte compare les fournisseurs de l'article (dernière livraison, prix
 * sur une période), chaque ligne prend le sien, et deux fournisseurs font
 * deux demandes.
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

    $carte = $this->actingAs(demandeur())->getJson(route('economat.purchase_requests.fournisseurs', $riz))->assertOk()->json();
    $parNom = collect($carte['fournisseurs'])->keyBy('nom');

    expect(array_keys($parNom->all()))->toBe(['Cash & Carry Bonanjo', 'Mboppi Distribution', 'Épicerie Akwa'])
        ->and($parNom['Mboppi Distribution']['derniere'])->toBe(['prix' => 2300000, 'date' => now()->subDays(5)->toDateString()])
        ->and($parNom['Cash & Carry Bonanjo']['moinsCher'])->toBeTrue()
        ->and($parNom['Épicerie Akwa']['derniere'])->toBeNull()
        ->and($parNom['Épicerie Akwa']['tarif'])->toBe(2400000);

    // Sur une période : les livraisons de chacun, leur prix le plus bas, le plus haut, le dernier.
    $periode = $this->actingAs(demandeur())->getJson(route('economat.purchase_requests.fournisseurs', [
        'item' => $riz, 'du' => now()->subDays(90)->toDateString(), 'au' => now()->toDateString(),
    ]))->assertOk()->json('fournisseurs');
    $mboppiPeriode = collect($periode)->firstWhere('nom', 'Mboppi Distribution')['periode'];

    expect($mboppiPeriode)->toBe(['livraisons' => 2, 'min' => 2100000, 'max' => 2300000, 'dernier' => 2300000]);
});

test("deux lignes chez deux fournisseurs font deux demandes, chacune au prix de sa dernière livraison", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $laiterie = Supplier::create(['name' => 'Laiterie du Littoral', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true, 'average_cost' => 1500000]);
    $beurre = StockItem::create(['name' => 'Beurre doux', 'unit' => 'paquet', 'is_active' => true]);
    livrer($mboppi, $riz, 2300000, 5);
    livrer($laiterie, $beurre, 150000, 3);
    // Un tarif saisi depuis : la dernière livraison l'emporte.
    \Illuminate\Support\Facades\DB::table('stock_item_supplier')->where('stock_item_id', $riz->id)->update(['purchase_price' => 2000000]);

    $econome = demandeur();
    $this->actingAs($econome)->get(route('economat.purchase_requests.create'))->assertOk()
        ->assertSee("Fournisseurs de l'article", false)->assertDontSee('name="supplier_id"', false)->assertDontSee('Prix moyen');

    $this->actingAs($econome)->post(route('economat.purchase_requests.store'), [
        'priority' => 'normal',
        'lines' => [
            ['stock_item_id' => $riz->id, 'supplier_id' => $mboppi->id, 'quantity_requested' => 4, 'estimated_unit_price' => 1],
            ['stock_item_id' => $beurre->id, 'supplier_id' => $laiterie->id, 'quantity_requested' => 10],
        ],
    ])->assertRedirect(route('economat.purchase_requests.index'))->assertSessionHas('success', fn ($m) => str_contains($m, '2 demandes'));

    $demandes = PurchaseRequest::with('lines')->orderBy('id')->get();
    expect($demandes)->toHaveCount(2)
        ->and($demandes->pluck('supplier_id')->all())->toBe([$mboppi->id, $laiterie->id])
        ->and((int) $demandes[0]->lines->sole()->estimated_unit_price)->toBe(2300000)
        ->and((int) $demandes[1]->lines->sole()->estimated_unit_price)->toBe(150000)
        ->and((int) $demandes[0]->total_estimated_amount)->toBe(9200000);
});

test("une ligne sans fournisseur est refusée, et rien n'est créé", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);
    $sel = StockItem::create(['name' => 'Sel fin', 'unit' => 'kg', 'is_active' => true]);

    $this->actingAs(demandeur())->post(route('economat.purchase_requests.store'), [
        'priority' => 'normal',
        'lines' => [
            ['stock_item_id' => $riz->id, 'supplier_id' => $mboppi->id, 'quantity_requested' => 1, 'estimated_unit_price' => 22000],
            ['stock_item_id' => $sel->id, 'quantity_requested' => 1],
        ],
    ])->assertSessionHasErrors('lines.1.supplier_id');

    expect(PurchaseRequest::count())->toBe(0);
});

test("la fiche d'un article ne parle plus de prix moyen des fournisseurs, mais garde le CUMP du stock", function () {
    $mboppi = Supplier::create(['name' => 'Mboppi Distribution', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);
    livrer($mboppi, $riz, 2300000, 5);

    $this->actingAs(demandeur())->get(route('economat.items.show', $riz))->assertOk()
        ->assertDontSee('Prix moyen fournisseurs')->assertDontSee('Écart vs Moyenne')
        ->assertSee('Coût moyen (CUMP)')->assertSee('Dernière livraison')->assertSee('23 000 F', false);
});
