<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        TenantSeeder::class,
        RoomTypeSeeder::class,
        RoomSeeder::class,
    ]);
});

test('un sejour en cours avec actual_check_in affiche le minuteur et l heure de depart configuree', function () {
    $tenant = Tenant::first();
    $settings = $tenant->settings ?? [];
    $settings['reception'] = ['check_in_time' => '15:00', 'check_out_time' => '11:00'];
    $tenant->update(['settings' => $settings]);

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $checkInAt = now()->subHours(3);
    $checkOutDate = now()->addDays(2);

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => $checkInAt->toDateString(),
        'check_out'         => $checkOutDate->toDateString(),
        'check_in_time'     => '15:00',
        'actual_check_in'   => $checkInAt,
        'adults_count'      => 2,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 0,
        'balance_due'       => 9000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $response = $this->actingAs($reception)->get(route('bookings.show', $booking));

    $response->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('bookingTimer(', false)
        ->assertSee("Fin prévue le {$checkOutDate->format('d/m/Y')} à 11h00")
        ->assertDontSee("Basé sur l'arrivée prévue");

    expect($booking->shouldDisplayStayTimer('11:00'))->toBeTrue()
        ->and($booking->hasOverstayedCheckout('11:00'))->toBeFalse();
});

test('un sejour de demonstration sans actual_check_in et avec date de check-out depassee affiche le minuteur de depassement', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'booking_number'    => 'DEMO-0020',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => '2026-08-31',
        'check_out'         => '2026-09-05',
        'check_in_time'     => '14:00',
        'actual_check_in'   => null,
        'actual_check_out'  => null,
        'adults_count'      => 2,
        'total_nights'      => 5,
        'price_per_night'   => 4500000,
        'total_room_amount' => 22500000,
        'total_amount'      => 22500000,
        'paid_amount'       => 0,
        'balance_due'       => 22500000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $response = $this->actingAs($reception)->get(route('bookings.show', $booking));

    $response->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('Check-out dépassé')
        ->assertSee('bookingTimer(', false)
        ->assertSee("Basé sur l'arrivée prévue le 31/08/2026 à 14h00", false)
        ->assertSee('Départ initialement prévu le 05/09/2026 à 12h00');

    expect($booking->effectiveCheckInAt('14:00')->format('Y-m-d H:i:s'))->toBe('2026-08-31 14:00:00')
        ->and($booking->scheduledCheckOutAt('12:00')->format('Y-m-d H:i:s'))->toBe('2026-09-05 12:00:00')
        ->and($booking->hasOverstayedCheckout('12:00'))->toBeTrue()
        ->and($booking->shouldDisplayStayTimer('12:00'))->toBeTrue();
});

test('un sejour en cours ayant depasse sa date de check-out affiche toujours le minuteur meme si actual_check_out a ete enregistre', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDays(4)->toDateString(),
        'check_out'         => now()->subDays(2)->toDateString(),
        'check_in_time'     => '14:00',
        'actual_check_in'   => now()->subDays(4),
        'actual_check_out'  => now()->subDays(3),
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 9000000,
        'balance_due'       => 0,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('Check-out dépassé');

    expect($booking->shouldDisplayStayTimer('12:00'))->toBeTrue();
});

test('le minuteur reste masque pour un sejour solde avant echeance ou pour les statuts non en sejour', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $settledNotOverdue = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->toDateString(),
        'check_out'         => now()->addDays(2)->toDateString(),
        'actual_check_in'   => now()->subHour(),
        'actual_check_out'  => now(),
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 9000000,
        'balance_due'       => 0,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.show', $settledNotOverdue))
        ->assertOk()
        ->assertDontSee('Minuteur de séjour en cours');

    foreach ([BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::COMPLETED, BookingStatus::CANCELLED] as $status) {
        $otherRoom = Room::where('number', '102')->first() ?? $room;
        $nonCheckedIn = Booking::create([
            'customer_id'       => $customer->id,
            'room_id'           => $otherRoom->id,
            'check_in'          => '2026-08-01',
            'check_out'         => '2026-08-03',
            'adults_count'      => 1,
            'total_nights'      => 2,
            'price_per_night'   => 4500000,
            'total_room_amount' => 9000000,
            'total_amount'      => 9000000,
            'paid_amount'       => 0,
            'balance_due'       => 9000000,
            'status'            => $status,
            'source'            => 'direct',
            'created_by'        => $reception->id,
        ]);

        $this->actingAs($reception)
            ->get(route('bookings.show', $nonCheckedIn))
            ->assertOk()
            ->assertDontSee('Minuteur de séjour en cours');
    }
});

test('la liste des reservations signale un badge de depassement sur les sejours dont le check-out est echu', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    Booking::create([
        'booking_number'    => 'DEMO-OVERDUE-1',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDays(5)->toDateString(),
        'check_out'         => now()->subDay()->toDateString(),
        'actual_check_in'   => null,
        'adults_count'      => 1,
        'total_nights'      => 4,
        'price_per_night'   => 4500000,
        'total_room_amount' => 18000000,
        'total_amount'      => 18000000,
        'paid_amount'       => 0,
        'balance_due'       => 18000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('DEMO-OVERDUE-1')
        ->assertSee('Dépassement');
});
