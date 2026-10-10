<?php

use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockRequisition;
use App\Models\User;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Conditionnements d'un article : un carton de 20 paquets de 10 pièces. Le
 * stock reste compté en pièces ; on suit à côté les cartons et paquets encore
 * fermés. Une sortie prend d'abord le vrac, puis les plus petites unités
 * fermées, et ouvre un paquet ou un carton quand il le faut.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat']);

    $this->gants = StockItem::create(['name' => 'Gants nitrile', 'unit' => 'pièce', 'is_active' => true]);
    app(StockService::class)->recordOpening($this->gants, 1000, 5000); // 50 F la pièce
});

function agentConditionnement(string $role, string $nom = 'Agent'): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function cartonsDeGants($test, int $cartonsFermes = 5): void
{
    $test->post(route('economat.items.transformation.packagings', $test->gants), ['niveaux' => [
        ['nom' => 'paquet', 'contenance' => 10, 'fermes' => 0],
        ['nom' => 'carton', 'contenance' => 20, 'fermes' => $cartonsFermes],
    ]])->assertSessionHas('success');
}

function etatDesGants($test): string
{
    return (string) $test->gants->fresh()->load('packagings')->stockDecompose();
}

test("l'économe définit les conditionnements : 1 carton = 20 paquets = 200 pièces", function () {
    $this->actingAs(agentConditionnement('econome'));

    $this->get(route('economat.items.transformation.show', $this->gants))->assertOk()
        ->assertSee('Conditionnements')->assertSee('pièce');
    cartonsDeGants($this);

    $niveaux = $this->gants->fresh()->packagings;
    expect($niveaux->pluck('name')->all())->toBe(['paquet', 'carton'])
        ->and((float) $niveaux[1]->factor)->toBe(200.0)
        ->and($niveaux[1]->closed_count)->toBe(5)
        ->and(etatDesGants($this))->toBe('5 cartons')
        ->and((float) $this->gants->fresh()->current_stock)->toBe(1000.0);

    $trace = StockMovement::where('source_type', StockMovement::SOURCE_PACKAGING)->sole();
    expect((float) $trace->quantity)->toBe(0.0)
        ->and($trace->reason)->toContain('1 carton = 200 pièces');
});

test('une définition incohérente est refusée', function () {
    $this->actingAs(agentConditionnement('econome'));

    $refus = fn (array $niveaux) => $this->post(route('economat.items.transformation.packagings', $this->gants), ['niveaux' => $niveaux])
        ->assertSessionHas('error');

    $refus([['nom' => 'carton', 'contenance' => 200, 'fermes' => 6]]);   // 1 200 pièces fermées pour 1 000
    $refus([['nom' => 'paquet', 'contenance' => 2.5, 'fermes' => 0]]);   // pas un nombre entier
    $refus([['nom' => 'pièce', 'contenance' => 10, 'fermes' => 0]]);     // l'unité elle-même
    $refus([['nom' => 'paquet', 'contenance' => 10], ['nom' => 'paquet', 'contenance' => 20]]);

    expect($this->gants->fresh()->packagings)->toHaveCount(0);
});

test("une sortie prend le vrac puis les petites unités, et ouvre un carton quand il le faut", function () {
    $this->actingAs(agentConditionnement('econome'));
    cartonsDeGants($this);
    $stock = app(StockService::class);

    // 1 paquet demandé : aucun paquet fermé, un carton s'ouvre.
    $m = $stock->recordOut($this->gants->fresh(), 10, StockMovement::SOURCE_MANUAL, null, 'Essai', 'paquet');
    expect(etatDesGants($this))->toBe('4 cartons · 19 paquets')
        ->and($m->packaging['ouverts'])->toBe(['carton' => 1])
        ->and($m->ouvertures())->toBe('ouverture : 1 carton');

    // 15 pièces : un paquet entier, puis un paquet ouvert dont il reste 5 pièces.
    $stock->recordOut($this->gants->fresh(), 15);
    expect(etatDesGants($this))->toBe('4 cartons · 17 paquets · 5 pièces');

    // 1 carton demandé : un carton fermé sort tel quel.
    $stock->recordOut($this->gants->fresh(), 200, StockMovement::SOURCE_MANUAL, null, null, 'carton');
    expect(etatDesGants($this))->toBe('3 cartons · 17 paquets · 5 pièces')
        ->and((float) $this->gants->fresh()->current_stock)->toBe(775.0);
});

