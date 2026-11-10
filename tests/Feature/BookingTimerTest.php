<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        TenantSeeder::class,
        RoomTypeSeeder::class,
        RoomSeeder::class,
    ]);
});

test('un sejour en cours avec actual_check_in affiche le minuteur et l heure de depart configuree', function () {
    $tenant = Tenant::first();
    $settings = $tenant->settings ?? [];
    $settings['reception'] = ['check_in_time' => '15:00', 'check_out_time' => '11:00'];
    $tenant->update(['settings' => $settings]);

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $checkInAt = now()->subHours(3);
    $checkOutDate = now()->addDays(2);

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => $checkInAt->toDateString(),
        'check_out'         => $checkOutDate->toDateString(),
        'check_in_time'     => '15:00',
        'actual_check_in'   => $checkInAt,
        'adults_count'      => 2,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 0,
        'balance_due'       => 9000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $response = $this->actingAs($reception)->get(route('bookings.show', $booking));

    $response->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('bookingTimer(', false)
        ->assertSee("Fin prévue le {$checkOutDate->format('d/m/Y')} à 11h00")
        ->assertDontSee("Basé sur l'arrivée prévue");

    expect($booking->shouldDisplayStayTimer('11:00'))->toBeTrue()
        ->and($booking->hasOverstayedCheckout('11:00'))->toBeFalse();
});

test('un sejour de demonstration sans actual_check_in et avec date de check-out depassee affiche le minuteur de depassement', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'booking_number'    => 'DEMO-0020',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => '2026-08-31',
        'check_out'         => '2026-09-05',
        'check_in_time'     => '14:00',
        'actual_check_in'   => null,
        'actual_check_out'  => null,
        'adults_count'      => 2,
        'total_nights'      => 5,
        'price_per_night'   => 4500000,
        'total_room_amount' => 22500000,
        'total_amount'      => 22500000,
        'paid_amount'       => 0,
        'balance_due'       => 22500000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $response = $this->actingAs($reception)->get(route('bookings.show', $booking));

    $response->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('Check-out dépassé')
        ->assertSee('bookingTimer(', false)
        ->assertSee("Basé sur l'arrivée prévue le 31/08/2026 à 14h00", false)
        ->assertSee('Départ initialement prévu le 05/09/2026 à 12h00');

    expect($booking->effectiveCheckInAt('14:00')->format('Y-m-d H:i:s'))->toBe('2026-08-31 14:00:00')
        ->and($booking->scheduledCheckOutAt('12:00')->format('Y-m-d H:i:s'))->toBe('2026-09-05 12:00:00')
        ->and($booking->hasOverstayedCheckout('12:00'))->toBeTrue()
        ->and($booking->shouldDisplayStayTimer('12:00'))->toBeTrue();
});

test('un sejour en cours ayant depasse sa date de check-out affiche toujours le minuteur meme si actual_check_out a ete enregistre', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDays(4)->toDateString(),
        'check_out'         => now()->subDays(2)->toDateString(),
        'check_in_time'     => '14:00',
        'actual_check_in'   => now()->subDays(4),
        'actual_check_out'  => now()->subDays(3),
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 9000000,
        'balance_due'       => 0,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('Minuteur de séjour en cours')
        ->assertSee('Check-out dépassé');

    expect($booking->shouldDisplayStayTimer('12:00'))->toBeTrue();
});

test('le minuteur reste masque pour un sejour solde avant echeance ou pour les statuts non en sejour', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $settledNotOverdue = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->toDateString(),
        'check_out'         => now()->addDays(2)->toDateString(),
        'actual_check_in'   => now()->subHour(),
        'actual_check_out'  => now(),
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'total_amount'      => 9000000,
        'paid_amount'       => 9000000,
        'balance_due'       => 0,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.show', $settledNotOverdue))
        ->assertOk()
        ->assertDontSee('Minuteur de séjour en cours');

    foreach ([BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::COMPLETED, BookingStatus::CANCELLED] as $status) {
        $otherRoom = Room::where('number', '102')->first() ?? $room;
        $nonCheckedIn = Booking::create([
            'customer_id'       => $customer->id,
            'room_id'           => $otherRoom->id,
            'check_in'          => '2026-08-01',
            'check_out'         => '2026-08-03',
            'adults_count'      => 1,
            'total_nights'      => 2,
            'price_per_night'   => 4500000,
            'total_room_amount' => 9000000,
            'total_amount'      => 9000000,
            'paid_amount'       => 0,
            'balance_due'       => 9000000,
            'status'            => $status,
            'source'            => 'direct',
            'created_by'        => $reception->id,
        ]);

        $this->actingAs($reception)
            ->get(route('bookings.show', $nonCheckedIn))
            ->assertOk()
            ->assertDontSee('Minuteur de séjour en cours');
    }
});

