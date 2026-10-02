<?php

use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('le champ ordre s auto-incremente lors de la creation de categories tout en restant modifiable', function () {
    $econome = User::factory()->create(['role' => 'econome']);

    // Création d'une première catégorie avec ordre 0
    $res1 = $this->actingAs($econome)->post(route('economat.categories.store'), [
        'name'          => 'Catégorie Zéro',
        'stock_account' => '332000',
        'sort_order'    => 0,
    ]);
    $res1->assertRedirect();

    $cat0 = StockCategory::where('name', 'Catégorie Zéro')->first();
    expect($cat0->sort_order)->toBe(0);

    // L'index doit maintenant proposer le prochain ordre (1)
    $resIndex = $this->actingAs($econome)->get(route('economat.categories.index'));
    $resIndex->assertOk()
        ->assertViewHas('nextSortOrder', 1);

    // Création d'une seconde catégorie avec l'ordre 1
    $res2 = $this->actingAs($econome)->post(route('economat.categories.store'), [
        'name'          => 'Catégorie Un',
        'stock_account' => '332000',
        'sort_order'    => 1,
    ]);
    $res2->assertRedirect();

    $cat1 = StockCategory::where('name', 'Catégorie Un')->first();
    expect($cat1->sort_order)->toBe(1);

    // L'index propose maintenant l'ordre 2
    $resIndex2 = $this->actingAs($econome)->get(route('economat.categories.index'));
    $resIndex2->assertOk()
        ->assertViewHas('nextSortOrder', 2);
});

test('il est impossible de valider un ordre deja attribue a une autre categorie', function () {
    $econome = User::factory()->create(['role' => 'econome']);

    StockCategory::create([
        'name'       => 'Catégorie Existante',
        'sort_order' => 0,
    ]);

    // Tentative de créer une catégorie avec l'ordre 0 qui est déjà pris
    $resDuplicate = $this->actingAs($econome)->post(route('economat.categories.store'), [
        'name'       => 'Nouvelle Catégorie Conflit',
        'sort_order' => 0,
    ]);

    $resDuplicate->assertSessionHasErrors('sort_order');
    expect(StockCategory::where('name', 'Nouvelle Catégorie Conflit')->exists())->toBeFalse();
});

test('la mise a jour vers un ordre deja attribue est bloquee mais conserver son ordre est accepte', function () {
    $econome = User::factory()->create(['role' => 'econome']);

    $catA = StockCategory::create(['name' => 'Cat A', 'sort_order' => 0]);
    $catB = StockCategory::create(['name' => 'Cat B', 'sort_order' => 1]);

    // Conserver son propre ordre fonctionne
    $this->actingAs($econome)->put(route('economat.categories.update', $catA), [
        'name'       => 'Cat A Modifiée',
        'sort_order' => 0,
    ])->assertRedirect()->assertSessionHas('success');

    // Tenter d'utiliser l'ordre de Cat B échoue
    $this->actingAs($econome)->put(route('economat.categories.update', $catA), [
        'name'       => 'Cat A Modifiée Conflit',
        'sort_order' => 1,
    ])->assertSessionHasErrors('sort_order');
});
