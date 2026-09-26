<?php

use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BreakfastPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('breakfast pricing service returns default settings and calculates correctly', function () {
    $service = app(BreakfastPricingService::class);
    $brackets = $service->getAgeBrackets();

    expect(collect($brackets)->pluck('id')->all())->toBe(['0_2', '2_10', '11_15', '16_17']);
    expect($service->getAdultPrice())->toBe(5000);

    // 2 adults (2 * 5000 = 10 000)
    // 4 children: 2 of 2-10 ans (2 * 2500 = 5000), 2 of 11-15 ans (2 * 4000 = 8000)
    // Total daily = 10 000 + 13 000 = 23 000 FCFA
    // For 3 nights = 69 000 FCFA
    $calculation = $service->calculateBreakfast(3, 2, ['2_10', '2_10', '11_15', '11_15']);

    expect($calculation['daily_total'])->toBe(23000);
    expect($calculation['stay_total'])->toBe(69000);
    expect($calculation['adults_count'])->toBe(2);
    expect($calculation['children_count'])->toBe(4);
    expect($calculation['children_breakdown'][0]['count'])->toBe(2);
    expect($calculation['children_breakdown'][1]['count'])->toBe(2);
});

test('tenant custom breakfast settings override default pricing and age brackets', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
    ]);

    $tenant = Tenant::first();
    $tenant->settings = array_merge($tenant->settings ?? [], [
        'reception' => [
            'breakfast' => [
                'adult_price' => 6000,
                'age_brackets' => [
                    [
                        'id' => '0_4',
                        'label' => 'Petit enfant (0 à 4 ans)',
                        'min_age' => 0,
                        'max_age' => 4,
                        'price' => 0,
                    ],
                    [
                        'id' => '5_12',
                        'label' => 'Enfant (5 à 12 ans)',
                        'min_age' => 5,
                        'max_age' => 12,
                        'price' => 3000,
                    ],
                ],
            ],
        ],
    ]);
    $tenant->save();

    $service = app(BreakfastPricingService::class);
    expect($service->getAdultPrice($tenant->id))->toBe(6000);
    $brackets = $service->getAgeBrackets($tenant->id);
    expect(collect($brackets)->pluck('id')->all())->toBe(['0_4', '5_12']);

    // 1 adult (6000) + 2 children of 5-12 ans (2 * 3000 = 6000) = 12 000 / day
    $calc = $service->calculateBreakfast(2, 1, ['5_12', '5_12'], $tenant->id);
    expect($calc['daily_total'])->toBe(12000);
    expect($calc['stay_total'])->toBe(24000);
});

test('booking step 2 validates and passes children_ages to select-room step', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $this->actingAs($user);

    $checkIn = now()->addDays(2)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d');

    $response = $this->post(route('bookings.store'), [
        'step'          => '2',
        'customer_id'   => $customer->id,
        'check_in'      => $checkIn,
        'check_out'     => $checkOut,
        'check_in_time' => '14:00',
        'adults'        => 2,
        'children'      => 4,
        'children_ages' => ['2_10', '2_10', '11_15', '11_15'],
        'source'        => 'direct',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.select-room');
    $response->assertViewHas('childrenAges', ['2_10', '2_10', '11_15', '11_15']);
    $response->assertSee('name="children_ages[]"', false);
});

test('booking step 3 passes children_ages and calculates breakfast for confirm step', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room = Room::first();
    $this->actingAs($user);

    $checkIn = now()->addDays(2)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d');

    $response = $this->post(route('bookings.store'), [
        'step'          => '3',
        'customer_id'   => $customer->id,
        'room_id'       => $room->id,
        'check_in'      => $checkIn,
        'check_out'     => $checkOut,
        'check_in_time' => '14:00',
        'adults_count'  => 2,
        'children_count'=> 4,
        'children_ages' => ['2_10', '2_10', '11_15', '11_15'],
        'source'        => 'direct',
    ]);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.confirm');
    $response->assertViewHas('childrenAges', ['2_10', '2_10', '11_15', '11_15']);
    $response->assertViewHas('breakfastCalculation');

    $calc = $response->viewData('breakfastCalculation');
    expect($calc['stay_total'])->toBe(69000);
});

test('storeBooking records children_ages, extras_amount, and breakfast folio item when breakfast is included', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room = Room::first();
    $this->actingAs($user);

    $checkIn = now()->addDays(2)->format('Y-m-d');
    $checkOut = now()->addDays(5)->format('Y-m-d'); // 3 nights

    $baseRoomPricePerNight = (int) $room->roomType->base_price / 100;
    $totalRoomAmount = $baseRoomPricePerNight * 3;

    // Breakfast calculation: 3 nights * (2 adults * 5000 + 2 * 2500 + 2 * 4000) = 69 000 FCFA
    $breakfastStayTotal = 69000;
    $netTotal = $totalRoomAmount + $breakfastStayTotal;
    $paymentAmount = (int) ceil($netTotal * 0.3); // 30% min deposit

    $response = $this->post(route('bookings.store'), [
        'step'              => '4',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => $checkIn,
        'check_out'         => $checkOut,
        'check_in_time'     => '14:00',
        'adults_count'      => 2,
        'children_count'    => 4,
        'children_ages'     => ['2_10', '2_10', '11_15', '11_15'],
        'include_breakfast' => '1',
        'breakfast_amount'  => $breakfastStayTotal,
        'custom_price'      => $totalRoomAmount,
        'payment_amount'    => $paymentAmount,
        'payment_method'    => 'cash',
        'source'            => 'direct',
    ]);

    $response->assertRedirect();
    $booking = Booking::latest('id')->first();
    expect($booking)->not->toBeNull();
    expect($booking->children_count)->toBe(4);
    expect($booking->children_ages)->toBe(['2_10', '2_10', '11_15', '11_15']);
    expect($booking->extras_amount)->toBe($breakfastStayTotal * 100);

    // Verify FolioItem for restaurant/petits-déjeuners was created
    $breakfastFolio = FolioItem::where('booking_id', $booking->id)
        ->where('type', FolioItem::TYPE_RESTAURANT)
        ->first();

    expect($breakfastFolio)->not->toBeNull();
    expect($breakfastFolio->total_price)->toBe($breakfastStayTotal * 100);
    expect($breakfastFolio->notes)->toContain('2 de 2 à 10 ans');
});

test('summary page displays children ages breakdown and breakfast extras', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room = Room::first();
    $this->actingAs($user);

    $booking = Booking::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_number'    => 'RES-TEST-BREAKFAST-01',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->addDays(2),
        'check_in_time'     => '14:00',
        'check_out'         => now()->addDays(5),
        'adults_count'      => 2,
        'children_count'    => 4,
        'children_ages'     => ['2_10', '2_10', '11_15', '11_15'],
        'total_nights'      => 3,
        'price_per_night'   => 5000000,
        'total_room_amount' => 15000000,
        'extras_amount'     => 6900000, // 69 000 FCFA
        'package_amount'    => 0,
        'tax_amount'        => 0,
        'discount_amount'   => 0,
        'total_amount'      => 21900000,
        'deposit_amount'    => 7000000,
        'paid_amount'       => 7000000,
        'balance_due'       => 14900000,
        'status'            => \App\Enums\BookingStatus::CONFIRMED,
        'source'            => 'direct',
        'checkin_code'      => '123456',
        'created_by'        => $user->id,
    ]);

    $response = $this->get(route('bookings.summary', $booking));
    $response->assertStatus(200);
    $response->assertSee('2 de 2 à 10 ans');
    $response->assertSee('Petits-déjeuners');
    $response->assertSee('69 000');
});
