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
    $response->assertSee('name="check_in_time"', false);
    $response->assertSee('name="source"', false);
});

test('step 2 detects occupied rooms and same-day turnover with housekeeping availability', function () {
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

    $customer1 = Customer::factory()->create();
    $customer2 = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    // Create an existing booking checking out in 3 days
    $existingBooking = Booking::create([
        'room_id' => $room->id,
        'customer_id' => $customer1->id,
        'booking_number' => 'VB-2026-000101',
        'status' => \App\Enums\BookingStatus::CONFIRMED,
        'check_in' => now()->subDay()->format('Y-m-d'),
        'check_out' => now()->addDays(3)->format('Y-m-d'),
        'adults_count' => 1,
        'total_nights' => 4,
        'price_per_night' => 4500000,
        'total_room_amount' => 18000000,
        'total_amount' => 18000000,
        'paid_amount' => 18000000,
        'source' => 'direct',
    ]);

    $this->actingAs($user);

    // New booking starts on the departure day of existing booking (same-day rotation!)
    $newCheckIn = now()->addDays(3)->format('Y-m-d');
    $newCheckOut = now()->addDays(6)->format('Y-m-d');

    $response = $this->post(route('bookings.store'), [
        'step'          => '2',
        'customer_id'   => $customer2->id,
        'check_in'      => $newCheckIn,
        'check_out'     => $newCheckOut,
        'check_in_time' => '13:00', // earlier than 14:00 (ready time)
        'adults'        => 1,
        'children'      => 0,
        'source'        => 'phone',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.select-room');

    // Should see occupancy indicator & rotation indicators
    $response->assertSee('Occupée');
    $response->assertSee('Rotation le jour d\'arrivée');
    $response->assertSee('14:00'); // Standard ready time (12:00 + 120 min)
    $response->assertSee('Disponibilité effective post-ménage');
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

test('draft is created during step 1 and step 2, can be resumed and is completed on booking store', function () {
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

    // 1. Step 1: Select customer -> creates draft with current_step = 2
    $step1Response = $this->post(route('bookings.store'), [
        'step'        => '1',
        'customer_id' => $customer->id,
    ]);

    $step1Response->assertRedirect();
    $draft = \App\Models\BookingDraft::where('customer_id', $customer->id)->first();
    expect($draft)->not->toBeNull();
    expect($draft->current_step)->toBe(2);
    expect($draft->status)->toBe('active');

    // 2. Step 2: Choose dates and check_in_time -> updates draft with current_step = 3
    $checkIn = now()->addDays(5)->format('Y-m-d');
    $checkOut = now()->addDays(7)->format('Y-m-d');

    $step2Response = $this->post(route('bookings.store'), [
        'step'          => '2',
        'customer_id'   => $customer->id,
        'check_in'      => $checkIn,
        'check_out'     => $checkOut,
        'check_in_time' => '15:30',
        'adults'        => 2,
        'children'      => 1,
        'source'        => 'phone',
        'draft_token'   => $draft->token,
    ]);

    $step2Response->assertStatus(200);
    $draft->refresh();
    expect($draft->current_step)->toBe(3);
    expect($draft->check_in_time)->toBe('15:30');
    expect($draft->adults)->toBe(2);

    // 3. Draft list view
    $draftsListResponse = $this->get(route('bookings.drafts.index'));
    $draftsListResponse->assertStatus(200);
    $draftsListResponse->assertSee($customer->full_name);
    $draftsListResponse->assertSee('Étape 3/4');

    // 4. Resume view
    $resumeResponse = $this->get(route('bookings.drafts.resume', $draft->token));
    $resumeResponse->assertStatus(200);
    $resumeResponse->assertSee($customer->full_name);
    $resumeResponse->assertSee('15:30');

    // 5. Final booking completion marks draft as completed
    $finalResponse = $this->post(route('bookings.store'), [
        'customer_id'    => $customer->id,
        'room_id'        => $room->id,
        'check_in'       => $checkIn,
        'check_out'      => $checkOut,
        'check_in_time'  => '15:30',
        'adults_count'   => 2,
        'children_count' => 1,
        'source'         => 'phone',
        'custom_price'   => '90000',
        'payment_amount' => '30000',
        'payment_method' => 'cash',
        'draft_token'    => $draft->token,
    ]);

    $finalResponse->assertRedirect();
    $draft->refresh();
    expect($draft->status)->toBe('completed');
});

test('draft continue route directly mounts and displays the exact target step (step 3 and step 4)', function () {
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

    // 1. Create a draft at Step 3 (dates chosen, waiting for room selection)
    $draftStep3 = \App\Models\BookingDraft::create([
        'created_by'    => $user->id,
        'customer_id'   => $customer->id,
        'current_step'  => 3,
        'check_in'      => now()->addDays(4)->format('Y-m-d'),
        'check_out'     => now()->addDays(6)->format('Y-m-d'),
        'check_in_time' => '14:00',
        'adults'        => 2,
        'children'      => 0,
        'source'        => 'email',
        'status'        => 'active',
    ]);

    // Resuming step 3 directly returns select-room view with available rooms
    $responseStep3 = $this->get(route('bookings.drafts.continue', $draftStep3->token));
    $responseStep3->assertStatus(200);
    $responseStep3->assertViewIs('bookings.select-room');
    $responseStep3->assertViewHas('checkInTime', '14:00');
    $responseStep3->assertViewHas('source', 'email');
    $responseStep3->assertSee('Chambre ' . $room->number);

    // 2. Create a draft at Step 4 (room selected, waiting for confirmation & payment)
    $draftStep4 = \App\Models\BookingDraft::create([
        'created_by'    => $user->id,
        'customer_id'   => $customer->id,
        'room_id'       => $room->id,
        'current_step'  => 4,
        'check_in'      => now()->addDays(4)->format('Y-m-d'),
        'check_out'     => now()->addDays(6)->format('Y-m-d'),
        'check_in_time' => '14:00',
        'adults'        => 2,
        'children'      => 0,
        'source'        => 'email',
        'status'        => 'active',
    ]);

    // Resuming step 4 directly returns confirm view with room details and price calculation
    $responseStep4 = $this->get(route('bookings.drafts.continue', $draftStep4->token));
    $responseStep4->assertStatus(200);
    $responseStep4->assertViewIs('bookings.confirm');
    $responseStep4->assertViewHas('room');
    $responseStep4->assertViewHas('pricePerNight');
    $responseStep4->assertSee('Chambre ' . $room->number);
    $responseStep4->assertSee('Finaliser la réservation');
});

test('step 2 calculates exact ready time for room in cleaning status and detects check-in conflict', function () {
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

    // Set room to CLEANING status with status history entry
    $room->update(['status' => \App\Enums\RoomStatus::CLEANING]);
    \App\Models\RoomStatusHistory::create([
        'room_id'     => $room->id,
        'from_status' => \App\Enums\RoomStatus::DIRTY->value,
        'to_status'   => \App\Enums\RoomStatus::CLEANING->value,
        'changed_by'  => $user->id,
        'changed_at'  => today()->setTime(11, 0), // cleaned started at 11:00
    ]);

    $this->actingAs($user);

    // Arrival is today with requested check_in_time at 12:00 (before 11:00 + 120min = 13:00)
    $today = today()->format('Y-m-d');
    $tomorrow = today()->addDay()->format('Y-m-d');

    $response = $this->post(route('bookings.store'), [
        'step'          => '2',
        'customer_id'   => $customer->id,
        'check_in'      => $today,
        'check_out'     => $tomorrow,
        'check_in_time' => '12:00',
        'adults'        => 1,
        'children'      => 0,
        'source'        => 'direct',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.select-room');
    $response->assertSee('En nettoyage');
    $response->assertSee('13:00'); // 11:00 + 120m delay = 13:00
    $response->assertSee('Nettoyage en cours');
});



