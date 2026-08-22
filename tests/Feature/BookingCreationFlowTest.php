<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking wizard step 1 displays check-in time and preserves source selection', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);

    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();

    $this->actingAs($user);

    // 1. Loading create view with source = phone and check_in_time = 16:30
    $response = $this->get(route('bookings.create', [
        'customer_id'   => $customer->id,
        'check_in'      => now()->addDays(2)->format('Y-m-d'),
        'check_out'     => now()->addDays(4)->format('Y-m-d'),
        'check_in_time' => '16:30',
        'adults'        => 2,
        'source'        => 'phone',
    ]));

    $response->assertStatus(200);
    $response->assertSee('name="check_in_time"', false);
    $response->assertSee('value="16:30"', false);
    $response->assertSee('<option value="phone" selected>', false);
});

test('booking step 2 stores check_in_time and source and passes them to room selection', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);

    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();

    $this->actingAs($user);

    $checkIn = now()->addDays(2)->format('Y-m-d');
    $checkOut = now()->addDays(4)->format('Y-m-d');

    // Submitting step 2 form
    $response = $this->post(route('bookings.store'), [
        'step'          => '2',
        'customer_id'   => $customer->id,
        'check_in'      => $checkIn,
        'check_out'     => $checkOut,
        'check_in_time' => '15:45',
        'adults'        => 2,
        'children'      => 0,
        'source'        => 'phone',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.select-room');
    $response->assertViewHas('checkInTime', '15:45');
    $response->assertViewHas('source', 'phone');
    $response->assertSee('name="check_in_time" value="15:45"', false);
    $response->assertSee('name="source" value="phone"', false);
    $response->assertSee('source=phone', false);
    $response->assertSee('check_in_time=15%3A45', false);
});

test('full booking flow preserves check_in_time and phone source to final database record', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);

    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $this->actingAs($user);

    $checkIn = now()->addDays(3)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d');

    // Step 3 -> Step 4 confirmation
    $step3Response = $this->post(route('bookings.store'), [
        'step'          => '3',
        'customer_id'   => $customer->id,
        'room_id'       => $room->id,
        'check_in'      => $checkIn,
        'check_out'     => $checkOut,
        'check_in_time' => '16:15',
        'adults_count'  => 2,
        'children_count'=> 0,
        'source'        => 'phone',
    ]);

    $step3Response->assertStatus(200);
    $step3Response->assertViewIs('bookings.confirm');
    $step3Response->assertViewHas('checkInTime', '16:15');
    $step3Response->assertViewHas('source', 'phone');
    $step3Response->assertSee('name="check_in_time" value="16:15"', false);
    $step3Response->assertSee('name="source" value="phone"', false);

    // Final submission
    $finalResponse = $this->post(route('bookings.store'), [
        'customer_id'    => $customer->id,
        'room_id'        => $room->id,
        'check_in'       => $checkIn,
        'check_out'      => $checkOut,
        'check_in_time'  => '16:15',
        'adults_count'   => 2,
        'children_count' => 0,
        'source'         => 'phone',
        'custom_price'   => '90000',
        'payment_amount' => '30000',
        'payment_method' => 'cash',
    ]);

    $finalResponse->assertRedirect();

    $booking = Booking::latest()->first();
    expect($booking->source)->toBe('phone');
    expect($booking->check_in_time)->toBe('16:15');

    // Check show view
    $showResponse = $this->get(route('bookings.show', $booking));
    $showResponse->assertStatus(200);
    $showResponse->assertSee('16:15');
    $showResponse->assertSee('phone');
});
