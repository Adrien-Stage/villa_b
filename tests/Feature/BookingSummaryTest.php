<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        TenantSeeder::class,
        RoomTypeSeeder::class,
        RoomSeeder::class,
    ]);
});

test('la page de recapitulatif affiche la synthese complete de la reservation apres confirmation', function () {
    $user = User::factory()->create(['role' => 'reception']);
    $this->actingAs($user);

    $customer = Customer::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Kouam',
        'phone' => '+237690112233',
        'email' => 'alice.kouam@example.com',
    ]);

    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => today(),
        'check_out'         => today()->addDays(2),
        'check_in_time'     => '14:30',
        'adults_count'      => 2,
        'children_count'    => 1,
        'total_nights'      => 2,
        'price_per_night'   => 50000,
        'total_room_amount' => 100000,
        'total_amount'      => 100000,
        'deposit_amount'    => 40000,
        'paid_amount'       => 40000,
        'balance_due'       => 60000,
        'status'            => BookingStatus::CONFIRMED,
        'source'            => 'walk_in',
        'checkin_code'      => 'CHK-8842',
        'notes'             => 'Lit bebe souhaite',
        'created_by'        => $user->id,
    ]);

    Payment::create([
        'booking_id'    => $booking->id,
        'customer_id'   => $customer->id,
        'amount'        => 40000,
        'method'        => 'cash',
        'status'        => 'completed',
        'reference'     => 'REC-TEST-40K',
        'processed_by'  => $user->id,
    ]);

    $response = $this->get(route('bookings.summary', $booking));

    $response->assertOk();
    $response->assertSee('Récapitulatif de la réservation');
    $response->assertSee($booking->booking_number);
    $response->assertSee('Alice Kouam');
    $response->assertSee('+237690112233');
    $response->assertSee('Chambre 101');
    $response->assertSee('14:30');
    $response->assertSee('2 nuit');
    $response->assertSee('Lit bebe souhaite');
    $response->assertSee('100 000 FCFA');
    $response->assertSee('40 000 FCFA');
    $response->assertSee('60 000 FCFA');
    $response->assertSee('CHK-8842');
    $response->assertSee('Code de Check-in');
    $response->assertSee(route('bookings.show', $booking));
});

test('la validation de l etape 3 redirige vers la page de recapitulatif', function () {
    $user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($user);

    CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $response = $this->post(route('bookings.store'), [
        'customer_id'    => $customer->id,
        'room_id'        => $room->id,
        'check_in'       => today()->format('Y-m-d'),
        'check_out'      => today()->addDay()->format('Y-m-d'),
        'check_in_time'  => '15:00',
        'adults_count'   => 1,
        'children_count' => 0,
        'source'         => 'direct',
        'custom_price'   => '50000',
        'payment_amount' => '25000',
        'payment_method' => 'cash',
    ]);

    $booking = Booking::latest()->first();

    $response->assertRedirect(route('bookings.summary', $booking));
    $response->assertSessionHas('success');
    $response->assertSessionHas('checkin_code', $booking->checkin_code);
});
