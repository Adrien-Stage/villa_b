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

test('agenda page loads and lists every ongoing stay', function () {
    // Seed database
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();
    $room = Room::where('number', '101')->first();

    // Create user
    $user = User::factory()->create([
        'role' => 'manager']);

    // Create customers
    $customer1 = Customer::factory()->create([]);
    $customer2 = Customer::factory()->create([]);

    // Create confirmed booking
    $confirmedBooking = Booking::create([
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
        'balance_due' => 9000000]);

    // Une demande en attente : l'agenda la montre aussi, sinon l'arrivée
    // passerait inaperçue.

    $pendingBooking = Booking::create([
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
        'balance_due' => 4500000]);

    // Log in user
    $this->actingAs($user);

    // L'agenda a sa propre page, hors de la liste des réservations.
    $response = $this->get(route('agenda.index'));

    // Check successful status
    $response->assertStatus(200);

    // Extract calendarBookings view data
    $calendarBookings = $response->viewData('calendarBookings');
    
    // Assert confirmed booking is present
    expect($calendarBookings)->not->toBeNull();
    expect($calendarBookings->pluck('id'))->toContain($confirmedBooking->id);
    
    // Assert pending booking is present too
    expect($calendarBookings->pluck('id'))->toContain($pendingBooking->id);

    // Filtres de statut proposés au-dessus du calendrier
    $statusFilters = $response->viewData('statusFilters');
    expect($statusFilters)->toBe([
        'all'        => 'Toutes',
        'pending'    => 'En attente',
        'confirmed'  => 'Confirmées',
        'checked_in' => 'En séjour']);
});

test('agenda status filter narrows the stays shown', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);

    $room = Room::where('number', '101')->first();
    $user = User::factory()->create(['role' => 'manager']);

    $confirmed = Booking::create([
        'room_id' => $room->id,
        'customer_id' => Customer::factory()->create([])->id,
        'status' => BookingStatus::CONFIRMED,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(3)->format('Y-m-d'),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 4500000,
        'total_room_amount' => 9000000,
        'total_amount' => 9000000,
        'paid_amount' => 0,
        'balance_due' => 9000000]);

    $pending = Booking::create([
        'room_id' => $room->id,
        'customer_id' => Customer::factory()->create([])->id,
        'status' => BookingStatus::PENDING,
        'check_in' => now()->addDays(4)->format('Y-m-d'),
        'check_out' => now()->addDays(5)->format('Y-m-d'),
        'adults_count' => 1,
        'total_nights' => 1,
        'price_per_night' => 4500000,
        'total_room_amount' => 4500000,
        'total_amount' => 4500000,
        'paid_amount' => 0,
        'balance_due' => 4500000]);

    $this->actingAs($user);

    $response = $this->get(route('agenda.index', ['status' => 'confirmed']));

    $response->assertStatus(200);

    $calendarBookings = $response->viewData('calendarBookings');
    expect($calendarBookings->pluck('id'))->toContain($confirmed->id);
    expect($calendarBookings->pluck('id'))->not->toContain($pending->id);
});
