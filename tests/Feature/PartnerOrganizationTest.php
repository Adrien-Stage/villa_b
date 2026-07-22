<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\PartnerOrganization;
use App\Models\Room;
use App\Models\ServiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Prépare un établissement exploitable : chambres, manager connecté et caisse
 * ouverte (sans caisse, la réservation est refusée en amont).
 */
function partnerTestSetup(): array
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

    // Chambre 101 : tarif de base 45 000 FCFA la nuit.
    return [$user, Room::where('number', '101')->first()];
}

function bookingPayload(Customer $customer, Room $room, array $overrides = []): array
{
    return array_merge([
        'room_id'         => $room->id,
        'customer_id'     => $customer->id,
        'check_in'        => now()->addDay()->format('Y-m-d'),
        'check_out'       => now()->addDays(3)->format('Y-m-d'),   // 2 nuits
        'adults_count'    => 1,
        'children_count'  => 0,
        'source'          => 'direct',
        'custom_price'    => '90000',    // 2 x 45 000
        'payment_amount'  => '90000',
        'payment_method'  => 'cash',
        'apply_partner_privileges' => 1,
    ], $overrides);
}

test('la remise partenaire en pourcentage est appliquée automatiquement à la réservation', function () {
    [$user, $room] = partnerTestSetup();

    $organization = PartnerOrganization::create([
        'name'                => 'Total Energies Cameroun',
        'type'                => 'company',
        'is_active'           => true,
        'room_discount_type'  => PartnerOrganization::DISCOUNT_PERCENT,
        'room_discount_value' => 20,
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();

    $booking = Booking::latest()->first();

    // 90 000 FCFA bruts, 20 % de remise = 18 000 FCFA, net 72 000 FCFA.
    expect($booking->partner_organization_id)->toBe($organization->id)
        ->and($booking->total_room_amount)->toBe(9000000)
        ->and($booking->discount_amount)->toBe(1800000)
        ->and($booking->total_amount)->toBe(7200000);

    // La remise doit apparaître en ligne distincte du folio, en négatif.
    $discountLine = $booking->folioItems()->where('type', FolioItem::TYPE_DISCOUNT)->first();
    expect($discountLine)->not->toBeNull()
        ->and($discountLine->total_price)->toBe(-1800000)
        ->and($discountLine->description)->toContain('Total Energies Cameroun');
});

test('la remise partenaire au montant est multipliée par le nombre de nuitées', function () {
    [$user, $room] = partnerTestSetup();

    $organization = PartnerOrganization::create([
        'name'                => 'Ambassade de France',
        'type'                => 'embassy',
        'is_active'           => true,
        'room_discount_type'  => PartnerOrganization::DISCOUNT_AMOUNT,
        'room_discount_value' => 500000,   // 5 000 FCFA par nuitée
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();

    $booking = Booking::latest()->first();

    // 5 000 x 2 nuits = 10 000 FCFA de remise.
    expect($booking->discount_amount)->toBe(1000000)
        ->and($booking->total_amount)->toBe(8000000);
});

test('un client sans organisation ne reçoit aucune remise', function () {
    [$user, $room] = partnerTestSetup();

    $customer = Customer::factory()->create(['partner_organization_id' => null]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->partner_organization_id)->toBeNull()
        ->and($booking->discount_amount)->toBe(0)
        ->and($booking->total_amount)->toBe(9000000);
});

test('une convention échue n’accorde plus de remise', function () {
    [$user, $room] = partnerTestSetup();

    $organization = PartnerOrganization::create([
        'name'                => 'Convention expirée',
        'type'                => 'company',
        'is_active'           => true,
        'room_discount_type'  => PartnerOrganization::DISCOUNT_PERCENT,
        'room_discount_value' => 30,
        'valid_from'          => now()->subYear()->format('Y-m-d'),
        'valid_until'         => now()->subMonth()->format('Y-m-d'),
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->partner_organization_id)->toBeNull()
        ->and($booking->discount_amount)->toBe(0);
});

test('la réception peut écarter la convention pour un séjour privé', function () {
    [$user, $room] = partnerTestSetup();

    $organization = PartnerOrganization::create([
        'name'                => 'Organisation X',
        'type'                => 'company',
        'is_active'           => true,
        'room_discount_type'  => PartnerOrganization::DISCOUNT_PERCENT,
        'room_discount_value' => 25,
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room, [
        'apply_partner_privileges' => 0,
    ]))->assertRedirect();

    $booking = Booking::latest()->first();

    expect($booking->partner_organization_id)->toBeNull()
        ->and($booking->discount_amount)->toBe(0);
});

test('une prestation couverte par la convention est facturée à zéro même si le client prétend le contraire', function () {
    [$user, $room] = partnerTestSetup();

    $service = ServiceItem::create([
        'category'  => ServiceItem::CATEGORY_SPA,
        'name'      => 'Massage relaxant',
        'price'     => 1500000,     // 15 000 FCFA
        'is_active' => true,
    ]);

    $organization = PartnerOrganization::create([
        'name'                  => 'ONG Santé Plus',
        'type'                  => 'ngo',
        'is_active'             => true,
        'room_discount_type'    => PartnerOrganization::DISCOUNT_NONE,
        'free_service_item_ids' => [$service->id],
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);

    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();
    $booking = Booking::latest()->first();

    // Les prestations ne s'ajoutent qu'en cours de séjour.
    $booking->update(['status' => \App\Enums\BookingStatus::CHECKED_IN, 'actual_check_in' => now()]);

    // Le navigateur envoie volontairement is_complimentary = 0 : c'est le
    // serveur qui doit imposer la gratuité conventionnelle.
    $this->post(route('bookings.folio.add', $booking), [
        'type'             => 'spa',
        'description'      => 'Massage relaxant',
        'quantity'         => 1,
        'unit_price'       => 15000,
        'is_complimentary' => 0,
        'service_item_id'  => $service->id,
    ])->assertRedirect();

    $line = $booking->folioItems()->where('type', 'spa')->latest('id')->first();

    expect($line)->not->toBeNull()
        ->and($line->is_complimentary)->toBeTrue()
        ->and($line->total_price)->toBe(0)
        ->and($line->notes)->toContain('ONG Santé Plus');
});

test('une prestation hors convention reste facturée normalement', function () {
    [$user, $room] = partnerTestSetup();

    $covered = ServiceItem::create([
        'category' => ServiceItem::CATEGORY_SPA, 'name' => 'Massage relaxant',
        'price' => 1500000, 'is_active' => true,
    ]);
    $notCovered = ServiceItem::create([
        'category' => ServiceItem::CATEGORY_ACTIVITY, 'name' => 'Excursion',
        'price' => 2000000, 'is_active' => true,
    ]);

    $organization = PartnerOrganization::create([
        'name' => 'ONG Santé Plus', 'type' => 'ngo', 'is_active' => true,
        'room_discount_type' => PartnerOrganization::DISCOUNT_NONE,
        'free_service_item_ids' => [$covered->id],
    ]);

    $customer = Customer::factory()->create(['partner_organization_id' => $organization->id]);
    $this->post(route('bookings.store'), bookingPayload($customer, $room))->assertRedirect();
    $booking = Booking::latest()->first();
    $booking->update(['status' => \App\Enums\BookingStatus::CHECKED_IN, 'actual_check_in' => now()]);

    $this->post(route('bookings.folio.add', $booking), [
        'type' => 'activity', 'description' => 'Excursion', 'quantity' => 1,
        'unit_price' => 20000, 'is_complimentary' => 0, 'service_item_id' => $notCovered->id,
    ])->assertRedirect();

    $line = $booking->folioItems()->where('type', 'activity')->latest('id')->first();

    expect($line->is_complimentary)->toBeFalse()
        ->and($line->total_price)->toBe(2000000);
});
