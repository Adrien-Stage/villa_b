<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ReceptionSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ShopOrder;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->tenant = Tenant::create([
        'name' => 'Villa Boutanga',
        'slug' => 'villa-boutanga',
        'currency' => 'XAF',
        'is_active' => true,
    ]);

    $this->manager = User::factory()->create([
        'role' => 'manager',
        'is_active' => true,
    ]);

    $this->receptionist = User::factory()->create([
        'role' => 'reception',
        'is_active' => true,
    ]);

    $this->customer = Customer::create([
        'first_name' => 'Larissa',
        'last_name' => 'Atangana',
        'email' => 'larissa.atangana@example.com',
        'phone' => '+237 656111110',
        'nationality' => 'Camerounaise',
        'is_vip' => true,
        'is_blacklisted' => false,
    ]);

    $this->roomType = RoomType::create([
        'name' => 'Suite Prestige',
        'code' => 'PRESTIGE',
        'base_capacity' => 2,
        'max_capacity' => 2,
        'base_price' => 5000000,
    ]);

    $this->room = Room::create([
        'number' => '101',
        'room_type_id' => $this->roomType->id,
        'floor' => 1,
        'status' => 'available',
    ]);
});

test('it displays the invoices tab with financial KPIs and tab buttons', function () {
    $this->actingAs($this->receptionist);

    $response = $this->get(route('customers.show', ['customer' => $this->customer, 'tab' => 'invoices']));

    $response->assertStatus(200);
    $response->assertSee('Toutes les Factures', false);
    $response->assertSee('Total Facturé (TTC)', false);
    $response->assertSee('Total Réglé', false);
    $response->assertSee('Reste à payer', false);
    $response->assertSee('Périodes rapides :', false);
    $response->assertSee('Ce mois-ci', false);
    $response->assertSee('Mois dernier', false);
});

test('it aggregates invoices and bills from accommodation, boutique, restaurant and reception', function () {
    $this->actingAs($this->receptionist);

    // 1. Facture d'hébergement
    $booking = Booking::create([
        'booking_number' => 'BKG-2026-0001',
        'customer_id' => $this->customer->id,
        'room_id' => $this->room->id,
        'check_in' => Carbon::parse('2026-09-10'),
        'check_out' => Carbon::parse('2026-09-12'),
        'price_per_night' => 5000000,
        'total_room_amount' => 10000000,
        'total_nights' => 2,
        'total_amount' => 10000000,
        'paid_amount' => 10000000,
        'balance_due' => 0,
        'status' => 'completed',
    ]);

    Invoice::create([
        'booking_id' => $booking->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'FAC-HOTEL-999',
        'invoice_date' => Carbon::parse('2026-09-12'),
        'subtotal' => 10000000,
        'tax_amount' => 0,
        'total_amount' => 10000000,
        'paid_amount' => 10000000,
        'balance_due' => 0,
        'status' => 'paid',
    ]);

    // 2. Commande boutique
    $shopOrder = ShopOrder::create([
        'order_number' => 'SHOP-TICKET-888',
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->full_name,
        'total_items' => 2,
        'subtotal' => 1500000,
        'tax_amount' => 0,
        'total_amount' => 1500000,
        'payment_status' => 'paid',
        'payment_method' => 'card',
        'created_by' => $this->receptionist->id,
    ]);
    $shopOrder->created_at = Carbon::parse('2026-09-15 14:00:00');
    $shopOrder->save();

    // 3. Commande restaurant
    $restaurantOrder = RestaurantCustomerOrder::create([
        'table_number' => 'T5',
        'booking_id' => $booking->id,
        'customer_name' => $this->customer->full_name,
        'customer_phone' => $this->customer->phone,
        'status' => 'served',
        'total_amount' => 2500000,
        'amount_paid' => 2500000,
        'payment_status' => 'paid',
        'payment_method' => 'room_charge',
    ]);
    $restaurantOrder->created_at = Carbon::parse('2026-09-11 20:30:00');
    $restaurantOrder->save();

    // 4. Vente POS Réception
    $receptionSale = ReceptionSale::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->receptionist->id,
        'sale_number' => 'REC-SALE-777',
        'customer_id' => $this->customer->id,
        'customer_name' => $this->customer->full_name,
        'customer_phone' => $this->customer->phone,
        'payment_type' => 'direct',
        'payment_method' => 'cash',
        'payment_status' => 'completed',
        'subtotal' => 500000,
        'tax_amount' => 0,
        'total_amount' => 500000,
    ]);
    $receptionSale->created_at = Carbon::parse('2026-09-16 10:15:00');
    $receptionSale->save();

    $response = $this->get(route('customers.show', [
        'customer' => $this->customer,
        'tab' => 'invoices',
    ]));

    $response->assertStatus(200);

    // Vérification de la présence des 4 pièces comptables
    $response->assertSee('FAC-HOTEL-999');
    $response->assertSee('SHOP-TICKET-888');
    $response->assertSee('CMD-REST-');
    $response->assertSee('REC-SALE-777');

    // Vérification des labels de service
    $response->assertSee('Hébergement');
    $response->assertSee('Boutique');
    $response->assertSee('Restaurant');
    $response->assertSee('POS Réception');
});

