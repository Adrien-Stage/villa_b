<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Models\GroupBooking;
use App\Models\CashRegisterSession;
use App\Models\CashRegisterDisbursement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access reception cash register routes', function () {
    $response = $this->get(route('bookings.cash_register.index'));
    $response->assertRedirect(route('login'));
});

test('receptionist and manager can access index and open form, but shop cashier cannot', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $tenant = Tenant::first();

    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);
    $shopCashier = User::factory()->create(['role' => 'shop_cashier']);

    // Receptionist
    $this->actingAs($receptionist);
    $this->get(route('bookings.cash_register.index'))->assertStatus(200);
    $this->get(route('bookings.cash_register.open'))->assertStatus(200);

    // Manager
    $this->actingAs($manager);
    $this->get(route('bookings.cash_register.index'))->assertStatus(200);
    $this->get(route('bookings.cash_register.open'))->assertStatus(200);

    // Shop Cashier (should get 403)
    $this->actingAs($shopCashier);
    $this->get(route('bookings.cash_register.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
});

test('only manager can access close form and close caisse', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $tenant = Tenant::first();

    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    // Open receptionist's session
    $session = CashRegisterSession::create([
        'user_id' => $receptionist->id,
        'module' => 'reception',
        'opening_amount' => 100000,
        'opened_at' => now()]);

    // Receptionist tries to close -> 403
    $this->actingAs($receptionist);
    $this->get(route('bookings.cash_register.close'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
    $this->post(route('bookings.cash_register.close.store'), [], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);

    // Manager session closure
    $managerSession = CashRegisterSession::create([
        'user_id' => $manager->id,
        'module' => 'reception',
        'opening_amount' => 100000,
        'opened_at' => now()]);

    $this->actingAs($manager);
    $this->get(route('bookings.cash_register.close'))->assertStatus(200);
    
    $response = $this->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount' => '1500',
        'theoretical_closing_amount' => 100000,
        'closing_notes' => 'Clôture de test']);
    $response->assertRedirect();
    
    $managerSession->refresh();
    expect($managerSession->closed_at)->not->toBeNull();
    expect($managerSession->actual_closing_amount)->toBe(150000);
});

test('redirects to open form if caisse is closed for booking creation', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $user = User::factory()->create(['role' => 'reception']);

    $this->actingAs($user);

    // 1. Individual booking wizard
    $this->get(route('bookings.create'))->assertRedirect(route('bookings.cash_register.open'));

    // 2. Individual booking store
    $response = $this->post(route('bookings.store'), [
        'room_id' => $room->id,
        'customer_id' => $customer->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 1,
        'custom_price' => '45000',
        'payment_amount' => '15000',
        'payment_method' => 'cash']);
    $response->assertRedirect(route('bookings.cash_register.open'));

    // 3. Group booking wizard
    // Group creation routes require manager role, so authenticate as manager
    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager);
    $this->get(route('groups.create'))->assertRedirect(route('bookings.cash_register.open'));

    // 4. Group booking store
    $response = $this->post(route('groups.store'), [
        'contact_customer_id' => $customer->id,
        'group_name' => 'Test Group',
        'event_type' => 'family',
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'end_date' => now()->addDays(3)->format('Y-m-d'),
        'deposit_amount' => '20000',
        'payment_method' => 'cash']);
    $response->assertRedirect(route('bookings.cash_register.open'));
});

