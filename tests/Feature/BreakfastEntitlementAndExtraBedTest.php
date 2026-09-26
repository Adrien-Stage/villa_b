<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BreakfastEntitlement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BreakfastPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('booking creation with extra bed creates extra bed folio item and updates totals', function () {
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
    $checkOut = now()->addDays(4)->format('Y-m-d'); // 2 nights

    $baseRoomPricePerNight = (int) $room->roomType->base_price / 100;
    $totalRoomAmount = $baseRoomPricePerNight * 2;
    $extraBedPricePerNight = 10000; // 10 000 FCFA
    $extraBedTotal = $extraBedPricePerNight * 2; // 20 000 FCFA
    $netTotal = $totalRoomAmount + $extraBedTotal;
    $paymentAmount = (int) ceil($netTotal * 0.3);

    $response = $this->post(route('bookings.store'), [
        'step'              => '4',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => $checkIn,
        'check_out'         => $checkOut,
        'check_in_time'     => '14:00',
        'adults_count'      => 2,
        'children_count'    => 0,
        'has_extra_bed'     => '1',
        'extra_bed_count'   => 1,
        'extra_bed_amount'  => $extraBedTotal,
        'custom_price'      => $totalRoomAmount,
        'payment_amount'    => $paymentAmount,
        'payment_method'    => 'cash',
        'source'            => 'direct',
    ]);

    $response->assertRedirect();
    $booking = Booking::latest('id')->first();
    expect($booking)->not->toBeNull();
    expect($booking->has_extra_bed)->toBeTrue();
    expect($booking->extra_bed_count)->toBe(1);
    expect($booking->extra_bed_amount)->toBe($extraBedTotal * 100);

    // Verify FolioItem for Lit d'appoint was created
    $extraBedFolio = FolioItem::where('booking_id', $booking->id)
        ->where('type', FolioItem::TYPE_OTHER)
        ->where('description', 'like', '%Lit d\'appoint%')
        ->first();

    expect($extraBedFolio)->not->toBeNull();
    expect($extraBedFolio->total_price)->toBe($extraBedTotal * 100);
});

test('booking creation generates daily breakfast entitlements for all mornings', function () {
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
    // Ensure room type has includes_breakfast = true and base_capacity = 2
    $room->roomType->update([
        'includes_breakfast' => true,
        'base_capacity'      => 2,
    ]);

    $this->actingAs($user);

    $checkIn = now()->addDays(1)->format('Y-m-d');
    $checkOut = now()->addDays(4)->format('Y-m-d'); // 3 nights

    $baseRoomPricePerNight = (int) $room->roomType->base_price / 100;
    $totalRoomAmount = $baseRoomPricePerNight * 3;
    $childBreakfastStayTotal = 2500 * 3; // 1 child of 2-10 ans for 3 nights

    $response = $this->post(route('bookings.store'), [
        'step'                       => '4',
        'customer_id'                => $customer->id,
        'room_id'                    => $room->id,
        'check_in'                   => $checkIn,
        'check_out'                  => $checkOut,
        'adults_count'               => 2,
        'children_count'             => 1,
        'children_ages'              => ['2_10'],
        'prepaid_breakfast_children' => '1',
        'prepaid_breakfast_amount'   => $childBreakfastStayTotal,
        'custom_price'               => $totalRoomAmount,
        'payment_amount'             => (int) ceil(($totalRoomAmount + $childBreakfastStayTotal) * 0.3),
        'payment_method'             => 'cash',
        'source'                     => 'direct',
    ]);

    $response->assertRedirect();
    $booking = Booking::latest('id')->first();
    expect($booking)->not->toBeNull();

    // Entitlements should exist for check_in + 1, check_in + 2, check_in + 3
    $entitlements = BreakfastEntitlement::where('booking_id', $booking->id)
        ->orderBy('service_date')
        ->get();

    expect($entitlements)->toHaveCount(3);

    $morning1 = Carbon::parse($checkIn)->addDays(1)->toDateString();
    $morning2 = Carbon::parse($checkIn)->addDays(2)->toDateString();
    $morning3 = Carbon::parse($checkIn)->addDays(3)->toDateString();

    expect($entitlements->map(fn ($e) => $e->service_date->toDateString())->all())->toBe([$morning1, $morning2, $morning3]);

    foreach ($entitlements as $entitlement) {
        expect($entitlement->adults_included)->toBe(2);
        expect($entitlement->children_included)->toBe(1);
        expect($entitlement->isAvailable())->toBeTrue();
    }
});

