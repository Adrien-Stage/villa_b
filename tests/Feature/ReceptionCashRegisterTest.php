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

    $receptionist = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'reception']);
    $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);
    $shopCashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'shop_cashier']);

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

    $receptionist = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'reception']);
    $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);

    // Open receptionist's session
    $session = CashRegisterSession::create([
        'tenant_id' => $tenant->id,
        'user_id' => $receptionist->id,
        'module' => 'reception',
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    // Receptionist tries to close -> 403
    $this->actingAs($receptionist);
    $this->get(route('bookings.cash_register.close'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
    $this->post(route('bookings.cash_register.close.store'), [], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);

    // Manager session closure
    $managerSession = CashRegisterSession::create([
        'tenant_id' => $tenant->id,
        'user_id' => $manager->id,
        'module' => 'reception',
        'opening_amount' => 100000,
        'opened_at' => now(),
    ]);

    $this->actingAs($manager);
    $this->get(route('bookings.cash_register.close'))->assertStatus(200);
    
    $response = $this->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount' => '1500',
        'theoretical_closing_amount' => 100000,
        'closing_notes' => 'Clôture de test',
    ]);
    $response->assertRedirect();
    
    $managerSession->refresh();
    expect($managerSession->closed_at)->not->toBeNull();
    expect($managerSession->actual_closing_amount)->toBe(150000);
});

test('redirects to open form if caisse is closed for booking creation', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'reception']);

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
        'payment_method' => 'cash',
    ]);
    $response->assertRedirect(route('bookings.cash_register.open'));

    // 3. Group booking wizard
    // Group creation routes require manager role, so authenticate as manager
    $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);
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
        'payment_method' => 'cash',
    ]);
    $response->assertRedirect(route('bookings.cash_register.open'));
});

test('payments and calculations work when caisse is open', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);

    $this->actingAs($manager);

    // 1. Open the caisse with 50 000 FCFA
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '50000',
    ])->assertRedirect();

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
        'payment_method' => 'cash',
    ])->assertRedirect();

    // Verify payment is associated to session
    $booking = Booking::latest()->first();
    $payment1 = $booking->payments->first();
    expect($payment1->cash_register_session_id)->toBe($activeSession->id);
    expect($payment1->amount)->toBe(1500000); // 15 000 FCFA
    expect($payment1->method)->toBe('cash');

    // 3. Add non-cash payment (MTN MoMo) of 10 000 FCFA
    $this->post(route('bookings.payment.add', $booking), [
        'amount' => '10000',
        'method' => 'mtn_momo',
    ])->assertRedirect();

    $payment2 = $booking->payments()->orderBy('id', 'desc')->first();
    expect($payment2->cash_register_session_id)->toBe($activeSession->id);
    expect($payment2->amount)->toBe(1000000); // 10 000 FCFA
    expect($payment2->method)->toBe('mtn_momo');

    // 4. Add disbursement (Sortie de caisse) of 5 000 FCFA
    $this->post(route('bookings.cash_register.disbursements.store'), [
        'amount' => '5000',
        'reason' => 'Achat café accueil',
    ])->assertRedirect();

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
        \Database\Seeders\RoomSeeder::class,
    ]);
    $tenant = Tenant::first();
    $room = Room::first();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);

    $this->actingAs($manager);

    // 1. Open the caisse with 10 000 FCFA
    $this->post(route('bookings.cash_register.open.store'), [
        'opening_amount' => '10000',
    ])->assertRedirect();

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
        'payment_method' => 'cash',
    ])->assertRedirect();

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
        'adults_count' => 2,
    ])->assertRedirect();

    // 4. Add group payment in cash
    $this->post(route('groups.payment.add', $group), [
        'amount' => '15000',
        'method' => 'cash',
        'distribution' => 'equal',
    ])->assertRedirect();

    $payment2 = \App\Models\Payment::whereNotNull('booking_id')->first();
    expect($payment2->cash_register_session_id)->toBe($activeSession->id);
    expect($payment2->amount)->toBe(1500000); // 15 000 FCFA
    expect($payment2->method)->toBe('cash');
});
