<?php

use App\Models\JournalEntry;
use App\Models\PointOfSale;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\Role;
use App\Models\StockCut;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Découpe vers le garde-manger : le chef prend 30 kg des 180 kg de poulet reçus
 * et les répartit en quarts, demis, entiers et carcasses. Les 30 kg sortent de
 * l'économat au coût moyen ; la valeur se partage au poids entre les portions,
 * carcasses comprises. On recommence sur ce qui reste.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'restaurant']);
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00'));

    $this->restaurant = PointOfSale::create(['code' => 'PHA', 'slug' => 'le-phare', 'name' => 'Le Phare',
        'kind' => PointOfSale::KIND_RESTAURATION, 'is_active' => true, 'sort_order' => 1]);
    $this->poulet = StockItem::create(['name' => 'Poulet entier 1,5 kg', 'unit' => 'kg', 'is_active' => true]);
    app(StockService::class)->recordOpening($this->poulet, 180, 250000); // 2 500 F le kg
});

function agentDecoupe(string $role): User
{
    $user = User::factory()->create(['name' => 'Clarisse Ngo', 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function decouper($test, float $quantite, array $portions, array $autres = [])
{
    return $test->post(route('economat.items.transformation.cut', $test->poulet), array_merge([
        'point_of_sale_id' => $test->restaurant->id,
        'quantity'         => $quantite,
        'lines'            => array_map(fn ($nom, $qte) => ['pantry_item_id' => '', 'nom' => $nom, 'quantity' => $qte],
            array_keys($portions), $portions),
    ], $autres));
}

test("30 kg de poulet découpés : l'économat baisse, les portions entrent au garde-manger, valorisées au poids", function () {
    $this->actingAs(agentDecoupe('econome'));

    $this->get(route('economat.items.transformation.show', $this->poulet))->assertOk()
        ->assertSee('Découpe vers le garde-manger')->assertSee('Le Phare');

    decouper($this, 30, ['Quart de poulet' => 10, 'Demi-poulet' => 8, 'Poulet entier' => 7, 'Carcasses' => 5])
        ->assertRedirect()->assertSessionHas('success');

    $decoupe = StockCut::sole();
    expect($decoupe->number)->toBe('DEC-2026-0001')
        ->and((float) $this->poulet->fresh()->current_stock)->toBe(150.0)
        ->and($decoupe->total_value)->toBe(30 * 250000)
        ->and($decoupe->lines->pluck('value', 'label')->all())->toBe([
            'Quart de poulet' => 2500000, 'Demi-poulet' => 2000000, 'Poulet entier' => 1750000, 'Carcasses' => 1250000,
        ]);

    $quarts = RestaurantPantryItem::where('name', 'Quart de poulet')->sole();
    expect($quarts->point_of_sale_id)->toBe($this->restaurant->id)
        ->and($quarts->unit)->toBe('kg')
        ->and((float) $quarts->current_stock)->toBe(10.0)
        ->and((float) $quarts->average_cost)->toBe(250000.0);

    expect(StockMovement::where('source_type', StockMovement::SOURCE_CUT)->sole()->source_id)->toBe($decoupe->id)
        ->and(RestaurantPantryMovement::where('reason', RestaurantPantryMovement::REASON_TRANSFER_IN)->count())->toBe(4);

    $this->get(route('economat.cuts.show', $decoupe))->assertOk()->assertSee('DEC-2026-0001')->assertSee('Carcasses');
    $this->get(route('economat.cuts.print', $decoupe))->assertOk()->assertSee('Bon de découpe')->assertSee('Quart de poulet');
    $this->get(route('economat.movements.index', ['article' => $this->poulet->id]))->assertOk()->assertSee('Découpe DEC-2026-0001');
    $this->get(route('editions.index', ['q' => 'DEC-2026']))->assertOk()->assertSee('DEC-2026-0001')->assertSee('Bon de découpe');
});

test('on recommence sur le reste : une freinte reporte son coût sur les portions gardées', function () {
    $this->actingAs(agentDecoupe('econome'));
    decouper($this, 30, ['Quart de poulet' => 10, 'Demi-poulet' => 8, 'Poulet entier' => 7, 'Carcasses' => 5]);

    // Les 150 kg restants : 140 kg de portions, 10 kg perdus à la découpe.
    decouper($this, 150, ['Quart de poulet' => 80, 'Demi-poulet' => 60])->assertSessionHas('success');

    $deuxieme = StockCut::latest('id')->first();
    expect((float) $this->poulet->fresh()->current_stock)->toBe(0.0)
        ->and($deuxieme->freinte())->toBe(10.0)
        ->and($deuxieme->lines->sum('value'))->toBe(150 * 250000)
        // 37 500 000 c sur 140 kg : 267 857 c le kg au lieu de 250 000.
        ->and((int) round((float) $deuxieme->lines->first()->unit_cost))->toBe(267857);

    $quarts = RestaurantPantryItem::where('name', 'Quart de poulet')->sole();
    expect((float) $quarts->current_stock)->toBe(90.0);
});

test("une portion suivie en grammes au garde-manger reçoit des grammes", function () {
    $this->actingAs(agentDecoupe('econome'));
    $blanc = RestaurantPantryItem::create(['point_of_sale_id' => $this->restaurant->id, 'name' => 'Blanc de poulet',
        'unit' => 'g', 'purchase_unit' => 'g', 'purchase_conversion' => 1, 'current_stock' => 0, 'min_stock' => 0,
        'cost_price' => 0, 'average_cost' => 0, 'is_prepared' => false, 'is_active' => true]);

    $this->post(route('economat.items.transformation.cut', $this->poulet), [
        'point_of_sale_id' => $this->restaurant->id,
        'quantity'         => 2,
        'lines'            => [['pantry_item_id' => $blanc->id, 'quantity' => 2]],
    ])->assertSessionHas('success');

    expect((float) $blanc->fresh()->current_stock)->toBe(2000.0)
        ->and((float) $blanc->fresh()->average_cost)->toBe(250.0);
});

test('une découpe impossible ne sort rien', function () {
    $this->actingAs(agentDecoupe('econome'));

    decouper($this, 30, ['Quart de poulet' => 20, 'Demi-poulet' => 15])->assertSessionHas('error');    // 35 kg pour 30
    decouper($this, 300, ['Quart de poulet' => 100])->assertSessionHas('error');                      // plus que le stock
    decouper($this, 10, ['' => 10])->assertSessionHas('error');                                       // portion sans nom

    expect(StockCut::count())->toBe(0)
        ->and((float) $this->poulet->fresh()->current_stock)->toBe(180.0)
        ->and(RestaurantPantryItem::count())->toBe(0);
});

test("la découpe passe au grand livre comme un transfert vers la cuisine", function () {
    $this->actingAs(agentDecoupe('econome'));
    decouper($this, 30, ['Quart de poulet' => 30]);

    app(LedgerPostingService::class)->postEconomatStock(\Illuminate\Support\Carbon::parse('2026-10-11'));
    $ecriture = JournalEntry::query()->where('schema', 'like', LedgerPostingService::SCHEMA_ECONOMAT_STOCK . '%')->with('lines')->sole();

    expect($ecriture->lines->pluck('label')->unique()->values()->all())->toBe(['Transferts vers la cuisine'])
        ->and($ecriture->lines->firstWhere('debit', '>', 0)->debit)->toBe(30 * 250000);
});

test("seul l'économe découpe ; le magasinier et la direction consultent le bon", function () {
    $this->actingAs(agentDecoupe('econome'));
    decouper($this, 30, ['Quart de poulet' => 30]);
    $decoupe = StockCut::sole();

    $this->actingAs(agentDecoupe('storekeeper'));
    decouper($this, 10, ['Quart de poulet' => 10])->assertSessionHas('access_denied_popup', true);
    $this->get(route('economat.cuts.show', $decoupe))->assertOk();

    $this->actingAs(agentDecoupe('manager'))->get(route('economat.cuts.print', $decoupe))->assertOk();
    expect(StockCut::count())->toBe(1);
});