test('la liste des reservations signale un badge de depassement sur les sejours dont le check-out est echu', function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    Booking::create([
        'booking_number'    => 'DEMO-OVERDUE-1',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => now()->subDays(5)->toDateString(),
        'check_out'         => now()->subDay()->toDateString(),
        'actual_check_in'   => null,
        'adults_count'      => 1,
        'total_nights'      => 4,
        'price_per_night'   => 4500000,
        'total_room_amount' => 18000000,
        'total_amount'      => 18000000,
        'paid_amount'       => 0,
        'balance_due'       => 18000000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    $this->actingAs($reception)
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('DEMO-OVERDUE-1')
        ->assertSee('Dépassement');
});

test('les limites horaires de depart et l heure de check-out configuree determinent exactement les nuits en surplus', function () {
    $tenant = Tenant::first();
    $settings = $tenant->settings ?? [];
    $settings['reception'] = ['check_in_time' => '14:00', 'check_out_time' => '11:00'];
    $tenant->update(['settings' => $settings]);

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => '2026-11-08',
        'check_out'         => '2026-11-10',
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 40000,
        'total_room_amount' => 80000,
        'total_amount'      => 80000,
        'paid_amount'       => 0,
        'balance_due'       => 80000,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    expect($booking->overstayNights('12:00', \Illuminate\Support\Carbon::parse('2026-11-10 11:59:59')))->toBe(0)
        ->and($booking->overstayNights('12:00', \Illuminate\Support\Carbon::parse('2026-11-10 12:00:00')))->toBe(0)
        ->and($booking->overstayNights('12:00', \Illuminate\Support\Carbon::parse('2026-11-10 12:00:01')))->toBe(1)
        ->and($booking->overstayNights('12:00', \Illuminate\Support\Carbon::parse('2026-11-11 12:00:00')))->toBe(1)
        ->and($booking->overstayNights('12:00', \Illuminate\Support\Carbon::parse('2026-11-11 12:00:01')))->toBe(2)
        ->and($booking->overstayNights(null, \Illuminate\Support\Carbon::parse('2026-11-10 11:00:01')))->toBe(1)
        ->and($booking->overstayRoomAmount(null, \Illuminate\Support\Carbon::parse('2026-11-10 11:00:01')))->toBe(40000);
});

test('un sejour en cours ayant depasse le check-out applique automatiquement le surplus d hebergement sur le folio et le solde du', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 13:05:00'));

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    \App\Models\CashRegisterSession::create([
        'user_id' => $reception->id,
        'module' => 'reception',
        'opening_amount' => 0,
        'opened_at' => now(),
    ]);

    $booking = Booking::create([
        'booking_number'    => 'DEMO-0005',
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => '2026-10-28',
        'check_out'         => '2026-11-02',
        'check_in_time'     => '14:00',
        'actual_check_in'   => null,
        'adults_count'      => 2,
        'total_nights'      => 5,
        'price_per_night'   => 40000,   // 400 F
        'total_room_amount' => 200000,  // 2 000 F
        'extras_amount'     => 0,
        'tax_amount'        => 38500,   // 385 F (TVA additive démo)
        'total_amount'      => 238500,  // 2 385 F
        'deposit_amount'    => 71600,
        'paid_amount'       => 119250,  // 1 192,50 F
        'balance_due'       => 119250,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'ota_bookingcom',
        'created_by'        => $reception->id,
    ]);

    \App\Models\FolioItem::create([
        'booking_id'       => $booking->id,
        'customer_id'      => $customer->id,
        'type'             => \App\Models\FolioItem::TYPE_ROOM,
        'description'      => 'Hébergement — 5 nuit(s)',
        'quantity'         => 5,
        'unit_price'       => 40000,
        'total_price'      => 200000,
        'is_complimentary' => false,
        'earns_points'     => true,
        'occurred_at'      => \Illuminate\Support\Carbon::parse('2026-10-28 14:00:00'),
        'recorded_by'      => $reception->id,
    ]);

    // +8j 1h 5min => 9 nuits supplémentaires = +3 600 F (360000 centimes)
    expect($booking->overstayNights('12:00'))->toBe(9)
        ->and($booking->overstayRoomAmount('12:00'))->toBe(360000)
        ->and($booking->effectiveTotalNights('12:00'))->toBe(14)
        ->and($booking->effectiveRoomAmount('12:00'))->toBe(560000)
        ->and($booking->effectiveTotalAmount('12:00'))->toBe(598500)
        ->and($booking->effectiveBalanceDue('12:00'))->toBe(479250)
        ->and($booking->getConsumedBalance('12:00'))->toBe(479250);

    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('14 nuits')
        ->assertSee('5 prévues + 9 de dépassement')
        ->assertSee('+9 nuits · +3 600 FCFA')
        ->assertSee("Hébergement — 14 nuit(s) — Chambre {$room->number}")
        ->assertSee('5 600 FCFA')
        ->assertSee('Dont surplus hébergement (+9 nuits)')
        ->assertSee('+3 600 FCFA')
        ->assertSee('5 985 FCFA')
        ->assertSee('4 793 FCFA')
        ->assertSee('Encaisser')
        ->assertSee('value="4793"', false);

    $this->actingAs($reception)
        ->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('DEMO-0005')
        ->assertSee('14 nuits')
        ->assertSee('(+9 dép.)')
        ->assertSee('5 985')
        ->assertSee('+3 600 F');
});

