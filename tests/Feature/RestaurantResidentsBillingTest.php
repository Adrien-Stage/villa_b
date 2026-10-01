<?php

/**
 * La caisse du restaurant voit les clients logés qui ont consommé.
 *
 * Elle ne consulte plus le fichier clients ni les factures de l'hôtel, qui
 * relèvent de l'hébergement : la liste dont elle a besoin est celle des
 * commandes rattachées à un séjour, avec la chambre et le séjournant, pour
 * rapprocher la note du folio.
 */

use App\Models\Booking;
use App\Models\Customer;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant']);
    $this->seed(\Database\Seeders\TenantSeeder::class);

    $type = RoomType::create([
        'name' => 'Deluxe', 'code' => 'DLX', 'base_capacity' => 2, 'max_capacity' => 2, 'base_price' => 4000000,
    ]);
    $chambre = Room::create(['number' => '204', 'room_type_id' => $type->id, 'floor' => 2, 'status' => 'occupied']);
    $client = Customer::create(['first_name' => 'Aïcha', 'last_name' => 'Mbarga']);

    $this->sejour = Booking::create([
        'booking_number' => 'BKG-RESIDENT-1', 'customer_id' => $client->id, 'room_id' => $chambre->id,
        'check_in' => Carbon::today(), 'check_out' => Carbon::today()->addDays(2),
        'price_per_night' => 4000000, 'total_room_amount' => 8000000, 'total_nights' => 2,
        'total_amount' => 8000000, 'paid_amount' => 0, 'balance_due' => 8000000, 'status' => 'checked_in',
    ]);

    RestaurantCustomerOrder::create([
        'table_number' => 'T3', 'booking_id' => $this->sejour->id, 'status' => 'served',
        'total_amount' => 1500000, 'payment_status' => 'paid', 'payment_method' => 'room_charge',
    ]);
    RestaurantCustomerOrder::create([
        'table_number' => 'T9', 'customer_name' => 'Client de passage', 'status' => 'served',
        'total_amount' => 900000, 'payment_status' => 'unpaid',
    ]);
});

test('le filtre Résidents ne garde que les commandes rattachées à un séjour', function () {
    $this->actingAs(User::factory()->create(['role' => 'cashier']));

    $this->get(route('restaurant.billing.index', ['residents' => 1]))
        ->assertOk()
        ->assertSee('Ch. 204')
        ->assertSee('Aïcha Mbarga')
        ->assertDontSee('T9');
});

test('sans filtre, la caisse voit toutes les commandes du restaurant', function () {
    $this->actingAs(User::factory()->create(['role' => 'cashier']));

    $this->get(route('restaurant.billing.index'))
        ->assertOk()
        ->assertSee('T3')
        ->assertSee('T9');
});

test("la caisse du restaurant n'ouvre plus le fichier clients de l'hôtel", function () {
    $this->actingAs(User::factory()->create(['role' => 'cashier']));

    expect($this->get(route('customers.index'))->status())->not->toBe(200);
});
