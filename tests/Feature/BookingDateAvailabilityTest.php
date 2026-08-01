<?php

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * La disponibilité dépend désormais des dates demandées, plus du statut
 * courant : une chambre occupée cette semaine se vend pour le mois prochain.
 * Ces tests vérifient que la règle est bien la même côté site vitrine et côté
 * réception — c'est tout l'intérêt de l'avoir centralisée.
 */

function dateTenant(array $hebergement = [], array $reception = []): Tenant
{
    if (!Tenant::query()->exists()) {
        test()->seed(\Database\Seeders\TenantSeeder::class);
    }

    $tenant = Tenant::firstOrFail();
    $tenant->settings = array_merge($tenant->settings ?? [], [
        'hebergement' => $hebergement,
        'reception'   => $reception,
    ]);
    $tenant->save();

    return $tenant;
}

function dateRoom(RoomStatus $status = RoomStatus::AVAILABLE): Room
{
    $type = RoomType::create([
        'code' => strtoupper(substr(md5(uniqid('', true)), 0, 6)),
        'name' => 'Suite Présidentielle',
        'base_capacity' => 2, 'max_capacity' => 4,
        'base_price' => 35000000, 'is_active' => true,
    ]);

    return Room::create([
        'room_type_id' => $type->id,
        'number'       => (string) random_int(1000, 9999),
        'status'       => $status,
        'is_active'    => true,
    ]);
}

function occupy(Room $room, string $checkIn, string $checkOut): Booking
{
    $customer = Customer::create(['first_name' => 'Client', 'last_name' => 'Test']);
    $nights = \Illuminate\Support\Carbon::parse($checkIn)
        ->diffInDays(\Illuminate\Support\Carbon::parse($checkOut));
    $rate = 35000000;   // centimes FCFA

    return Booking::create([
        'tenant_id'         => Tenant::firstOrFail()->id,
        'room_id'           => $room->id,
        'customer_id'       => $customer->id,
        'booking_number'    => 'VB-' . random_int(10000, 99999),
        'status'            => BookingStatus::CONFIRMED,
        'check_in'          => $checkIn,
        'check_out'         => $checkOut,
        'total_nights'      => $nights,
        'price_per_night'   => $rate,
        'total_room_amount' => $nights * $rate,
        'total_amount'      => $nights * $rate,
        'adults_count'      => 2,
    ]);
}

// ── Refus sur période occupée ─────────────────────────────────────────────────

test('la période qui recoupe un séjour existant est refusée', function () {
    dateTenant();
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, '2026-09-10', '2026-09-15');

    $service = app(RoomAvailabilityService::class);

    expect($service->conflictReason($room, '2026-09-12', '2026-09-18'))->toContain('déjà occupée')
        ->and($service->conflictReason($room, '2026-09-08', '2026-09-12'))->toContain('déjà occupée')
        ->and($service->conflictReason($room, '2026-09-11', '2026-09-13'))->toContain('déjà occupée');
});

test('une chambre occupée aujourd\'hui reste réservable pour plus tard', function () {
    dateTenant();
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, today()->toDateString(), today()->addDays(3)->toDateString());

    // C'est le point du changement : le statut « occupée » ne ferme plus la
    // vente, seules les dates prises la ferment.
    expect(app(RoomAvailabilityService::class)
        ->conflictReason($room, today()->addMonth(), today()->addMonth()->addDays(2)))
        ->toBeNull();
});

// ── Rotation après départ et ménage ───────────────────────────────────────────

test('l\'arrivée le lendemain du départ passe toujours', function () {
    dateTenant(['cleaning_delay_minutes' => 240]);   // ménage très long
    $room = dateRoom();
    occupy($room, '2026-09-10', '2026-09-15');

    expect(app(RoomAvailabilityService::class)->conflictReason($room, '2026-09-16', '2026-09-18'))
        ->toBeNull();
});