test('un sejour initialement solde mais en depassement bloque le check-out jusqu a encaissement du surplus puis met a jour le folio et la facture', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 14:30:00'));

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $session = \App\Models\CashRegisterSession::create([
        'user_id' => $reception->id,
        'module' => 'reception',
        'opening_amount' => 0,
        'opened_at' => now(),
    ]);

    $booking = Booking::create([
        'customer_id'       => $customer->id,
        'room_id'           => $room->id,
        'check_in'          => '2026-11-07',
        'check_out'         => '2026-11-09',
        'actual_check_in'   => \Illuminate\Support\Carbon::parse('2026-11-07 14:00:00'),
        'adults_count'      => 1,
        'total_nights'      => 2,
        'price_per_night'   => 4500000,
        'total_room_amount' => 9000000,
        'tax_amount'        => 0,
        'total_amount'      => 9000000,
        'paid_amount'       => 9000000,
        'balance_due'       => 0,
        'status'            => BookingStatus::CHECKED_IN,
        'source'            => 'direct',
        'created_by'        => $reception->id,
    ]);

    \App\Models\Payment::create([
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'amount' => 9000000,
        'currency' => 'XAF',
        'method' => 'cash',
        'status' => 'completed',
        'reference' => 'PAY-2026-000100',
        'paid_at' => now()->subDays(3),
        'processed_by' => $reception->id,
        'cash_register_session_id' => $session->id,
    ]);

    \App\Models\FolioItem::create([
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'type' => \App\Models\FolioItem::TYPE_ROOM,
        'description' => "Hébergement 2 nuit(s) — Chambre {$room->number}",
        'quantity' => 2,
        'unit_price' => 4500000,
        'total_price' => 9000000,
        'is_complimentary' => false,
        'earns_points' => true,
        'occurred_at' => now()->subDays(3),
        'recorded_by' => $reception->id,
    ]);

    // 09/11 12:00 -> 10/11 14:30 = 2 nuits de surplus = 90 000 FCFA
    expect($booking->overstayNights('12:00'))->toBe(2)
        ->and($booking->effectiveBalanceDue('12:00'))->toBe(9000000);

    // Même avec balance_due = 0 en base, Encaisser reste disponible et le check-out est bloqué
    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('Encaisser')
        ->assertSee('value="90000"', false)
        ->assertSee('180 000 FCFA')
        ->assertSee('90 000 FCFA');

    expect(fn () => app(\App\Services\CheckOutService::class)->process($booking->fresh()))
        ->toThrow(\LogicException::class, '90 000 FCFA');

    // Règlement partiel : 45 000 FCFA -> solde restant de 45 000 FCFA, check_out pas encore avancé
    $this->actingAs($reception)
        ->post(route('bookings.payment.add', $booking), [
            'amount' => 45000,
            'method' => 'cash',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, '45 000 FCFA'));

    expect($booking->fresh()->effectiveBalanceDue('12:00'))->toBe(4500000);

    // Règlement du reliquat : 45 000 FCFA -> synchronise nuits (4), montant (180 000), check_out (11/11) et folio
    $this->actingAs($reception)
        ->post(route('bookings.payment.add', $booking), [
            'amount' => 45000,
            'method' => 'cash',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, '0 FCFA'));

    $booking->refresh();
    $roomFolio = $booking->folioItems()->where('type', \App\Models\FolioItem::TYPE_ROOM)->sole();

    expect($booking->total_nights)->toBe(4)
        ->and($booking->total_room_amount)->toBe(18000000)
        ->and($booking->total_amount)->toBe(18000000)
        ->and($booking->paid_amount)->toBe(18000000)
        ->and($booking->balance_due)->toBe(0)
        ->and($booking->check_out->toDateString())->toBe('2026-11-11')
        ->and((float) $roomFolio->quantity)->toBe(4.0)
        ->and($roomFolio->total_price)->toBe(18000000)
        ->and($roomFolio->description)->toContain('4 nuit(s)');

    $invoice = app(\App\Services\CheckOutService::class)->process($booking);
    $roomInvoiceItem = $invoice->items()->where('category', 'room')->sole();

    expect($invoice->total_amount)->toBe(18000000)
        ->and($invoice->balance_due)->toBe(0)
        ->and((float) $roomInvoiceItem->quantity)->toBe(4.0)
        ->and($roomInvoiceItem->total_price)->toBe(18000000);
});