test('une entrée en cartons reste fermée ; son annulation retire des cartons', function () {
    $this->actingAs(agentConditionnement('econome'));
    cartonsDeGants($this, 0);
    $stock = app(StockService::class);

    $stock->recordIn($this->gants->fresh(), 400, 5000, StockMovement::SOURCE_MANUAL, null, 'Livraison', 'carton');
    expect(etatDesGants($this))->toBe('2 cartons · 1 000 pièces');

    $stock->recordIn($this->gants->fresh(), 35, 5000);  // en vrac
    expect(etatDesGants($this))->toBe('2 cartons · 1 035 pièces');

    $stock->reverseIn($this->gants->fresh(), 400, 5000, StockMovement::SOURCE_MANUAL, null, 'Annulation', 'carton');
    expect(etatDesGants($this))->toBe('1 035 pièces');
});

test("un ajustement à la baisse ne laisse jamais plus d'unités fermées que de stock", function () {
    $this->actingAs(agentConditionnement('econome'));
    cartonsDeGants($this);

    app(StockService::class)->adjust($this->gants->fresh(), 450, 'Casse');
    $etat = $this->gants->fresh()->load('packagings');

    expect((float) $etat->current_stock)->toBe(450.0)
        ->and($etat->packagings->sum(fn ($n) => $n->closed_count * (float) $n->factor))->toBeLessThanOrEqual(450.0)
        ->and(etatDesGants($this))->toBe('2 cartons · 5 paquets');
});

test("l'ouverture à la main range des paquets sans changer le stock", function () {
    $this->actingAs(agentConditionnement('storekeeper'));
    $this->gants->packagings()->create(['name' => 'paquet', 'factor' => 10, 'closed_count' => 0]);
    $this->gants->packagings()->create(['name' => 'carton', 'factor' => 200, 'closed_count' => 5]);

    $this->post(route('economat.items.transformation.open', $this->gants), ['niveau' => 'carton', 'nombre' => 2])
        ->assertSessionHas('success');
    expect(etatDesGants($this))->toBe('3 cartons · 40 paquets')
        ->and((float) $this->gants->fresh()->current_stock)->toBe(1000.0);

    $this->post(route('economat.items.transformation.open', $this->gants), ['niveau' => 'carton', 'nombre' => 9])
        ->assertSessionHas('error');

    // Le magasinier ouvre, mais ne redéfinit pas les conditionnements.
    $this->post(route('economat.items.transformation.packagings', $this->gants), ['niveaux' => []])
        ->assertSessionHas('access_denied_popup', true);
});

test('une demande interne en paquets se livre en paquets, et le carton s’ouvre de lui-même', function () {
    $econome = agentConditionnement('econome', 'Clarisse Ngo');
    $this->actingAs($econome);
    cartonsDeGants($this);

    $gouvernante = agentConditionnement('housekeeping_leader', 'Grace Mballa');
    $this->actingAs($gouvernante)->get(route('economat.requisitions.create'))->assertOk()->assertSee('paquet');
    $this->post(route('economat.requisitions.store'), [
        'department' => 'housekeeping',
        'purpose'    => 'Ménage des étages',
        'lines'      => [['stock_item_id' => $this->gants->id, 'quantity' => 3, 'packaging' => 'paquet']],
    ])->assertSessionHasNoErrors();

    $demande = StockRequisition::latest('id')->first();
    $ligne = $demande->lines()->sole();
    expect((float) $ligne->quantity_requested)->toBe(30.0)
        ->and($ligne->packaging_name)->toBe('paquet')
        ->and($ligne->quantiteDemandee())->toBe('3 paquets (30 pièces)');

    $this->actingAs($econome);
    $this->get(route('economat.requisitions.show', $demande))->assertOk()->assertSee('3 paquets (30 pièces)');
    $this->post(route('economat.requisitions.approve', $demande), [])->assertSessionHas('success');
    // Le magasin ne sert que 2 paquets sur 3.
    $this->post(route('economat.requisitions.deliver', $demande), ['issued' => [$ligne->id => 2]])->assertSessionHas('success');

    expect((float) $ligne->fresh()->quantity_issued)->toBe(20.0)
        ->and($ligne->fresh()->quantiteServie())->toBe('2 paquets (20 pièces)')
        ->and(etatDesGants($this))->toBe('4 cartons · 18 paquets');

    $this->get(route('economat.requisitions.print', $demande))->assertOk()->assertSee('2 paquets (20 pièces)');
    $this->get(route('economat.movements.index', ['article' => $this->gants->id]))->assertOk()
        ->assertSee('Ouverture : 1 carton')->assertSee('4 cartons · 18 paquets');
});

test("un conditionnement inconnu de l'article est refusé à la demande", function () {
    $this->actingAs(agentConditionnement('housekeeping_leader'));

    $this->post(route('economat.requisitions.store'), [
        'department' => 'housekeeping',
        'lines'      => [['stock_item_id' => $this->gants->id, 'quantity' => 1, 'packaging' => 'palette']],
    ])->assertSessionHasErrors('lines');

    expect(StockRequisition::count())->toBe(0);
});