test('payments and calculations work when caisse is open', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager);

    // 1. Open the caisse with 50 000 FCFA
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '50000'])->assertRedirect();

    $activeSession = CashRegisterSession::where('user_id', $manager->id)
        ->where('module', 'reception')
        ->whereNull('closed_at')
        ->first();
    expect($activeSession)->not->toBeNull();
    expect($activeSession->opening_amount)->toBe(5000000); // 50 000 FCFA

    // 2. Create booking with 15 000 FCFA cash deposit
    $this->post(route('bookings.store'), [
        'room_id' => $room->id,
        'customer_id' => $customer->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 1,
        'custom_price' => '45000',
        'payment_amount' => '15000',
        'payment_method' => 'cash'])->assertRedirect();

    // Verify payment is associated to session
    $booking = Booking::latest()->first();
    $payment1 = $booking->payments->first();
    expect($payment1->cash_register_session_id)->toBe($activeSession->id);
    expect($payment1->amount)->toBe(1500000); // 15 000 FCFA
    expect($payment1->method)->toBe('cash');

    // 3. Add non-cash payment (MTN MoMo) of 10 000 FCFA
    $this->post(route('bookings.payment.add', $booking), [
        'amount' => '10000',
        'method' => 'mtn_momo'])->assertRedirect();

    $payment2 = $booking->payments()->orderBy('id', 'desc')->first();
    expect($payment2->cash_register_session_id)->toBe($activeSession->id);
    expect($payment2->amount)->toBe(1000000); // 10 000 FCFA
    expect($payment2->method)->toBe('mtn_momo');

    // 4. Add disbursement (Sortie de caisse) of 5 000 FCFA
    $this->post(route('bookings.cash_register.disbursements.store'), [
        'amount' => '5000',
        'reason' => 'Achat café accueil'])->assertRedirect();

    $disbursement = CashRegisterDisbursement::latest()->first();
    expect($disbursement->cash_register_session_id)->toBe($activeSession->id);
    expect($disbursement->amount)->toBe(500000); // 5 000 FCFA

    // 5. Check closing calculations
    // Theoretical Closing:
    // Opening amount (50 000) + Cash Payments (15 000) - Disbursements (5 000) = 60 000 FCFA (6000000 cents)
    // Note: MTN MoMo payment is NOT cash, so excluded.
    $closeResponse = $this->get(route('bookings.cash_register.close'));
    $closeResponse->assertStatus(200);
    $closeResponse->assertSee('60 000'); // 60 000 FCFA in presentation
    $closeResponse->assertSee('15 000'); // cash payments
    $closeResponse->assertSee('5 000'); // disbursements
});

test('group booking payments and calculations work when caisse is open', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager);

    // 1. Open the caisse with 10 000 FCFA
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    $activeSession = CashRegisterSession::where('user_id', $manager->id)
        ->where('module', 'reception')
        ->whereNull('closed_at')
        ->first();

    // 2. Create group booking with 20 000 FCFA cash deposit
    $this->post(route('groups.store'), [
        'contact_customer_id' => $customer->id,
        'group_name' => 'Group Test 1',
        'event_type' => 'family',
        'start_date' => now()->addDays(1)->format('Y-m-d'),
        'end_date' => now()->addDays(3)->format('Y-m-d'),
        'deposit_amount' => '20000',
        'payment_method' => 'cash'])->assertRedirect();

    $group = GroupBooking::latest()->first();
    expect($group)->not->toBeNull();
    
    // Deposit payment is created in store
    $payment1 = \App\Models\Payment::where('customer_id', $group->contact_customer_id)->first();
    expect($payment1->cash_register_session_id)->toBe($activeSession->id);
    expect($payment1->amount)->toBe(2000000); // 20 000 FCFA
    expect($payment1->method)->toBe('cash');

    // 3. Add room to group
    $this->post(route('groups.addRoom', $group), [
        'room_id' => $room->id,
        'customer_id' => $customer->id,
        'adults_count' => 2])->assertRedirect();

    // 4. Add group payment in cash
    $this->post(route('groups.payment.add', $group), [
        'amount' => '15000',
        'method' => 'cash',
        'distribution' => 'equal'])->assertRedirect();

    $payment2 = \App\Models\Payment::whereNotNull('booking_id')->first();
    expect($payment2->cash_register_session_id)->toBe($activeSession->id);
    expect($payment2->amount)->toBe(1500000); // 15 000 FCFA
    expect($payment2->method)->toBe('cash');
});

test('bookings and groups index views receive isCashRegisterOpen correctly', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $tenant = Tenant::first();
    $user = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    // 1. Caisse is closed
    $this->actingAs($user);
    $response = $this->get(route('bookings.index'));
    $response->assertStatus(200);
    $response->assertViewHas('isCashRegisterOpen', false);

    $this->actingAs($manager);
    $response = $this->get(route('groups.index'));
    $response->assertStatus(200);
    $response->assertViewHas('isCashRegisterOpen', false);

    // 2. Open the caisse for user (receptionist)
    $this->actingAs($user);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    // Now index view should have isCashRegisterOpen = true for user
    $response = $this->get(route('bookings.index'));
    $response->assertStatus(200);
    $response->assertViewHas('isCashRegisterOpen', true);

    // But for manager, it is still closed (since it's a different user)
    $this->actingAs($manager);
    $response = $this->get(route('groups.index'));
    $response->assertStatus(200);
    $response->assertViewHas('isCashRegisterOpen', false);

    // Now open the caisse for manager
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '20000'])->assertRedirect();

    // Now index view should have isCashRegisterOpen = true for manager too
    $response = $this->get(route('groups.index'));
    $response->assertStatus(200);
    $response->assertViewHas('isCashRegisterOpen', true);
});