test('un sejour offert en depassement conserve un surplus financier nul', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 15:00:00'));

    $reception = User::factory()->create(['role' => 'reception']);
    $customer = Customer::factory()->create();
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'customer_id'         => $customer->id,
        'room_id'             => $room->id,
        'check_in'            => '2026-11-08',
        'check_out'           => '2026-11-09',
        'actual_check_in'     => now()->subDays(2),
        'is_complimentary'    => true,
        'complimentary_value' => 4500000,
        'adults_count'        => 1,
        'total_nights'        => 1,
        'price_per_night'     => 0,
        'total_room_amount'   => 0,
        'total_amount'        => 0,
        'paid_amount'         => 0,
        'balance_due'         => 0,
        'status'              => BookingStatus::CHECKED_IN,
        'source'              => 'direct',
        'created_by'          => $reception->id,
    ]);

    expect($booking->overstayNights('12:00'))->toBe(2)
        ->and($booking->overstayRoomAmount('12:00'))->toBe(0)
        ->and($booking->effectiveTotalAmount('12:00'))->toBe(0)
        ->and($booking->effectiveBalanceDue('12:00'))->toBe(0);

    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('Séjour offert · +2 nuits')
        ->assertDontSee('Encaisser');
});

test('un dossier groupe inclut le surplus des chambres en depassement lors du paiement et du check-out global', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 13:00:00'));

    $reception = User::factory()->create(['role' => 'reception']);
    $session = \App\Models\CashRegisterSession::create([
        'user_id' => $reception->id,
        'module' => 'reception',
        'opening_amount' => 0,
        'opened_at' => now(),
    ]);

    $customer = Customer::factory()->create();
    $room101 = Room::where('number', '101')->first();
    $room102 = Room::where('number', '102')->first();

    $group = \App\Models\GroupBooking::create([
        'contact_customer_id' => $customer->id,
        'group_name' => 'Mission Audit',
        'event_type' => 'corporate',
        'start_date' => '2026-11-08',
        'end_date' => '2026-11-09',
        'status' => 'in_house',
        'tenant_id' => Tenant::first()->id,
    ]);

    foreach ([$room101, $room102] as $room) {
        $b = Booking::create([
            'group_booking_id' => $group->id,
            'customer_id' => $customer->id,
            'room_id' => $room->id,
            'status' => BookingStatus::CHECKED_IN,
            'check_in' => '2026-11-08',
            'check_out' => '2026-11-09',
            'actual_check_in' => \Illuminate\Support\Carbon::parse('2026-11-08 14:00:00'),
            'adults_count' => 1,
            'total_nights' => 1,
            'price_per_night' => 4000000,
            'total_room_amount' => 4000000,
            'total_amount' => 4000000,
            'paid_amount' => 4000000,
            'balance_due' => 0,
            'source' => 'group',
            'created_by' => $reception->id,
        ]);

        \App\Models\Payment::create([
            'booking_id' => $b->id,
            'customer_id' => $customer->id,
            'amount' => 4000000,
            'currency' => 'XAF',
            'method' => 'cash',
            'status' => 'completed',
            'reference' => 'PAY-GRP-' . $room->number,
            'paid_at' => now()->subDays(2),
            'processed_by' => $reception->id,
            'cash_register_session_id' => $session->id,
        ]);

        \App\Models\FolioItem::create([
            'booking_id' => $b->id,
            'customer_id' => $customer->id,
            'type' => \App\Models\FolioItem::TYPE_ROOM,
            'description' => "Hébergement 1 nuit(s) — Chambre {$room->number}",
            'quantity' => 1,
            'unit_price' => 4000000,
            'total_price' => 4000000,
            'is_complimentary' => false,
            'earns_points' => true,
            'occurred_at' => now()->subDays(2),
            'recorded_by' => $reception->id,
        ]);
    }

    // 2 chambres × 2 nuits de surplus × 40 000 FCFA = 160 000 FCFA dus
    $this->actingAs($reception)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertSee('Dépassement')
        ->assertSee('+2 nuits dép.')
        ->assertSee('160 000 FCFA')
        ->assertSee('Encaisser');

    // Le check-out global doit refuser tant que le surplus du groupe n'est pas réglé
    $this->actingAs($reception)
        ->post(route('groups.checkOutAll', $group))
        ->assertSessionHasErrors('checkout');

    // Paiement du surplus groupe puis check-out global
    $this->actingAs($reception)
        ->post(route('groups.payment.add', $group), [
            'amount' => 160000,
            'method' => 'cash',
            'distribution' => 'equal',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, '0 FCFA'));

    $this->actingAs($reception)
        ->post(route('groups.checkOutAll', $group))
        ->assertSessionHasNoErrors();

    expect($group->fresh()->status)->toBe('completed');
});

