<?php

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Économat › Articles : une barre de recherche filtre la liste à la saisie,
 * par le nom, la référence ou la catégorie de l'article.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat']);

    $plomberie = StockCategory::create(['name' => 'Plomberie']);
    $epicerie = StockCategory::create(['name' => 'Alimentation']);
    StockItem::create(['name' => 'Tuyau PVC 32', 'reference' => '1PL1678', 'unit' => 'pièce', 'current_stock' => 7, 'stock_category_id' => $plomberie->id]);
    StockItem::create(['name' => 'Coude PVC', 'reference' => 'PL-COU', 'unit' => 'pièce', 'current_stock' => 0, 'min_stock' => 5, 'stock_category_id' => $plomberie->id]);
    StockItem::create(['name' => 'Riz parfumé', 'reference' => 'EP-RIZ', 'unit' => 'kg', 'current_stock' => 40, 'stock_category_id' => $epicerie->id]);

    $econome = User::factory()->create(['is_active' => true]);
    $econome->roles()->sync(Role::where('slug', 'econome')->pluck('id'));
    $this->actingAs($econome->fresh());
});

test('la liste porte une barre de recherche', function () {
    $this->get(route('economat.items.index'))->assertOk()
        ->assertSee('id="recherche-articles"', false)
        ->assertSee('Rechercher par nom, référence ou catégorie');
});

test('la recherche filtre par nom, sans tenir compte de la casse', function () {
    $this->get(route('economat.items.index', ['q' => 'pvc']))->assertOk()
        ->assertSee('Tuyau PVC 32')->assertSee('Coude PVC')->assertDontSee('Riz parfumé')
        ->assertSee('2 article(s) pour « pvc »', false);
});

test('la recherche trouve aussi la référence et la catégorie', function () {
    $this->get(route('economat.items.index', ['q' => '1pl1678']))->assertOk()
        ->assertSee('Tuyau PVC 32')->assertDontSee('Coude PVC');

    $this->get(route('economat.items.index', ['q' => 'aliment']))->assertOk()
        ->assertSee('Riz parfumé')->assertDontSee('Tuyau PVC 32');
});

test('la recherche se combine avec le filtre « Sous le seuil » et le garde dans ses liens', function () {
    $this->get(route('economat.items.index', ['q' => 'pvc', 'filter' => 'alert']))->assertOk()
        ->assertSee('Coude PVC')->assertDontSee('Tuyau PVC 32')
        ->assertSee(route('economat.items.index', ['q' => 'pvc']), false);
});

test('une recherche sans résultat le dit', function () {
    $this->get(route('economat.items.index', ['q' => 'introuvable']))->assertOk()
        ->assertSee('Aucun article ne correspond à « introuvable ».', false);
});
