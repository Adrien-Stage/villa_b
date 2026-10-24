<?php

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Un article se choisit en tapant son nom ou sa référence (x-article-picker),
 * plus dans un menu déroulant : la liste devient vite trop longue pour être
 * parcourue à l'œil.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'accounting']);

    $categorie = StockCategory::create(['name' => 'Plomberie']);
    StockItem::create(['name' => 'Tuyau PVC importé de 32', 'reference' => '1PL1678', 'unit' => 'pièce', 'current_stock' => 7, 'stock_category_id' => $categorie->id]);
});

function utilisateurAvecRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("les écrans de l'économat proposent les articles par saisie, sans menu déroulant", function (string $route, string $role) {
    $this->actingAs(utilisateurAvecRole($role));

    $page = $this->get(route($route))->assertOk();

    $page->assertSee('articlePicker(', false)
        ->assertSee('role="combobox"', false)
        ->assertSee('Tuyau PVC import', false);

    expect($page->getContent())
        ->not->toMatch('/<select[^>]*name="[^"]*stock_item_id/')
        ->not->toMatch('/<select[^>]*name="article"/');
})->with([
    'demande interne'      => ['economat.requisitions.create', 'econome'],
    "demande d'achat"      => ['economat.purchase_requests.create', 'econome'],
    'réception directe'    => ['economat.receipts.direct.create', 'econome'],
    'sortie externe'       => ['economat.external_issues.create', 'econome'],
    'journal des mouvements' => ['economat.movements.index', 'econome'],
]);
