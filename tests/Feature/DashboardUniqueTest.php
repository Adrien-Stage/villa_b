<?php

/**
 * Un seul écran porte le nom « Tableau de bord », et il s'adapte au rôle.
 *
 * L'économe en voyait deux : celui de la section Général et celui de la
 * section Économat. Ce n'était pas qu'un doublon de libellé — le tableau de
 * bord général ne comportait aucun bloc pour lui, et se réduisait à une carte
 * « Bienvenue » vide. Le second écran compensait ce vide.
 */

use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Libellés des liens de la barre latérale, pour le rôle donné. */
function liensSidebar(string $role): array
{
    activerModules(['economat', 'comptabilite', 'ledger', 'accounting', 'analytics', 'discussions']);

    $html = test()->actingAs(User::factory()->create(['role' => $role]))
        ->get('/dashboard')->getContent();

    // Hors de la barre latérale, « Tableau de bord » est légitime : c'est le
    // titre de la page dans l'en-tête.
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);
    preg_match_all('#<span class="sidebar-libelle">(.*?)</span>#s', $nav[0] ?? '', $libelles);

    return array_map('trim', $libelles[1] ?? []);
}

test('aucun rôle ne voit deux fois « Tableau de bord »', function (string $role) {
    $liens = liensSidebar($role);

    expect(array_count_values($liens)['Tableau de bord'] ?? 0)
        ->toBe(1, "Le rôle {$role} voit : " . implode(' · ', $liens));
})->with(['econome', 'accountant', 'manager', 'reception', 'housekeeping_leader']);

test("l'économat garde son écran, sous un nom qui lui est propre", function () {
    expect(liensSidebar('econome'))->toContain("Vue d'ensemble");
});

test("le tableau de bord de l'économe porte ses propres indicateurs", function () {
    activerModules(['economat']);
    StockItem::create([
        'name' => 'Riz parfumé', 'unit' => 'kg', 'current_stock' => 4,
        'min_stock' => 20, 'average_cost' => 90000, 'is_active' => true,
    ]);

    $reponse = $this->actingAs(User::factory()->create(['role' => 'econome']))->get('/dashboard');

    $reponse->assertOk()
        ->assertSee('Articles sous seuil')
        ->assertSee('Valeur du stock')
        ->assertSee('Demandes en attente')
        // La carte de repli ne doit plus apparaître : elle signalait un écran vide.
        ->assertDontSee('Bienvenue');
});

test('les articles à réapprovisionner sont listés, rupture distinguée', function () {
    activerModules(['economat']);
    StockItem::create([
        'name' => 'Huile de table', 'unit' => 'L', 'current_stock' => 0,
        'min_stock' => 10, 'average_cost' => 120000, 'is_active' => true,
    ]);
    StockItem::create([
        'name' => 'Sucre en poudre', 'unit' => 'kg', 'current_stock' => 3,
        'min_stock' => 15, 'average_cost' => 60000, 'is_active' => true,
    ]);
    // Au-dessus du seuil : ne doit pas remonter dans les alertes.
    StockItem::create([
        'name' => 'Farine', 'unit' => 'kg', 'current_stock' => 200,
        'min_stock' => 10, 'average_cost' => 50000, 'is_active' => true,
    ]);

    $reponse = $this->actingAs(User::factory()->create(['role' => 'econome']))->get('/dashboard');

    $reponse->assertSee('À réapprovisionner (Économat)', false)
        ->assertSee('Huile de table')
        ->assertSee('Rupture')
        ->assertSee('Sucre en poudre')
        ->assertDontSee('Farine');
});

test("un rôle sans économat ne reçoit pas ses indicateurs", function () {
    activerModules(['economat']);
    StockItem::create([
        'name' => 'Draps', 'unit' => 'pièce', 'current_stock' => 1,
        'min_stock' => 10, 'average_cost' => 500000, 'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'reception']))->get('/dashboard')
        ->assertDontSee('Valeur du stock')
        ->assertDontSee('À réapprovisionner (Économat)', false);
});