test('it filters customer invoices by precise date range', function () {
    $this->actingAs($this->receptionist);

    // Document en septembre 2026
    $septOrder = ShopOrder::create([
        'order_number' => 'SHOP-SEPTEMBRE-2026',
        'customer_id' => $this->customer->id,
        'total_items' => 1,
        'subtotal' => 200000,
        'tax_amount' => 0,
        'total_amount' => 200000,
        'payment_status' => 'paid',
        'created_by' => $this->receptionist->id,
    ]);
    $septOrder->created_at = Carbon::parse('2026-09-15 11:00:00');
    $septOrder->save();

    // Document en août 2026
    $augustOrder = ShopOrder::create([
        'order_number' => 'SHOP-AOUT-2026',
        'customer_id' => $this->customer->id,
        'total_items' => 1,
        'subtotal' => 300000,
        'tax_amount' => 0,
        'total_amount' => 300000,
        'payment_status' => 'paid',
        'created_by' => $this->receptionist->id,
    ]);
    $augustOrder->created_at = Carbon::parse('2026-08-10 11:00:00');
    $augustOrder->save();

    // Requête filtrée sur septembre (du 01 au 30 septembre)
    $response = $this->get(route('customers.show', [
        'customer' => $this->customer,
        'tab' => 'invoices',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]));

    $response->assertStatus(200);
    $response->assertSee('SHOP-SEPTEMBRE-2026');
    $response->assertDontSee('SHOP-AOUT-2026');
});

test('it filters customer invoices by service', function () {
    $this->actingAs($this->receptionist);

    // Boutique
    ShopOrder::create([
        'order_number' => 'SHOP-ONLY-TEST',
        'customer_id' => $this->customer->id,
        'total_items' => 1,
        'subtotal' => 100000,
        'tax_amount' => 0,
        'total_amount' => 100000,
        'payment_status' => 'paid',
        'created_by' => $this->receptionist->id,
        'created_at' => now(),
    ]);

    // Réception
    ReceptionSale::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->receptionist->id,
        'sale_number' => 'REC-ONLY-TEST',
        'customer_id' => $this->customer->id,
        'payment_type' => 'direct',
        'payment_method' => 'cash',
        'payment_status' => 'completed',
        'subtotal' => 100000,
        'tax_amount' => 0,
        'total_amount' => 100000,
        'created_at' => now(),
    ]);

    // Filtrer uniquement la boutique
    $response = $this->get(route('customers.show', [
        'customer' => $this->customer,
        'tab' => 'invoices',
        'service' => 'shop',
    ]));

    $response->assertStatus(200);
    $response->assertSee('SHOP-ONLY-TEST');
    $response->assertDontSee('REC-ONLY-TEST');
});

test('it handles edge-case dates like September 31st safely without 500 error', function () {
    $this->actingAs($this->receptionist);

    $response = $this->get(route('customers.show', [
        'customer' => $this->customer,
        'tab' => 'invoices',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-31',
    ]));

    $response->assertStatus(200);
});
