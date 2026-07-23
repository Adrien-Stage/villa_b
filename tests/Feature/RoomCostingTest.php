<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Services\RoomCostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function costingSetup(): RoomType
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    return RoomType::first();
}

test('le coût variable somme correctement les postes selon leur base de calcul', function () {
    $type = costingSetup();
    $type->update(['base_capacity' => 2, 'base_price' => 4500000]);

    RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'energy', 'label' => 'Électricité', 'basis' => 'per_night', 'quantity' => 8, 'unit_cost' => 7500]);          // 600 F
    RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'water', 'label' => 'Eau', 'basis' => 'per_guest_night', 'quantity' => 0.15, 'unit_cost' => 50000]);        // 0.15×500×2 = 150 F
    RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'linen', 'label' => 'Draps', 'basis' => 'per_stay', 'quantity' => 1, 'unit_cost' => 300000]);               // 3000 / 2.5 = 1200 F
    RoomCostSheet::create(['room_type_id' => $type->id, 'avg_length_of_stay' => 2.5]);

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());

    // 600 + 150 + 1200 = 1950 F
    expect((int) round($f['variable_cost']))->toBe(195000);
});

test('un poste lié à un article de l’économat est valorisé à son coût moyen pondéré', function () {
    $type = costingSetup();
    $type->update(['base_capacity' => 2]);

    $item = StockItem::create(['name' => 'Kit accueil', 'unit' => 'kit', 'current_stock' => 100, 'average_cost' => 20000]); // 200 F

    // unit_cost saisi volontairement faux : le lien économat doit primer.
    RoomCostItem::create([
        'room_type_id' => $type->id, 'category' => 'consumable', 'label' => 'Kit', 'basis' => 'per_guest_night',
        'quantity' => 1, 'unit_cost' => 999, 'stock_item_id' => $item->id,
    ]);
    RoomCostSheet::create(['room_type_id' => $type->id]);

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());
    $line = collect($f['groups'])->flatMap(fn ($g) => $g['lines'])->firstWhere('label', 'Kit');

    // 200 F au CMP × 2 occupants de référence = 400 F
    expect($line['unit_cost'])->toBe(20000)
        ->and($line['linked'])->toBeTrue()
        ->and((int) round($line['per_night']))->toBe(40000);
});

test('sans historique de vente, la marge se base sur le prix de base', function () {
    $type = costingSetup();
    $type->update(['base_price' => 5000000]);
    RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'energy', 'label' => 'Élec', 'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 500000]); // 5000 F

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());

    expect($f['reference_is_realized'])->toBeFalse()
        ->and($f['reference_price'])->toBe(5000000)
        ->and((int) round($f['contribution_margin']))->toBe(4500000); // 50000 - 5000
});

test('l’ADR réellement encaissé prime sur le prix de base pour la marge', function () {
    $type = costingSetup();
    $type->update(['base_price' => 5000000]);
    $room = Room::where('room_type_id', $type->id)->first();
    $customer = Customer::factory()->create();

    // Deux séjours vendus à des prix différents : ADR = moyenne = 4000 F.
    foreach ([[3500000, 2], [4500000, 4]] as [$price, $nights]) {
        Booking::create([
            'room_id' => $room->id, 'customer_id' => $customer->id,
            'status' => \App\Enums\BookingStatus::CHECKED_OUT,
            'check_in' => now(), 'check_out' => now()->addDays($nights),
            'adults_count' => 1, 'total_nights' => $nights,
            'price_per_night' => $price, 'total_room_amount' => $price * $nights,
            'total_amount' => $price * $nights, 'balance_due' => 0,
        ]);
    }

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());

    expect($f['reference_is_realized'])->toBeTrue()
        ->and($f['realized_adr'])->toBe(4000000);          // (3500 + 4500) / 2
});

test('une réservation annulée n’entre pas dans l’ADR', function () {
    $type = costingSetup();
    $room = Room::where('room_type_id', $type->id)->first();
    $customer = Customer::factory()->create();

    Booking::create([
        'room_id' => $room->id, 'customer_id' => $customer->id,
        'status' => \App\Enums\BookingStatus::CANCELLED,
        'check_in' => now(), 'check_out' => now()->addDay(),
        'adults_count' => 1, 'total_nights' => 1,
        'price_per_night' => 9900000, 'total_room_amount' => 9900000,
        'total_amount' => 9900000, 'balance_due' => 0,
    ]);

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());

    // La seule réservation est annulée → pas d'ADR réalisé, repli sur base_price.
    expect($f['reference_is_realized'])->toBeFalse();
});

test('la charge fixe optionnelle produit une marge nette distincte de la contribution', function () {
    $type = costingSetup();
    $type->update(['base_price' => 5000000]);
    RoomCostItem::create(['room_type_id' => $type->id, 'category' => 'energy', 'label' => 'Élec', 'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 200000]); // 2000 F
    RoomCostSheet::create(['room_type_id' => $type->id, 'fixed_cost_per_night' => 100000]); // 1000 F

    $f = app(RoomCostingService::class)->sheetFor($type->fresh());

    expect((int) round($f['contribution_margin']))->toBe(4800000)  // 50000 - 2000
        ->and((int) round($f['net_margin']))->toBe(4700000);       // - 1000 fixe
});
