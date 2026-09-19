<?php

/**
 * Vue consolidée des marges par type de chambre.
 *
 * L'écran existait mais présentait les types en cartes côte à côte : on vient
 * pourtant ici pour les comparer, et des cartes se comparent mal. La fiche
 * détaillée ne s'ouvre qu'au besoin, pour comprendre d'où vient un coût.
 */

use App\Models\RoomCostItem;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => activerModules(['hebergement', 'comptabilite', 'accounting']));

function typeAvecCout(string $nom, int $prix, int $cout): RoomType
{
    $type = RoomType::create([
        'name' => $nom, 'code' => \Illuminate\Support\Str::slug($nom),
        'base_price' => $prix, 'base_capacity' => 2, 'max_capacity' => 2, 'is_active' => true,
    ]);

    RoomCostItem::create([
        'room_type_id' => $type->id, 'label' => 'Blanchisserie', 'category' => 'linen',
        'basis' => RoomCostItem::BASIS_PER_NIGHT, 'quantity' => 1,
        'unit_cost' => $cout, 'is_active' => true,
    ]);

    return $type;
}

test('la vue consolidée compare les types dans un tableau', function () {
    typeAvecCout('Standard', 6_100_000, 1_000_000);
    typeAvecCout('Confort', 9_800_000, 1_200_000);

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.index'))
        ->assertOk()
        ->assertSee('Marges par type de chambre')
        ->assertSee('Standard')
        ->assertSee('Confort')
        // Un tableau, non des cartes : on compare les lignes entre elles.
        ->assertSee('<table', false)
        ->assertSee('Marge globale');
});

test('la marge globale est pondérée par le prix, non par le nombre de types', function () {
    // Une suite ne pèse pas comme une chambre standard : faire la moyenne des
    // pourcentages donnerait le même poids aux deux.
    typeAvecCout('Standard', 10_000_00, 5_000_00);   // 50 %
    typeAvecCout('Suite', 90_000_00, 9_000_00);      // 90 %

    $page = $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.index'))->getContent();

    // Pondérée : (100 000 − 14 000) / 100 000 = 86 %. Moyenne simple : 70 %.
    expect($page)->toContain('86%')->not->toContain('70%');
});

test('une fiche vide est signalée et ne produit aucun pourcentage', function () {
    RoomType::create(['name' => 'Bungalow', 'code' => 'bung', 'base_price' => 5_000_00,
        'base_capacity' => 2, 'max_capacity' => 2, 'is_active' => true]);

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.index'))
        ->assertSee('à remplir')
        ->assertDontSee('100%');
});

test('le document consolidé sort dans les quatre formats', function (string $format, string $type) {
    typeAvecCout('Standard', 6_100_000, 1_000_000);

    $reponse = $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.document', ['format' => $format]));

    $reponse->assertOk();
    expect($reponse->headers->get('content-type'))->toContain($type);
})->with([
    ['impression', 'text/html'],
    ['pdf', 'application/pdf'],
    ['excel', 'spreadsheetml'],
    ['word', 'wordprocessingml'],
]);

test('le document annonce les fiches encore vides plutôt que de fausser le total', function () {
    typeAvecCout('Standard', 6_100_000, 1_000_000);
    RoomType::create(['name' => 'Bungalow', 'code' => 'bung', 'base_price' => 5_000_00,
        'base_capacity' => 2, 'max_capacity' => 2, 'is_active' => true]);

    $page = $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.document', ['format' => 'impression']))->getContent();

    // Un total calculé sur la moitié des types n'est pas la marge de l'hôtel.
    expect($page)->toContain('restent à remplir')
        ->and($page)->toContain('les totaux');
});

test("le document trie la chambre la moins rentable en premier", function () {
    typeAvecCout('Rentable', 90_000_00, 9_000_00);
    typeAvecCout('Faible', 10_000_00, 8_000_00);

    $page = $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.document', ['format' => 'impression']))->getContent();

    // C'est ce qu'on cherche en ouvrant le document.
    expect(mb_strpos($page, 'Faible'))->toBeLessThan(mb_strpos($page, 'Rentable'));
});

test("l'export du document est journalisé", function () {
    typeAvecCout('Standard', 6_100_000, 1_000_000);

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('rooms.cost_sheets.document', ['format' => 'pdf']));

    $this->assertDatabaseHas('audit_logs', ['event_type' => 'export', 'module' => 'comptabilite']);
});

test("un rôle sans la comptabilité n'accède ni à la vue ni au document", function () {
    $agent = User::factory()->create(['role' => 'housekeeping_staff']);

    expect($this->actingAs($agent)->get(route('rooms.cost_sheets.index'))->status())->not->toBe(200)
        ->and($this->actingAs($agent)->get(route('rooms.cost_sheets.document'))->status())->not->toBe(200);
});
