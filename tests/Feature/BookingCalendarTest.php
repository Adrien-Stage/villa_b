<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar view loads successfully and filters only confirmed bookings', function () {
    // Seed database
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();
    $room = Room::where('number', '101')->first();

    // Create user
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'manager',
    ]);

    // Create customers
    $customer1 = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $customer2 = Customer::factory()->create(['tenant_id' => $tenant->id]);

    // Create confirmed booking
    $confirmedBooking = Booking::create([
        'tenant_id' => $tenant->id,
        'room_id' => $room->id,
        'customer_id' => $customer1->id,
        'status' => BookingStatus::CONFIRMED,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(3)->format('Y-m-d'),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 4500000,
        'total_room_amount' => 9000000,
        'total_amount' => 9000000,
        'paid_amount' => 0,
        'balance_due' => 9000000,
    ]);

    // Create pending booking (should not be in calendar list)
    $pendingBooking = Booking::create([
        'tenant_id' => $tenant->id,
        'room_id' => $room->id,
        'customer_id' => $customer2->id,
        'status' => BookingStatus::PENDING,
        'check_in' => now()->addDays(4)->format('Y-m-d'),
        'check_out' => now()->addDays(5)->format('Y-m-d'),
        'adults_count' => 1,
        'total_nights' => 1,
        'price_per_night' => 4500000,
        'total_room_amount' => 4500000,
        'total_amount' => 4500000,
        'paid_amount' => 0,
        'balance_due' => 4500000,
    ]);

    // Log in user
    $this->actingAs($user);

    // Call bookings index with tab=active and view=calendar
    $response = $this->get(route('bookings.index', [
        'tab' => 'active',
        'view' => 'calendar',
    ]));

    // Check successful status
    $response->assertStatus(200);

    // Extract calendarBookings view data
    $calendarBookings = $response->viewData('calendarBookings');
    
    // Assert confirmed booking is present
    expect($calendarBookings)->not->toBeNull();
    expect($calendarBookings->pluck('id'))->toContain($confirmedBooking->id);
    
    // Assert pending booking is NOT present
    expect($calendarBookings->pluck('id'))->not->toContain($pendingBooking->id);

    // Assert that status filters on the calendar page only contain 'confirmed'
    $statusFilters = $response->viewData('statusFilters');
    expect($statusFilters)->toBe([
        'confirmed' => 'Confirmées',
    ]);
});
