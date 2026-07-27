<?php

use App\Models\RoomCostItem;
use App\Models\RoomType;
use App\Models\User;
use App\Services\RoomCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function costSheetSetupUx(): array
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $manager = User::factory()->create(['role' => 'manager']);
    test()->actingAs($manager);

    return [$manager, RoomType::first()];
}

test('sans aucun coût saisi, aucune marge trompeuse n’est affichée', function () {
    [$manager, $type] = costSheetSetupUx();
    $type->update(['base_price' => 5000000]);

    $sheet = app(RoomCostingService::class)->sheetFor($type->fresh());

    // Le point du bug : le coût nul donnait « 100 % de marge » en vert.
    expect($sheet['is_configured'])->toBeFalse()
        ->and($sheet['contribution_pct'])->toBeNull()
        ->and($sheet['cost_ratio'])->toBeNull()
        ->and($sheet['net_margin_pct'])->toBeNull();
});

test('la page annonce clairement une fiche à remplir et propose un démarrage', function () {
    [$manager, $type] = costSheetSetupUx();

    test()->get(route('rooms.cost_sheets.show', $type))
        ->assertOk()
        // false = comparer au texte brut : l'apostrophe du gabarit n'est pas échappée.
        ->assertSee("Cette fiche n'est pas encore remplie", false)
        ->assertSee('Démarrer avec les postes courants')
        ->assertDontSee('100%');

    // La liste ne doit pas non plus afficher de pourcentage inventé.
    test()->get(route('rooms.cost_sheets.index'))
        ->assertOk()
        ->assertSee('À configurer');
});

test('le démarrage rapide crée les postes courants prêts à ajuster', function () {
    [$manager, $type] = costSheetSetupUx();

    test()->post(route('rooms.cost_sheets.starter', $type))->assertRedirect();

    $items = RoomCostItem::where('room_type_id', $type->id)->get();
    expect($items)->toHaveCount(5)
        ->and($items->pluck('label')->all())->toContain('Électricité', 'Eau', "Kit d'accueil");

    // Les montants sont bien convertis en centimes.
    $elec = $items->firstWhere('label', 'Électricité');
    expect($elec->unit_cost)->toBe(7500)
        ->and($elec->basis)->toBe('per_night');

    // La fiche devient configurée : la marge peut alors être calculée.
    $sheet = app(RoomCostingService::class)->sheetFor($type->fresh());
    expect($sheet['is_configured'])->toBeTrue()
        ->and($sheet['contribution_pct'])->not->toBeNull();
});

test('relancer le démarrage rapide ne duplique pas les postes', function () {
    [$manager, $type] = costSheetSetupUx();

    test()->post(route('rooms.cost_sheets.starter', $type));
    test()->post(route('rooms.cost_sheets.starter', $type));

    expect(RoomCostItem::where('room_type_id', $type->id)->count())->toBe(5);
});

test('une fois configurée, la fiche affiche la marge et non plus l’invite', function () {
    [$manager, $type] = costSheetSetupUx();
    $type->update(['base_price' => 5000000]);
    test()->post(route('rooms.cost_sheets.starter', $type));

    test()->get(route('rooms.cost_sheets.show', $type))
        ->assertOk()
        ->assertDontSee("Cette fiche n'est pas encore remplie")
        ->assertSee('Ce qui vous reste');
});

test('la réception ne peut toujours pas déclencher le démarrage rapide', function () {
    costSheetSetupUx();
    $type = RoomType::first();
    $reception = User::factory()->create(['role' => 'reception']);

    test()->actingAs($reception)
        ->post(route('rooms.cost_sheets.starter', $type))
        ->assertRedirect();

    expect(RoomCostItem::where('room_type_id', $type->id)->count())->toBe(0);
});
