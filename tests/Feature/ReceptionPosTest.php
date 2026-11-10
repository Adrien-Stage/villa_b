<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\ReceptionSale;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ServiceItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access reception pos routes', function () {
    $this->get(route('reception.pos.index'))->assertRedirect(route('login'));
    $this->get(route('reception.pos.history'))->assertRedirect(route('login'));
});

test('unauthorized roles are denied access to pos reception', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $housekeeping = User::factory()->create(['role' => 'housekeeping_staff']);

    $this->actingAs($housekeeping);
    $this->get(route('reception.pos.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertStatus(403);
});

test('receptionist can access reception pos terminal while manager is denied', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($receptionist);
    $response = $this->get(route('reception.pos.index'));
    $response->assertStatus(200);
    $response->assertSee('Mode POS Réception');

    $this->actingAs($manager);
    $this->get(route('reception.pos.index'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertStatus(403);
});

test('immediate payment requires an active cash register session', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $this->actingAs($receptionist);

    $response = $this->post(route('reception.pos.sales.store'), [
        'client_mode' => 'customer',
        'customer_name' => 'Client Test',
        'payment_method' => 'cash',
        'items' => [
            [
                'name' => 'Petit déjeuner',
                'category' => 'breakfast',
                'quantity' => 1,
                'unit_price' => 3500,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('caisse');
    expect(ReceptionSale::count())->toBe(0);
});

test('immediate cash payment creates sale and payment linked to cash session', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $this->actingAs($receptionist);

    // Ouvrir une caisse réception
    $session = CashRegisterSession::create([
        'user_id' => $receptionist->id,
        'module' => 'reception',
        'opening_amount' => 5000000, // 50 000 FCFA
        'opened_at' => now(),
    ]);

    $service = ServiceItem::create([
        'name' => 'Petit déjeuner buffet',
        'category' => 'breakfast',
        'price' => 350000, // 3 500 FCFA
        'is_active' => true,
    ]);

    $response = $this->post(route('reception.pos.sales.store'), [
        'client_mode' => 'customer',
        'customer_name' => 'Jean Dupont',
        'customer_phone' => '690000000',
        'payment_method' => 'cash',
        'items' => [
            [
                'service_item_id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'quantity' => 2,
                'unit_price' => 3500,
            ],
        ],
    ]);

    $sale = ReceptionSale::first();
    expect($sale)->not->toBeNull()
        ->and($sale->total_amount)->toBe(700000) // 7 000 FCFA
        ->and($sale->payment_status)->toBe('paid')
        ->and($sale->payment_method)->toBe('cash')
        ->and($sale->cash_register_session_id)->toBe($session->id);

    // Vérifier création du paiement lié à la caisse
    $payment = Payment::where('cash_register_session_id', $session->id)->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe(700000)
        ->and($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('completed');

    $response->assertRedirect(route('reception.pos.receipt', $sale));
});

test('room charge sale debits resident booking folio and recalculates totals', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $this->actingAs($receptionist);

    $customer = Customer::create([
        'first_name' => 'Paul',
        'last_name' => 'Biya',
        'phone' => '699999999',
    ]);

    $roomType = RoomType::create([
        'name' => 'Standard',
        'code' => 'STD',
        'base_capacity' => 2,
        'max_capacity' => 2,
        'base_price' => 2500000,
    ]);

    $room = Room::create([
        'room_type_id' => $roomType->id,
        'number' => '204',
        'floor' => 2,
        'status' => 'occupied',
    ]);

    $booking = Booking::create([
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'booking_number' => 'BK-TEST-204',
        'status' => BookingStatus::CHECKED_IN,
        'check_in' => now()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'total_nights' => 2,
        'price_per_night' => 2500000,
        'total_room_amount' => 5000000,
        'total_amount' => 5000000,
        'balance_due' => 5000000,
        'paid_amount' => 0,
    ]);

    $response = $this->post(route('reception.pos.sales.store'), [
        'client_mode' => 'room',
        'booking_id' => $booking->id,
        'payment_method' => 'room_charge',
        'items' => [
            [
                'name' => 'Blanchisserie costume',
                'category' => 'laundry',
                'quantity' => 1,
                'unit_price' => 5000,
            ],
            [
                'name' => 'Petit déjeuner',
                'category' => 'breakfast',
                'quantity' => 2,
                'unit_price' => 3500,
            ],
        ],
    ]);

    $sale = ReceptionSale::first();
    expect($sale)->not->toBeNull()
        ->and($sale->payment_status)->toBe('charged_to_room')
        ->and($sale->total_amount)->toBe(1200000) // 12 000 FCFA
        ->and($sale->booking_id)->toBe($booking->id)
        ->and($sale->room_number)->toBe('204');

    // Vérifier création des FolioItem
    $folioItems = FolioItem::where('booking_id', $booking->id)->get();
    expect($folioItems->count())->toBe(2);

    // Vérifier mise à jour du booking
    $booking->refresh();
    expect($booking->balance_due)->toBe(6200000); // 50 000 + 12 000 FCFA

    $response->assertRedirect(route('reception.pos.receipt', $sale));
});

test('receipt and history views render properly for receptionist', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    $receptionist = User::factory()->create(['role' => 'reception']);
    $this->actingAs($receptionist);

    $sale = ReceptionSale::create([
        'sale_number' => 'POS-REC-TEST-001',
        'user_id' => $receptionist->id,
        'customer_name' => 'Jean Dupont',
        'payment_type' => 'immediate',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'subtotal' => 1200000,
        'tax_amount' => 193501,
        'total_amount' => 1200000,
    ]);

    $this->get(route('reception.pos.receipt', $sale))
        ->assertStatus(200)
        ->assertSee('POS-REC-TEST-001')
        ->assertSee('Jean Dupont');

    $this->get(route('reception.pos.history'))
        ->assertStatus(200)
        ->assertSee('Historique des Ventes POS Réception')
        ->assertSee('POS-REC-TEST-001');
});