test('booking wizard previous button maps fields and returns select-room step', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $user = User::factory()->create(['role' => 'reception']);

    $this->actingAs($user);

    // Open the caisse
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    // Send step=4 POST request but with action_back=1
    $response = $this->post(route('bookings.store'), [
        'action_back' => '1',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct']);

    $response->assertStatus(200);
    $response->assertViewIs('bookings.select-room');
    $response->assertViewHas('customer');
    $response->assertViewHas('adults', 2);
    $response->assertViewHas('children', 1);
});

test('receptionist cannot submit arbitrary custom price but manager can', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $roomType = $room->roomType;
    $roomType->update(['base_price' => 5000000]); // 50 000 FCFA

    // Set custom settings
    $tenant->update([
        'settings' => [
            'reception' => [
                'max_discount_percentage' => 15,
                'min_deposit_percentage' => 30]
        ]
    ]);

    $customer = Customer::factory()->create([]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    // 1. Logged in as receptionist, caisse open
    $this->actingAs($receptionist);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    // Try submitting arbitrary price (49 000 FCFA is not a multiple of 5% discount of 50 000 FCFA)
    $response = $this->from(route('bookings.create'))->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 0,
        'source' => 'direct',
        'custom_price' => '49000',
        'payment_amount' => '15000',
        'payment_method' => 'cash']);

    $response->assertRedirect(route('bookings.create'));
    $response->assertSessionHasErrors('custom_price');

    // Try submitting too low payment amount (10% of 47 500 is 4 750, but min deposit percentage is 30% = 14 250)
    // Here we use 47 500 (5% discount), which is a valid custom price.
    $response2 = $this->from(route('bookings.create'))->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 0,
        'source' => 'direct',
        'custom_price' => '47500',
        'payment_amount' => '10000', // < 14 250
        'payment_method' => 'cash']);

    $response2->assertRedirect(route('bookings.create'));
    $response2->assertSessionHasErrors('payment_amount');

    // Try submitting valid discount and valid payment
    $response3 = $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 0,
        'source' => 'direct',
        'custom_price' => '47500', // 5% discount
        'payment_amount' => '15000', // > 14 250
        'payment_method' => 'cash']);

    $response3->assertRedirect();
    $booking = Booking::orderBy('id', 'desc')->first();
    expect($booking->price_per_night)->toBe(4750000); // in cents

    // 2. Logged in as manager, caisse open
    $this->actingAs($manager);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '20000'])->assertRedirect();

    // Manager can set arbitrary price
    $response4 = $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(3)->format('Y-m-d'),
        'check_out' => now()->addDays(4)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 0,
        'source' => 'direct',
        'custom_price' => '49000', // arbitrary price
        'payment_amount' => '15000',
        'payment_method' => 'cash']);

    $response4->assertRedirect();
    $managerBooking = Booking::orderBy('id', 'desc')->first();
    expect($managerBooking->price_per_night)->toBe(4900000); // in cents
});

test('receptionist complimentary booking needs approval and saves as pending', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $receptionist = User::factory()->create(['role' => 'reception']);

    $this->actingAs($receptionist);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    $response = $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct',
        'custom_price' => '0',
        'payment_amount' => '0',
        'payment_method' => 'cash',
        'is_offerte' => '1',
        'offerte_reason' => 'Guest of honor receptionist']);

    $response->assertRedirect();
    $booking = Booking::orderBy('id', 'desc')->first();
    expect($booking->status)->toBe(\App\Enums\BookingStatus::PENDING);
    expect($booking->notes)->toContain('Offerte - Motif : Guest of honor receptionist');
    expect($booking->payments()->count())->toBe(0);
});

test('manager complimentary booking is confirmed immediately', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    $response = $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct',
        'custom_price' => '0',
        'payment_amount' => '0',
        'payment_method' => 'cash',
        'is_offerte' => '1',
        'offerte_reason' => 'Guest of honor manager']);

    $response->assertRedirect();
    $booking = Booking::orderBy('id', 'desc')->first();
    expect($booking->status)->toBe(\App\Enums\BookingStatus::CONFIRMED);
    expect($booking->notes)->toContain('Offerte - Motif : Guest of honor manager');
    expect($booking->payments()->count())->toBe(0);
});

test('manager can approve pending complimentary booking', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    // Create a pending booking
    $this->actingAs($receptionist);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct',
        'custom_price' => '0',
        'payment_amount' => '0',
        'payment_method' => 'cash',
        'is_offerte' => '1',
        'offerte_reason' => 'Complimentary test booking']);

    $booking = Booking::orderBy('id', 'desc')->first();
    expect($booking->status)->toBe(\App\Enums\BookingStatus::PENDING);

    // Receptionist tries to approve -> 403
    $this->actingAs($receptionist);
    $response = $this->post(route('bookings.approve', $booking));
    $response->assertStatus(403);

    // Manager approves -> success
    $this->actingAs($manager);
    $response = $this->post(route('bookings.approve', $booking));
    $response->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe(\App\Enums\BookingStatus::CONFIRMED);
});