function enableRestaurantModule(): void
{
    $prop = new \ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['restaurant']);
}

test('restaurant breakfast index page renders correctly with date filter and stats', function () {
    enableRestaurantModule();
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    $customer = Customer::factory()->create(['first_name' => 'Amadou', 'last_name' => 'Diallo']);
    $room = Room::first();

    $booking = Booking::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_number'    => 'RES-BF-001',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDay(),
        'check_out'         => now()->addDays(2),
        'adults_count'      => 2,
        'children_count'    => 1,
        'total_nights'      => 3,
        'price_per_night'   => 5000000,
        'total_room_amount' => 15000000,
        'total_amount'      => 15000000,
        'deposit_amount'    => 5000000,
        'paid_amount'       => 5000000,
        'balance_due'       => 10000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $user->id,
    ]);

    $today = now()->toDateString();
    $entitlement = BreakfastEntitlement::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_id'        => $booking->id,
        'room_id'           => $room->id,
        'service_date'      => $today,
        'adults_included'   => 2,
        'children_included' => 1,
        'status'            => BreakfastEntitlement::STATUS_AVAILABLE,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('restaurant.breakfast.index', ['date' => $today]));
    $response->assertStatus(200);
    $response->assertSee($room->number);
    $response->assertSee('Diallo');
    $response->assertSee('2 adultes');
    $response->assertSee('1 enfant');
});

test('restaurant pointage with included quota serves without extra charge', function () {
    enableRestaurantModule();
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'restaurant_staff']);
    $customer = Customer::factory()->create();
    $room = Room::first();

    $booking = Booking::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_number'    => 'RES-BF-002',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDay(),
        'check_out'         => now()->addDays(2),
        'adults_count'      => 2,
        'children_count'    => 1,
        'total_nights'      => 3,
        'price_per_night'   => 5000000,
        'total_room_amount' => 15000000,
        'total_amount'      => 15000000,
        'deposit_amount'    => 5000000,
        'paid_amount'       => 5000000,
        'balance_due'       => 10000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $user->id,
    ]);

    $entitlement = BreakfastEntitlement::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_id'        => $booking->id,
        'room_id'           => $room->id,
        'service_date'      => now()->toDateString(),
        'adults_included'   => 2,
        'children_included' => 1,
        'status'            => BreakfastEntitlement::STATUS_AVAILABLE,
    ]);

    $this->actingAs($user);

    $response = $this->post(route('restaurant.breakfast.serve', $entitlement), [
        'adults_served'     => 2,
        'children_served'   => 1,
        'settlement_method' => 'room_charge',
        'notes'             => 'Pris au buffet principal',
    ]);

    $response->assertRedirect();
    $entitlement->refresh();

    expect($entitlement->status)->toBe(BreakfastEntitlement::STATUS_CONSUMED);
    expect($entitlement->adults_consumed)->toBe(2);
    expect($entitlement->children_consumed)->toBe(1);

    // No restaurant extra folio item created
    $folioItemsCount = FolioItem::where('booking_id', $booking->id)
        ->where('type', FolioItem::TYPE_RESTAURANT)
        ->count();
    expect($folioItemsCount)->toBe(0);
});