test('la rotation le jour même passe quand le ménage tient dans la journée', function () {
    // Départ 12 h + 2 h de ménage = prête à 14 h, arrivée à 14 h : ça tient.
    dateTenant(
        ['cleaning_delay_minutes' => 120],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $room = dateRoom();
    occupy($room, '2026-09-10', '2026-09-15');

    $service = app(RoomAvailabilityService::class);

    expect($service->canTurnOverSameDay($room->roomType))->toBeTrue()
        ->and($service->conflictReason($room, '2026-09-15', '2026-09-18'))->toBeNull();
});

test('la rotation le jour même est refusée quand le ménage déborde', function () {
    // Départ 12 h + 4 h de ménage = prête à 16 h, après l'arrivée de 14 h.
    dateTenant(
        ['cleaning_delay_minutes' => 240],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $room = dateRoom();
    occupy($room, '2026-09-10', '2026-09-15');

    $service = app(RoomAvailabilityService::class);

    expect($service->canTurnOverSameDay($room->roomType))->toBeFalse()
        ->and($service->conflictReason($room, '2026-09-15', '2026-09-18'))
            ->toContain('ne sera prête qu\'à 16h00');
});

test('le délai propre à la suite décide de sa rotation, pas le délai global', function () {
    $room = dateRoom();
    dateTenant(
        [
            'cleaning_delay_minutes' => 60,                          // global : rotation OK
            'cleaning_delay_by_type' => [$room->room_type_id => 240], // suite : rotation impossible
        ],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    occupy($room, '2026-09-10', '2026-09-15');

    $service = app(RoomAvailabilityService::class);

    expect($service->canTurnOverSameDay(null))->toBeTrue()
        ->and($service->canTurnOverSameDay($room->roomType))->toBeFalse()
        ->and($service->conflictReason($room, '2026-09-15', '2026-09-18'))->not->toBeNull();
});

test('un séjour annulé ne bloque plus les dates', function () {
    dateTenant();
    $room = dateRoom();
    occupy($room, '2026-09-10', '2026-09-15')->update(['status' => BookingStatus::CANCELLED]);

    expect(app(RoomAvailabilityService::class)->conflictReason($room, '2026-09-11', '2026-09-13'))
        ->toBeNull();
});

// ── Même règle sur les deux surfaces ──────────────────────────────────────────

test('le site refuse la période occupée et accepte celle d\'après', function () {
    dateTenant(
        ['cleaning_delay_minutes' => 120],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, '2026-09-10', '2026-09-15');

    $payload = fn (string $in, string $out) => [
        'room_id'    => $room->id,
        'check_in'   => $in,
        'check_out'  => $out,
        'adults'     => 2,
        'first_name' => 'Aminatou',
        'last_name'  => 'Njoya',
        'email'      => 'aminatou' . random_int(1, 9999) . '@example.com',
        'phone'      => '+237699112233',
    ];

    $this->postJson(route('api.bookings.store'), $payload('2026-09-12', '2026-09-14'))
        ->assertStatus(409);

    // Départ le 15 à 12 h, ménage 2 h, arrivée le 15 à 14 h : ça passe.
    $this->postJson(route('api.bookings.store'), $payload('2026-09-15', '2026-09-18'))
        ->assertCreated();
});

test('le site expose les périodes prises pour griser le calendrier', function () {
    dateTenant();
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, today()->addDays(5)->toDateString(), today()->addDays(9)->toDateString());

    $data = collect($this->getJson(route('api.rooms.index'))->assertOk()->json('data'));
    $availability = $data->firstWhere('id', $room->id)['availability'];

    expect($availability['busy_ranges'])->toHaveCount(1)
        ->and($availability['busy_ranges'][0]['from'])->toBe(today()->addDays(5)->toDateString())
        ->and($availability['busy_ranges'][0]['to'])->toBe(today()->addDays(9)->toDateString())
        ->and($availability['check_in_time'])->toBe('14:00');
});

test('la réception applique la même règle que le site', function () {
    dateTenant(
        ['cleaning_delay_minutes' => 120],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, '2026-09-10', '2026-09-15');

    // Le wizard interne liste les chambres libres sur la période : la chambre
    // doit réapparaître dès le 15, comme sur le site.
    expect(Room::availableBetween('2026-09-12', '2026-09-14')->pluck('id'))->not->toContain($room->id)
        ->and(Room::availableBetween('2026-09-15', '2026-09-18')->pluck('id'))->toContain($room->id);
});

test('une chambre en maintenance n\'est réservable à aucune date', function () {
    dateTenant();
    $room = dateRoom(RoomStatus::MAINTENANCE);

    expect(app(RoomAvailabilityService::class)->conflictReason($room, '2026-12-01', '2026-12-05'))
        ->toContain('maintenance')
        ->and(Room::availableBetween('2026-12-01', '2026-12-05')->pluck('id'))->not->toContain($room->id);
});

test('la chambre occupée annonce sa date de libération en français', function () {
    dateTenant();
    $room = dateRoom(RoomStatus::OCCUPIED);
    occupy($room, today()->subDay()->toDateString(), '2026-09-15');

    $payload = app(RoomAvailabilityService::class)->payload($room);

    expect($payload['state'])->toBe('occupied')
        ->and($payload['label'])->toBe('Occupée jusqu\'au 15 septembre')
        ->and($payload['sellable'])->toBeTrue();
});

test('la chambre prête avant l\'heure d\'arrivée accepte une arrivée le jour même', function () {
    // Libérée à 12 h, 2 h de ménage : prête à 14 h, soit l'heure d'arrivée.
    // Ce qui compte est l'heure à laquelle le client se présente, pas
    // l'instant où il consulte le site.
    dateTenant(
        ['cleaning_delay_minutes' => 120],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $this->travelTo(today()->setTime(12, 20));

    $room = dateRoom(RoomStatus::DIRTY);
    \App\Models\RoomStatusHistory::create([
        'room_id'     => $room->id,
        'from_status' => RoomStatus::OCCUPIED->value,
        'to_status'   => RoomStatus::DIRTY->value,
        'changed_at'  => today()->setTime(12, 0),
    ]);

    expect(app(RoomAvailabilityService::class)
        ->conflictReason($room->fresh(), today(), today()->addDays(2)))
        ->toBeNull();
});

test('la chambre prête après l\'heure d\'arrivée refuse l\'arrivée du jour', function () {
    // Libérée à 13 h avec 2 h de ménage : prête à 15 h, après l'arrivée de 14 h.
    dateTenant(
        ['cleaning_delay_minutes' => 120],
        ['check_out_time' => '12:00', 'check_in_time' => '14:00']
    );
    $this->travelTo(today()->setTime(13, 10));

    $room = dateRoom(RoomStatus::DIRTY);
    \App\Models\RoomStatusHistory::create([
        'room_id'     => $room->id,
        'from_status' => RoomStatus::OCCUPIED->value,
        'to_status'   => RoomStatus::DIRTY->value,
        'changed_at'  => today()->setTime(13, 0),
    ]);

    expect(app(RoomAvailabilityService::class)
        ->conflictReason($room->fresh(), today(), today()->addDays(2)))
        ->toContain('15h00');
});
