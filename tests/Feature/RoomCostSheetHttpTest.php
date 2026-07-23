<?php

use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function costSheetSetup(): array
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

test('le manager voit la liste des fiches et le détail d\'un type', function () {
    [$manager, $type] = costSheetSetup();

    test()->get(route('rooms.cost_sheets.index'))->assertOk();
    test()->get(route('rooms.cost_sheets.show', $type))->assertOk();
});

test('un poste de coût est créé avec conversion FCFA vers centimes', function () {
    [$manager, $type] = costSheetSetup();

    test()->post(route('rooms.cost_sheets.items.store', $type), [
        'category' => 'energy', 'label' => 'Électricité', 'basis' => 'per_night',
        'quantity' => 8, 'unit_cost' => 75,   // 75 FCFA / kWh
    ])->assertRedirect();

    $item = RoomCostItem::where('room_type_id', $type->id)->first();
    expect($item)->not->toBeNull()
        ->and($item->unit_cost)->toBe(7500)   // stocké en centimes
        ->and($item->label)->toBe('Électricité');
});

test('un poste lié à un article de l\'économat ignore le prix saisi et suit le CMP', function () {
    [$manager, $type] = costSheetSetup();
    $stock = StockItem::create(['name' => 'Savon', 'unit' => 'pièce', 'current_stock' => 50, 'average_cost' => 30000]); // 300 F

    test()->post(route('rooms.cost_sheets.items.store', $type), [
        'category' => 'consumable', 'label' => 'Savon', 'basis' => 'per_night',
        'quantity' => 2, 'unit_cost' => 1, 'stock_item_id' => $stock->id,
    ])->assertRedirect();

    $item = RoomCostItem::where('room_type_id', $type->id)->first();
    // Le coût effectif vient du CMP (300 F), pas du unit_cost saisi (1 F).
    expect($item->effectiveUnitCost())->toBe(30000)
        ->and((int) round($item->costPerNight(2, 1)))->toBe(60000); // 300 × 2 quantité
});

test('les hypothèses de la fiche sont enregistrées avec la charge fixe en centimes', function () {
    [$manager, $type] = costSheetSetup();

    test()->put(route('rooms.cost_sheets.assumptions', $type), [
        'reference_occupants' => 3,
        'avg_length_of_stay' => 2.5,
        'fixed_cost_per_night' => 1200,   // FCFA
    ])->assertRedirect();

    $sheet = RoomCostSheet::where('room_type_id', $type->id)->first();
    expect($sheet->reference_occupants)->toBe(3)
        ->and((float) $sheet->avg_length_of_stay)->toBe(2.5)
        ->and($sheet->fixed_cost_per_night)->toBe(120000);
});

test('un poste est mis à jour puis supprimé', function () {
    [$manager, $type] = costSheetSetup();
    $item = RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'water', 'label' => 'Eau', 'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 50000]);

    test()->put(route('rooms.cost_sheets.items.update', [$type, $item]), [
        'category' => 'water', 'label' => 'Eau froide', 'basis' => 'per_guest_night', 'quantity' => 0.2, 'unit_cost' => 500,
    ])->assertRedirect();
    expect($item->fresh()->label)->toBe('Eau froide')->and($item->fresh()->basis)->toBe('per_guest_night');

    test()->delete(route('rooms.cost_sheets.items.destroy', [$type, $item]))->assertRedirect();
    expect(RoomCostItem::find($item->id))->toBeNull();
});

test('un poste d\'un autre type de chambre ne peut pas être modifié via cette fiche', function () {
    [$manager, $type] = costSheetSetup();
    // Un autre type existant (le seeder en crée plusieurs), sinon on en crée un.
    $other = RoomType::where('id', '!=', $type->id)->first()
        ?? RoomType::create(['name' => 'Autre', 'code' => 'AUT', 'base_capacity' => 1, 'max_capacity' => 1, 'base_price' => 1000000, 'tenant_id' => $type->tenant_id]);
    $item = RoomCostItem::create(['room_type_id' => $other->id, 'category' => 'other', 'label' => 'X', 'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 100]);

    // On tente de le modifier en passant par la fiche de $type : incohérence → 404.
    test()->put(route('rooms.cost_sheets.items.update', [$type, $item]), [
        'category' => 'other', 'label' => 'Piraté', 'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 1,
    ])->assertNotFound();
});

test('la réception ne peut pas accéder aux fiches techniques', function () {
    costSheetSetup();
    $reception = User::factory()->create(['role' => 'reception']);
    $this->actingAs($reception);

    $this->get(route('rooms.cost_sheets.index'))->assertRedirect();
});