test('restaurant pointage with extra guests via room charge bills folio and updates booking balance', function () {
    enableRestaurantModule();
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'restaurant_staff']);
    $customer = Customer::factory()->create();
    $room = Room::first();

    $booking = Booking::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_number'    => 'RES-BF-003',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDay(),
        'check_out'         => now()->addDays(2),
        'adults_count'      => 2,
        'children_count'    => 0,
        'total_nights'      => 3,
        'price_per_night'   => 5000000,
        'total_room_amount' => 15000000,
        'total_amount'      => 15000000,
        'deposit_amount'    => 5000000,
        'paid_amount'       => 5000000,
        'balance_due'       => 10000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $user->id,
    ]);

    \App\Models\Payment::create([
        'tenant_id'    => Tenant::first()->id,
        'booking_id'   => $booking->id,
        'customer_id'  => $customer->id,
        'reference'    => 'PAY-BF-001',
        'amount'       => 5000000,
        'method'       => 'cash',
        'status'       => 'completed',
        'processed_by' => $user->id,
    ]);

    $entitlement = BreakfastEntitlement::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_id'        => $booking->id,
        'room_id'           => $room->id,
        'service_date'      => now()->toDateString(),
        'adults_included'   => 2,
        'children_included' => 0,
        'status'            => BreakfastEntitlement::STATUS_AVAILABLE,
    ]);

    $this->actingAs($user);

    // Entitlement includes 2 adults, 0 children.
    // Serving 3 adults (+1 adult extra = 5 000 FCFA) and 1 child (+1 child extra = 2 500 FCFA default fallback).
    // Total extra = 7 500 FCFA = 750 000 centimes.
    $response = $this->post(route('restaurant.breakfast.serve', $entitlement), [
        'adults_served'     => 3,
        'children_served'   => 1,
        'settlement_method' => 'room_charge',
        'notes'             => 'Invité extérieur à table',
    ]);

    $response->assertRedirect();
    $entitlement->refresh();

    expect($entitlement->status)->toBe(BreakfastEntitlement::STATUS_CONSUMED);
    expect($entitlement->adults_consumed)->toBe(3);
    expect($entitlement->children_consumed)->toBe(1);

    // Verify FolioItem was created
    $folioItem = FolioItem::where('booking_id', $booking->id)
        ->where('type', FolioItem::TYPE_RESTAURANT)
        ->first();

    expect($folioItem)->not->toBeNull();
    expect($folioItem->total_price)->toBe(750000);
    expect($folioItem->description)->toContain('Petit-déjeuner extra');

    // Verify booking totals were recalculated
    $booking->refresh();
    expect($booking->balance_due)->toBe(10750000); // 10 000 000 + 750 000
});

test('restaurant pointage with extra guests via cash does not bill room folio', function () {
    enableRestaurantModule();
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'restaurant_staff']);
    $customer = Customer::factory()->create();
    $room = Room::first();

    $booking = Booking::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_number'    => 'RES-BF-004',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDay(),
        'check_out'         => now()->addDays(2),
        'adults_count'      => 2,
        'children_count'    => 0,
        'total_nights'      => 3,
        'price_per_night'   => 5000000,
        'total_room_amount' => 15000000,
        'total_amount'      => 15000000,
        'deposit_amount'    => 5000000,
        'paid_amount'       => 5000000,
        'balance_due'       => 10000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $user->id,
    ]);

    $entitlement = BreakfastEntitlement::create([
        'tenant_id'         => Tenant::first()->id,
        'booking_id'        => $booking->id,
        'room_id'           => $room->id,
        'service_date'      => now()->toDateString(),
        'adults_included'   => 2,
        'children_included' => 0,
        'status'            => BreakfastEntitlement::STATUS_AVAILABLE,
    ]);

    $this->actingAs($user);

    // Serving 3 adults (1 extra = 5 000 FCFA), paid directly with cash
    $response = $this->post(route('restaurant.breakfast.serve', $entitlement), [
        'adults_served'     => 3,
        'children_served'   => 0,
        'settlement_method' => 'cash',
        'notes'             => 'Paiement direct en espèces au serveur',
    ]);

    $response->assertRedirect();
    $entitlement->refresh();

    expect($entitlement->status)->toBe(BreakfastEntitlement::STATUS_CONSUMED);
    expect($entitlement->adults_consumed)->toBe(3);
    expect($entitlement->children_consumed)->toBe(0);

    // No FolioItem created on the booking
    $folioItemsCount = FolioItem::where('booking_id', $booking->id)
        ->where('type', FolioItem::TYPE_RESTAURANT)
        ->count();
    expect($folioItemsCount)->toBe(0);

    // Booking balance remains unchanged
    $booking->refresh();
    expect($booking->balance_due)->toBe(10000000);
});
