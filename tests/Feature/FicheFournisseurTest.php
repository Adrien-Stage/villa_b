<?php

/**
 * La fiche d'un fournisseur : ses informations, les articles qu'il livre et
 * leur prix, ses commandes en cours, l'historique de ses livraisons — à
 * l'écran et dans les Éditions, pour qui lit les fournisseurs.
 */

use App\Editions\Catalogue;
use App\Editions\Pieces;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierTariffService;
use App\Support\FicheFournisseur;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function lecteurFiche(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un grossiste, deux articles à son catalogue, une livraison reçue et un bon en cours. */
function grossisteLivre(): Supplier
{
    $grossiste = Supplier::create([
        'name' => 'Mboppi Distribution', 'code' => 'FOU-MBOPPI', 'category' => 'food', 'city' => 'Douala',
        'phone' => '+237 233 43 55 66', 'bank_details' => 'CM21 10005 00001 1234567890 12', 'is_active' => true,
    ]);
    $concurrent = Supplier::create(['name' => 'Cash & Carry Bonanjo', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true]);
    $huile = StockItem::create(['name' => 'Huile végétale', 'unit' => 'bidon', 'is_active' => true]);

    $tarifs = app(SupplierTariffService::class);
    $tarifs->fixer($riz, $grossiste, 2200000);
    $tarifs->fixer($riz, $concurrent, 2400000);
    $tarifs->fixer($huile, $concurrent, 850000);
    $tarifs->fixer($huile, $grossiste, 900000);

    $econome = lecteurFiche('econome');
    $livre = PurchaseOrder::create(['supplier_id' => $grossiste->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $livre->lines()->create(['stock_item_id' => $riz->id, 'quantity_ordered' => 3, 'unit_price' => 2200000]);
    app(PurchaseOrderService::class)->markTransmitted($livre, 'telephone');
    app(GoodsReceiptService::class)->receive($livre->fresh(), [
        'received_at' => now()->subDays(3)->toDateTimeString(), 'delivery_note_number' => 'BL-7781',
        'lines' => [$livre->lines()->first()->id => ['quantity_delivered' => 3]],
    ], $econome);

    $enCours = PurchaseOrder::create(['supplier_id' => $grossiste->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $enCours->lines()->create(['stock_item_id' => $huile->id, 'quantity_ordered' => 10, 'unit_price' => 900000]);
    app(PurchaseOrderService::class)->markTransmitted($enCours, 'telephone');

    return $grossiste;
}

test("le responsable des achats ouvre la fiche : informations, articles et prix, bon en cours, livraisons", function () {
    $grossiste = grossisteLivre();

    $this->actingAs(lecteurFiche('purchasing_manager'))->get(route('economat.suppliers.show', $grossiste))->assertOk()
        ->assertSee('Mboppi Distribution')->assertSee('FOU-MBOPPI')->assertSee('+237 233 43 55 66')
        ->assertSee('Riz parfumé')->assertSee('22 000 F', false)->assertSee('Huile végétale')->assertSee('9 000 F', false)
        ->assertSee('Historique des livraisons')->assertSee('BL-7781')->assertSee('66 000 F', false)
        ->assertSee('Bons de commande en cours')
        // Il tient le fournisseur : il voit ses coordonnées bancaires.
        ->assertSee('CM21 10005 00001 1234567890 12');

    $fiche = FicheFournisseur::pour($grossiste, lecteurFiche('purchasing_manager'));
    $riz = $fiche['catalogue']->firstWhere('item.name', 'Riz parfumé');
    $huile = $fiche['catalogue']->firstWhere('item.name', 'Huile végétale');
    expect($riz)->toMatchArray(['habituel' => true, 'moinsCher' => true, 'autres' => 1])
        ->and($huile)->toMatchArray(['habituel' => false, 'moinsCher' => false, 'autres' => 1])
        ->and($fiche['chiffres'])->toMatchArray(['livraisons' => 1, 'montant_total' => 6600000, 'articles' => 2, 'bons_en_cours' => 1]);
});

test("le magasinier et le comptable lisent la fiche ; seul le comptable voit la banque ; la réception n'y a pas accès", function () {
    $grossiste = grossisteLivre();

    $this->actingAs(lecteurFiche('storekeeper'))->get(route('economat.suppliers.show', $grossiste))->assertOk()
        ->assertSee('Riz parfumé')->assertDontSee('CM21 10005');

    $this->actingAs(lecteurFiche('accountant'))->get(route('economat.suppliers.show', $grossiste))->assertOk()
        ->assertSee('CM21 10005 00001 1234567890 12');

    $this->actingAs(lecteurFiche('reception'))->get(route('economat.suppliers.show', $grossiste))
        ->assertRedirect()->assertSessionHas('access_denied_popup', true);
});

test("la fiche s'imprime, et se retrouve dans les Éditions par le nom du fournisseur", function () {
    $grossiste = grossisteLivre();
    $acheteur = lecteurFiche('purchasing_manager');

    $this->actingAs($acheteur)->get(route('economat.suppliers.print', $grossiste))->assertOk()
        ->assertSee('Fiche fournisseur')->assertSee('Riz parfumé')->assertSee('BL-7781')->assertSee('Livraisons des douze derniers mois');

    $pieces = app(Pieces::class)->chercher('mboppi', $acheteur);
    expect($pieces->firstWhere('sorte', 'Fiche fournisseur'))->toMatchArray([
        'numero' => 'Mboppi Distribution', 'url' => route('economat.suppliers.print', $grossiste),
    ]);

    $edition = app(Catalogue::class)->trouver('tarifs-fournisseurs');
    expect($edition->accessiblePour(lecteurFiche('storekeeper')))->toBeTrue()
        ->and($edition->accessiblePour(lecteurFiche('reception')))->toBeFalse();

    $this->actingAs($acheteur)->get(route('editions.show', ['edition' => 'tarifs-fournisseurs', 'fournisseur' => $grossiste->id]))
        ->assertOk()->assertSee('Tarifs des fournisseurs')->assertSee('Riz parfumé')->assertSee('Fournisseur habituel · Moins cher');
});