test('complimentary bookings trigger in-app notifications correctly', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    // 1. Receptionist logs in, opens caisse
    $this->actingAs($receptionist);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    // 2. Create pending complimentary booking
    $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct',
        'custom_price' => '0',
        'payment_amount' => '0',
        'payment_method' => 'cash',
        'is_offerte' => '1',
        'offerte_reason' => 'Need approval notification']);

    $booking = Booking::orderBy('id', 'desc')->first();

    // 3. Manager should have 1 unread notification
    $this->actingAs($manager);
    $response = $this->getJson(route('notifications.unread'));
    $response->assertStatus(200);
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('total_unread', 1);
    $response->assertJsonPath('notifications.0.data.booking_id', $booking->id);
    $response->assertJsonPath('notifications.0.data.title', 'Chambre Offerte - Validation Requise');

    $notifId = $response->json('notifications.0.id');

    // 4. Manager approves the booking
    $this->post(route('bookings.approve', $booking))->assertRedirect();

    // 5. Receptionist should have 1 unread notification (approved)
    $this->actingAs($receptionist);
    $response2 = $this->getJson(route('notifications.unread'));
    $response2->assertStatus(200);
    $response2->assertJsonPath('total_unread', 1);
    $response2->assertJsonPath('notifications.0.data.booking_id', $booking->id);
    $response2->assertJsonPath('notifications.0.data.title', 'Réservation Offerte Validée');

    $recNotifId = $response2->json('notifications.0.id');

    // 6. Receptionist marks notification as read
    $this->postJson(route('notifications.read', $recNotifId))->assertStatus(200);
    $response3 = $this->getJson(route('notifications.unread'));
    $response3->assertJsonPath('total_unread', 0);

    // 7. Manager marks all as read
    $this->actingAs($manager);
    $this->postJson(route('notifications.readAll'))->assertStatus(200);
    $response4 = $this->getJson(route('notifications.unread'));
    $response4->assertJsonPath('total_unread', 0);
});

test('booking finalization sends check-in code email to customer and booker if present', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create([
        'email' => 'customer@example.com'
    ]);
    $booker = Customer::factory()->create([
        'email' => 'booker@example.com'
    ]);
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager);
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000'])->assertRedirect();

    $this->post(route('bookings.store'), [
        'step' => '4',
        'customer_id' => $customer->id,
        'booker_id' => $booker->id,
        'room_id' => $room->id,
        'check_in' => now()->addDays(1)->format('Y-m-d'),
        'check_out' => now()->addDays(2)->format('Y-m-d'),
        'adults_count' => 2,
        'children_count' => 1,
        'source' => 'direct',
        'custom_price' => '50000',
        'payment_amount' => '15000',
        'payment_method' => 'cash'])->assertRedirect();

    $booking = Booking::orderBy('id', 'desc')->first();
    expect($booking)->not->toBeNull();

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CheckinCodeMail::class, function ($mail) use ($customer) {
        return $mail->hasTo('customer@example.com') && $mail->booking->checkin_code !== null;
    });

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CheckinCodeMail::class, function ($mail) use ($booker) {
        return $mail->hasTo('booker@example.com');
    });
});

test('booking calculates price with capacity surcharge when occupants exceed base capacity', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class]);
    
    $tenant = Tenant::first();
    $room = Room::first();
    $roomType = $room->roomType;
    
    // Set capacity surcharge settings to 15% and base capacity of standard room to 2
    $roomType->update([
        'base_capacity' => 2,
        'base_price' => 4000000 // 40 000 FCFA
    ]);
    
    $tenant->update([
        'settings' => [
            'reception' => [
                'capacity_surcharge_percentage' => 15]
        ]
    ]);
    
    // Test base capacity not exceeded (2 people)
    $price1 = $roomType->getCalculatedPricePerNight(2, 0); // Should be 40 000 FCFA (4000000 centimes)
    expect($price1)->toBe(4000000);
    
    // Test base capacity exceeded (3 people)
    $price2 = $roomType->getCalculatedPricePerNight(2, 1); // 2 adults + 1 child = 3 > 2. Should be 40 000 * 1.15 = 46 000 FCFA (4600000 centimes)
    expect($price2)->toBe(4600000);
});

