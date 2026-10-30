<?php

/**
 * Le partage entre l'économat, le service achats et le contrôle de gestion :
 *  - l'économat tient le stock : il voit les articles et leurs alertes, et
 *    les transforme (ouvrir un conditionnement, découper) — le service achats
 *    en est averti ;
 *  - le service achats tient le catalogue (articles, catégories, classeur,
 *    tarifs, ajustements, reprises) et lit toute la tuile Économat ;
 *  - Contrôle & ratios et les écarts d'inventaire sont au contrôleur et au
 *    responsable financier.
 */

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\StockItemTransformed;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function agentDuPartage(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("l'économe voit les articles et leurs stocks, sans le catalogue : ni création, ni modification, ni ajustement, ni import", function () {
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'sac', 'is_active' => true, 'min_stock' => 5]);
    $econome = agentDuPartage('econome');

    $this->actingAs($econome)->get(route('economat.items.index'))->assertOk()
        ->assertSee('Riz parfumé')->assertSee('Transformation')
        ->assertDontSee('@click="openCreate()"', false)->assertDontSee('x-on:click="openAdjust(', false)
        ->assertDontSee('x-on:click="openOpening(', false)->assertDontSee('x-on:click="openEdit(', false)
        ->assertDontSee('Importer des articles depuis un CSV');

    $refuse = fn ($reponse) => $reponse->assertSessionHas('access_denied_popup', true);
    $refuse($this->actingAs($econome)->post(route('economat.items.store'), ['name' => 'Sel', 'unit' => 'kg']));
    $refuse($this->actingAs($econome)->put(route('economat.items.update', $riz), ['name' => 'Riz', 'unit' => 'sac']));
    $refuse($this->actingAs($econome)->post(route('economat.items.adjust', $riz), ['counted_quantity' => 3]));
    $refuse($this->actingAs($econome)->get(route('economat.categories.index')));
    $refuse($this->actingAs($econome)->get(route('economat.classeur.index')));
    $refuse($this->actingAs($econome)->get(route('economat.items.export')));

    expect(StockItem::where('name', 'Sel')->exists())->toBeFalse();
});

test("le service achats tient le catalogue : articles, catégories, ajustements", function () {
    $acheteur = agentDuPartage('purchasing_manager');

    $this->actingAs($acheteur)->get(route('economat.items.index'))->assertOk()
        ->assertSee('@click="openCreate()"', false)->assertSee('Importer des articles depuis un CSV');
    $this->actingAs($acheteur)->post(route('economat.categories.store'), ['name' => 'Épicerie'])->assertSessionHasNoErrors();
    $this->actingAs($acheteur)->post(route('economat.items.store'), [
        'name' => 'Sel fin', 'unit' => 'kg', 'stock_category_id' => StockCategory::where('name', 'Épicerie')->value('id'),
    ])->assertSessionHas('success');

    $sel = StockItem::where('name', 'Sel fin')->sole();
    app(StockService::class)->recordOpening($sel, 10, 30000);
    $this->actingAs($acheteur)->post(route('economat.items.adjust', $sel), ['counted_quantity' => 8, 'reason' => 'Inventaire'])
        ->assertSessionHas('success');

    expect((float) $sel->fresh()->current_stock)->toBe(8.0);
});

test("l'économe ouvre un carton ; le service achats en est averti", function () {
    Notification::fake();
    $gants = StockItem::create(['name' => 'Gants nitrile', 'unit' => 'pièce', 'is_active' => true]);
    $stock = app(StockService::class);
    $stock->recordOpening($gants, 200, 1000);
    $stock->definirConditionnements($gants, [['nom' => 'carton', 'contenance' => 100, 'fermes' => 2]]);

    $acheteur = agentDuPartage('purchasing_manager');
    $econome = agentDuPartage('econome');

    $this->actingAs($econome)->post(route('economat.items.transformation.open', $gants), ['niveau' => 'carton', 'nombre' => 1])
        ->assertSessionHas('success');

    Notification::assertSentTo($acheteur, StockItemTransformed::class,
        fn ($n) => $n->item->is($gants) && str_contains($n->toArray($acheteur)['message'], $econome->name));
    Notification::assertNotSentTo($econome, StockItemTransformed::class);
});

test("le responsable des achats lit toute la tuile Économat, sans y écrire", function () {
    $acheteur = agentDuPartage('purchasing_manager');

    foreach (['economat.index', 'economat.movements.index', 'economat.stock_counts.index', 'economat.stores.index', 'economat.receipts.index', 'economat.requisitions.index'] as $route) {
        $this->actingAs($acheteur)->get(route($route))->assertOk();
    }

    $this->actingAs($acheteur)->post(route('economat.receipts.direct.store'), [])->assertSessionHas('access_denied_popup', true);
});

test("Contrôle & ratios et les écarts d'inventaire : au contrôleur et au responsable financier seuls", function () {
    foreach (['controller', 'finance_manager'] as $role) {
        $this->actingAs(agentDuPartage($role))->get(route('economat.control.index'))->assertOk();
        $this->actingAs(agentDuPartage($role))->get(route('economat.control.variances.index'))->assertOk();
    }

    foreach (['econome', 'manager', 'quality_auditor', 'purchasing_manager', 'storekeeper'] as $role) {
        $this->actingAs(agentDuPartage($role))->get(route('economat.control.index'))->assertSessionHas('access_denied_popup', true);
        $this->actingAs(agentDuPartage($role))->get(route('economat.control.variances.index'))->assertSessionHas('access_denied_popup', true);
    }
});
