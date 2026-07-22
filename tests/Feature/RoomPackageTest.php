<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\PartnerOrganization;
use App\Models\Room;
use App\Models\RoomPackage;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function packTestSetup(): array
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);

    \App\Models\CashRegisterSession::create([
        'user_id'        => $user->id,
        'module'         => 'reception',
        'opening_amount' => 5000000,
        'opened_at'      => now(),
    ]);

    test()->actingAs($user);

    // Chambre 101 : 45 000 FCFA la nuit.
    return [$user, Room::where('number', '101')->first()];
}

function packBookingPayload(Customer $customer, Room $room, array $overrides = []): array
{
    return array_merge([
        'room_id'         => $room->id,
        'customer_id'     => $customer->id,
        'check_in'        => now()->addDay()->format('Y-m-d'),
        'check_out'       => now()->addDays(3)->format('Y-m-d'),   // 2 nuits
        'adults_count'    => 2,
        'children_count'  => 0,
        'source'          => 'direct',
        'custom_price'    => '90000',
        'payment_amount'  => '90000',
        'payment_method'  => 'cash',
    ], $overrides);
}

test('un pack par personne et par nuitée est facturé en supplément', function () {
    [$user, $room] = packTestSetup();

    $pack = RoomPackage::create([
        'name'         => 'Demi-pension',
        'meals'        => ['breakfast', 'dinner'],
        'pricing_mode' => RoomPackage::MODE_PER_PERSON_NIGHT,
        'price'        => 1000000,   // 10 000 FCFA
        'is_active'    => true,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id' => $pack->id,
    ]))->assertRedirect();

    $booking = Booking::latest()->first();

    // 10 000 x 2 nuits x 2 personnes = 40 000 FCFA.
    expect($booking->room_package_id)->toBe($pack->id)
        ->and($booking->package_amount)->toBe(4000000)
        ->and($booking->total_room_amount)->toBe(9000000)
        ->and($booking->total_amount)->toBe(13000000);   // 90 000 + 40 000

    $line = $booking->folioItems()->where('description', 'like', 'Formule%')->first();
    expect($line)->not->toBeNull()
        ->and($line->total_price)->toBe(4000000);
});

test('un pack au forfait ne dépend ni des nuitées ni du nombre de personnes', function () {
    [$user, $room] = packTestSetup();

    $pack = RoomPackage::create([
        'name'         => 'Forfait séjour',
        'pricing_mode' => RoomPackage::MODE_PER_STAY,
        'price'        => 2500000,   // 25 000 FCFA
        'is_active'    => true,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id' => $pack->id,
    ]))->assertRedirect();

    expect(Booking::latest()->first()->package_amount)->toBe(2500000);
});

test('la remise du pack et celle du partenaire se cumulent', function () {
    [$user, $room] = packTestSetup();

    $organization = PartnerOrganization::create([
        'name'                => 'Entreprise partenaire',
        'type'                => 'company',
        'is_active'           => true,
        'room_discount_type'  => PartnerOrganization::DISCOUNT_PERCENT,
        'room_discount_value' => 10,          // 9 000 FCFA
    ]);

    $pack = RoomPackage::create([
        'name'                => 'Pension complète',
        'meals'               => ['breakfast', 'lunch', 'dinner'],
        'pricing_mode'        => RoomPackage::MODE_PER_ROOM_NIGHT,
        'price'               => 1500000,     // 15 000 x 2 nuits = 30 000
        'room_discount_type'  => RoomPackage::DISCOUNT_PERCENT,
        'room_discount_value' => 5,           // 4 500 FCFA
        'is_active'           => true,
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id'          => $pack->id,
        'apply_partner_privileges' => 1,
    ]))->assertRedirect();

    $booking = Booking::latest()->first();

    // Remises cumulées : 9 000 + 4 500 = 13 500 FCFA.
    expect($booking->discount_amount)->toBe(1350000)
        ->and($booking->package_amount)->toBe(3000000)
        ->and($booking->total_amount)->toBe(9000000 + 3000000 - 1350000);

    // Chaque remise doit apparaître sur sa propre ligne, pour la traçabilité.
    $discountLines = $booking->folioItems()->where('type', FolioItem::TYPE_DISCOUNT)->get();
    expect($discountLines)->toHaveCount(2)
        ->and($discountLines->pluck('description')->implode(' '))
            ->toContain('Remise partenaire')
            ->toContain('Remise formule');
});

test('un pack non autorisé sur ce type de chambre est refusé', function () {
    [$user, $room] = packTestSetup();

    // Restreint à un type de chambre qui n'est pas celui de la 101.
    $pack = RoomPackage::create([
        'name'          => 'Pack suite uniquement',
        'pricing_mode'  => RoomPackage::MODE_PER_STAY,
        'price'         => 5000000,
        'room_type_ids' => [$room->room_type_id + 99],
        'is_active'     => true,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id' => $pack->id,
    ]))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->room_package_id)->toBeNull()
        ->and($booking->package_amount)->toBe(0);
});

test('un pack désactivé est refusé même si le formulaire le transmet', function () {
    [$user, $room] = packTestSetup();

    $pack = RoomPackage::create([
        'name'         => 'Ancien pack',
        'pricing_mode' => RoomPackage::MODE_PER_STAY,
        'price'        => 3000000,
        'is_active'    => false,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id' => $pack->id,
    ]))->assertRedirect();

    expect(Booking::latest()->first()->package_amount)->toBe(0);
});

test('une réservation sans formule reste inchangée', function () {
    [$user, $room] = packTestSetup();

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->room_package_id)->toBeNull()
        ->and($booking->package_amount)->toBe(0)
        ->and($booking->total_amount)->toBe(9000000);
});

test('le pack ne peut pas rendre la remise supérieure à l’hébergement', function () {
    [$user, $room] = packTestSetup();

    // Remise fixe volontairement démesurée : elle doit être plafonnée au brut.
    $pack = RoomPackage::create([
        'name'                => 'Pack remise excessive',
        'pricing_mode'        => RoomPackage::MODE_PER_STAY,
        'price'               => 100000,
        'room_discount_type'  => RoomPackage::DISCOUNT_AMOUNT,
        'room_discount_value' => 9000000,   // 90 000 FCFA par nuitée
        'is_active'           => true,
    ]);

    $customer = Customer::factory()->create();

    $this->post(route('bookings.store'), packBookingPayload($customer, $room, [
        'room_package_id' => $pack->id,
    ]))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->discount_amount)->toBe($booking->total_room_amount)
        ->and($booking->total_amount)->toBeGreaterThanOrEqual(0);
});