test('le reglement sur compte depot partenaire inclut le surplus d un sejour en depassement', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10 15:00:00'));

    \App\Support\RoleCatalog::sync();
    $finance = User::factory()->create(['role' => 'finance_manager']);
    $reception = User::factory()->create(['role' => 'reception']);

    $partner = \App\Models\PartnerOrganization::create([
        'name' => 'Groupe Énergie',
        'type' => 'company',
        'is_active' => true,
        'has_convention' => false,
        'room_discount_type' => 'none',
    ]);
    $customer = Customer::factory()->create(['partner_organization_id' => $partner->id]);
    $room = Room::where('number', '101')->first();

    $booking = Booking::create([
        'booking_number' => 'WAL-OVERDUE-01',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'status' => BookingStatus::CHECKED_IN,
        'check_in' => '2026-11-08',
        'check_out' => '2026-11-09',
        'actual_check_in' => \Illuminate\Support\Carbon::parse('2026-11-08 14:00:00'),
        'adults_count' => 1,
        'total_nights' => 1,
        'price_per_night' => 5000000,
        'total_room_amount' => 5000000,
        'total_amount' => 5000000,
        'paid_amount' => 0,
        'balance_due' => 5000000,
        'source' => 'direct',
        'created_by' => $reception->id,
    ]);

    \App\Models\FolioItem::create([
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'type' => \App\Models\FolioItem::TYPE_ROOM,
        'description' => "Hébergement 1 nuit(s) — Chambre {$room->number}",
        'quantity' => 1,
        'unit_price' => 5000000,
        'total_price' => 5000000,
        'is_complimentary' => false,
        'earns_points' => true,
        'occurred_at' => now()->subDays(2),
        'recorded_by' => $reception->id,
    ]);

    $service = app(\App\Services\PartnerWalletService::class);
    $service->topup($partner, [
        'request_key' => (string) \Illuminate\Support\Str::uuid(),
        'amount' => 500000,
        'method' => 'bank_transfer',
        'reference' => 'VIR-OVERDUE-01',
    ], $finance);

    // 1 nuit prévue + 2 nuits de dépassement = 150 000 FCFA
    $this->actingAs($reception)
        ->get(route('bookings.show', $booking))
        ->assertOk()
        ->assertSee('value="150000"', false);

    $service->settle($partner, $booking, null, [
        'request_key' => (string) \Illuminate\Support\Str::uuid(),
        'amount' => 150000,
        'authorized_by' => 'DAF Partenaire',
        'authorization_reference' => 'ACC-2026-099',
    ], $reception);

    $booking->refresh();
    expect($booking->total_nights)->toBe(3)
        ->and($booking->total_room_amount)->toBe(15000000)
        ->and($booking->paid_amount)->toBe(15000000)
        ->and($booking->balance_due)->toBe(0);
});
