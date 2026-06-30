<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user can create a booking with 0% VAT and correct net prices', function () {
    // Seed database
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();
    $room = Room::where('number', '101')->first(); // Chambre Standard, base price 4500000 cents (45000 FCFA)

    // Setup user
    $user = User::factory()->create([
        'role' => 'manager']);

    // Create active cash register session for reception
    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now()]);

    // Setup customers
    $customer = Customer::factory()->create([]);
    $booker = Customer::factory()->create([
        'first_name' => 'Mandataire',
        'last_name' => 'Tiers',
        'email' => 'mandataire@example.com',
        'phone' => '+237600000000',
        'id_document_type' => 'CNI',
        'id_document_number' => '123456789']);

    // Login user
    $this->actingAs($user);

    // Create booking via POST request (2 nights: 2 * 45000 = 90000)
    $response = $this->post(route('bookings.store'), [
        'room_id' => $room->id,
        'customer_id' => $customer->id,
        'booker_id' => $booker->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(3)->format('Y-m-d'), // 2 nights
        'adults_count' => 1,
        'children_count' => 0,
        'source' => 'direct',
        'notes' => 'Test booking',
        'custom_price' => '90000', // 90000 FCFA total
        'payment_amount' => '30000', // 30000 FCFA deposit
        'payment_method' => 'cash',
        'payment_reference' => 'REF123']);

    // Check redirection or success status
    $response->assertRedirect();

    // Verify booking in database
    $booking = Booking::latest()->first();
    expect($booking->tax_amount)->toBe(0);
    expect($booking->price_per_night)->toBe(4500000); // 45000 FCFA in cents
    expect($booking->total_room_amount)->toBe(9000000);
    expect($booking->total_amount)->toBe(9000000);
    expect($booking->deposit_amount)->toBe(3000000);
    expect($booking->paid_amount)->toBe(3000000);
    expect($booking->balance_due)->toBe(6000000);

    // Get the details view and check HTML elements
    $showResponse = $this->get(route('bookings.show', $booking));
    $showResponse->assertStatus(200);

    // TVA line should be hidden, "Sous-total" and "Total" should be present without HT/TTC
    $showResponse->assertDontSee('TVA (19,25%)');
    $showResponse->assertSee('Sous-total');
    $showResponse->assertSee('Total');
    $showResponse->assertDontSee('Sous-total HT');
    $showResponse->assertDontSee('Total TTC');

    // Booker (Mandataire) card must contain details
    $showResponse->assertSee('Mandataire (Payeur)');
    $showResponse->assertSee('Mandataire Tiers');
    $showResponse->assertSee('mandataire@example.com');
    $showResponse->assertSee('+237600000000');
    $showResponse->assertSee('CNI : 123456789');
});

test('a booking details view fallback to client himself if no booker is present', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();
    $room = Room::where('number', '101')->first();

    $user = User::factory()->create([
        'role' => 'manager']);

    // Create active cash register session for reception
    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'module' => 'reception',
        'opening_amount' => 5000000,
        'opened_at' => now()]);

    $customer = Customer::factory()->create([]);

    $this->actingAs($user);

    $response = $this->post(route('bookings.store'), [
        'room_id' => $room->id,
        'customer_id' => $customer->id,
        'booker_id' => '',
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(3)->format('Y-m-d'),
        'adults_count' => 1,
        'children_count' => 0,
        'source' => 'direct',
        'notes' => 'Test booking 2',
        'custom_price' => '90000',
        'payment_amount' => '30000',
        'payment_method' => 'cash',
        'payment_reference' => 'REF456']);

    $response->assertRedirect();

    $booking = Booking::latest()->first();
    expect($booking->booker_id)->toBeNull();

    $showResponse = $this->get(route('bookings.show', $booking));
    $showResponse->assertStatus(200);

    // Check fallback message
    $showResponse->assertSee('Le client final lui-même');
});

test('a shop cashier can create a shop order with 0% VAT', function () {
    // Seed database
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\ShopSeeder::class]);

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();

    // Create user with shop_cashier role
    $user = User::factory()->create([
        'role' => 'shop_cashier']);

    // Create an active cash register session for this cashier
    \App\Models\CashRegisterSession::create([
        'user_id' => $user->id,
        'opened_at' => now(),
        'opening_amount' => 5000000, // 50000 FCFA in cents
    ]);

    $product = \App\Models\ShopProduct::first(); // Standard Masque Bamiléké, price 2500000 cents

    $this->actingAs($user);

    // POST to shop.orders.store
    $response = $this->post(route('shop.orders.store'), [
        'customer_name' => 'Client de passage',
        'customer_phone' => '+237699999999',
        'payment_method' => 'cash',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2]
        ]]);

    $response->assertRedirect();

    // Verify order in database
    $order = \App\Models\ShopOrder::latest()->first();
    expect($order->tax_amount)->toBe(0);
    expect($order->subtotal)->toBe(5000000); // 2 * 25000 * 100 = 5000000 cents
    expect($order->total_amount)->toBe(5000000);

    // Verify detail page has 0% tax
    $showResponse = $this->get(route('shop.orders.show', $order));
    $showResponse->assertStatus(200);
    $showResponse->assertDontSee('TVA (19,25%)');
    $showResponse->assertDontSee('TVA :');
});

