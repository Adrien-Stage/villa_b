<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Invoice;
use App\Models\Room;
use App\Models\User;
use App\Services\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test("la facture de départ ne porte la chambre qu'une fois", function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    $user = User::factory()->create(['role' => 'reception']);
    $this->actingAs($user);
    CashRegisterSession::create(['user_id' => $user->id, 'module' => 'reception', 'opening_amount' => 0, 'opened_at' => now()]);

    $room = Room::where('number', '101')->first();
    $booking = Booking::create([
        'customer_id' => Customer::factory()->create()->id, 'room_id' => $room->id, 'booking_number' => 'VB-2026-000900',
        'status' => BookingStatus::CHECKED_IN, 'check_in' => now()->subDays(2)->toDateString(), 'check_out' => now()->toDateString(),
        'adults_count' => 2, 'total_nights' => 2, 'price_per_night' => 4500000, 'total_room_amount' => 9000000,
        'total_amount' => 9000000, 'paid_amount' => 9000000, 'balance_due' => 0, 'source' => 'direct',
    ]);
    // Le folio porte la chambre dès la réservation, puis les extras du séjour.
    FolioItem::create(['booking_id' => $booking->id, 'customer_id' => $booking->customer_id, 'type' => FolioItem::TYPE_ROOM,
        'description' => 'Hébergement 2 nuit(s) — Chambre 101', 'quantity' => 2, 'unit_price' => 4500000, 'total_price' => 9000000,
        'is_complimentary' => false, 'earns_points' => true, 'recorded_by' => $user->id, 'occurred_at' => now()->subDays(2)]);
    FolioItem::create(['booking_id' => $booking->id, 'customer_id' => $booking->customer_id, 'type' => FolioItem::TYPE_MINIBAR,
        'description' => 'Minibar : 2 boissons', 'quantity' => 2, 'unit_price' => 150000, 'total_price' => 300000,
        'is_complimentary' => false, 'earns_points' => true, 'recorded_by' => $user->id, 'occurred_at' => now()]);
    $booking->update(['total_amount' => 9300000, 'paid_amount' => 9300000]);

    app(CheckOutService::class)->process($booking->fresh(), $user);

    $lignes = Invoice::latest('id')->first()->items;
    expect($lignes->where('category', 'room'))->toHaveCount(1)
        ->and($lignes->where('category', 'minibar'))->toHaveCount(1)
        ->and($lignes)->toHaveCount(2);
});
