<?php

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomStatusHistory;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Services\RoomAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function delayTenant(array $hebergement = []): Tenant
{
    if (!Tenant::query()->exists()) {
        test()->seed(\Database\Seeders\TenantSeeder::class);
    }

    $tenant = Tenant::firstOrFail();
    $tenant->settings = array_merge($tenant->settings ?? [], ['hebergement' => $hebergement]);
    $tenant->save();

    return $tenant;
}

function delayRoom(RoomType $type, RoomStatus $status = RoomStatus::DIRTY, ?string $freedAt = null): Room
{
    $room = Room::create([
        'room_type_id' => $type->id,
        'number'       => (string) random_int(1000, 9999),
        'status'       => $status,
        'is_active'    => true,
    ]);

    if ($freedAt) {
        RoomStatusHistory::create([
            'room_id'     => $room->id,
            'from_status' => RoomStatus::OCCUPIED->value,
            'to_status'   => RoomStatus::DIRTY->value,
            'reason'      => 'Départ client',
            'changed_at'  => $freedAt,
        ]);
    }

    return $room->fresh();
}

function suiteType(string $name = 'Suite Présidentielle'): RoomType
{
    return RoomType::create([
        'code' => strtoupper(substr(md5($name), 0, 6)),
        'name' => $name,
        'base_capacity' => 2, 'max_capacity' => 4,
        'base_price' => 35000000, 'is_active' => true,
    ]);
}

// ── Réglage du délai ──────────────────────────────────────────────────────────

test('sans réglage, le délai par défaut de 2 h s\'applique', function () {
    delayTenant();

    expect(app(RoomAvailabilityService::class)->defaultDelayMinutes())
        ->toBe(RoomAvailabilityService::DEFAULT_DELAY_MINUTES);
});

test('le délai global paramétré remplace la valeur par défaut', function () {
    delayTenant(['cleaning_delay_minutes' => 45]);

    expect(app(RoomAvailabilityService::class)->defaultDelayMinutes())->toBe(45);
});

test('le délai par type de chambre prime sur le délai global', function () {
    $suite    = suiteType();
    $standard = suiteType('Standard');

    delayTenant([
        'cleaning_delay_minutes'  => 30,
        'cleaning_delay_by_type'  => [$suite->id => 120],
    ]);

    $service = app(RoomAvailabilityService::class);

    expect($service->delayMinutesFor($suite))->toBe(120)
        ->and($service->delayMinutesFor($standard))->toBe(30);
});

test('une surcharge vide retombe sur le délai global', function () {
    $suite = suiteType();
    delayTenant([
        'cleaning_delay_minutes' => 90,
        'cleaning_delay_by_type' => [$suite->id => ''],   // champ laissé vide
    ]);

    expect(app(RoomAvailabilityService::class)->delayMinutesFor($suite))->toBe(90);
});

// ── Calcul de l'heure de disponibilité ────────────────────────────────────────

test('la suite libérée à 12 h est annoncée disponible à 14 h', function () {
    $suite = suiteType();
    delayTenant(['cleaning_delay_by_type' => [$suite->id => 120]]);

    $this->travelTo(now()->setTime(12, 30));
    $room = delayRoom($suite, RoomStatus::DIRTY, today()->setTime(12, 0)->toDateTimeString());

    $service = app(RoomAvailabilityService::class);

    expect($service->availableAt($room)->format('H:i'))->toBe('14:00')
        ->and($service->minutesRemaining($room))->toBe(90)
        ->and($service->label($room))->toBe('Disponible dans 1 h 30');
});

test('le délai dépassé n\'annonce plus d\'attente', function () {
    $suite = suiteType();
    delayTenant(['cleaning_delay_by_type' => [$suite->id => 60]]);

    $this->travelTo(now()->setTime(16, 0));
    $room = delayRoom($suite, RoomStatus::DIRTY, today()->setTime(12, 0)->toDateTimeString());

    $service = app(RoomAvailabilityService::class);

    expect($service->minutesRemaining($room))->toBe(0)
        ->and($service->label($room))->toBe("Disponible d'ici peu");
});

test('une chambre déjà disponible ne porte aucune échéance', function () {
    delayTenant();
    $room = delayRoom(suiteType(), RoomStatus::AVAILABLE);

    $payload = app(RoomAvailabilityService::class)->payload($room);

    expect($payload['ready_now'])->toBeTrue()
        ->and($payload['state'])->toBe('available')
        ->and($payload['label'])->toBe('Disponible')
        ->and($payload['available_at'])->toBeNull();
});

test('une chambre en maintenance est visible mais pas vendable', function () {
    delayTenant();
    $room = delayRoom(suiteType(), RoomStatus::MAINTENANCE);

    $service = app(RoomAvailabilityService::class);

    // Indisponibilité sans échéance connue : elle figure au catalogue mais
    // aucune date ne peut être retenue.
    expect($service->isSellable($room))->toBeFalse()
        ->and($service->availableAt($room))->toBeNull()
        ->and($service->conflictReason($room, today()->addMonth(), today()->addMonth()->addDays(2)))
            ->toContain('maintenance');

    $payload = $service->payload($room);
    expect($payload['state'])->toBe('unavailable')
        ->and($payload['sellable'])->toBeFalse();
});

// ── Exposition au site vitrine ────────────────────────────────────────────────

test('le site voit la chambre en remise en état avec son échéance', function () {
    $suite = suiteType();
    delayTenant(['cleaning_delay_by_type' => [$suite->id => 120]]);

    $this->travelTo(now()->setTime(12, 30));
    $room = delayRoom($suite, RoomStatus::DIRTY, today()->setTime(12, 0)->toDateTimeString());

    $response = $this->getJson(route('api.rooms.index'))->assertOk();

    $payload = collect($response->json('data'))->firstWhere('id', $room->id);

    expect($payload)->not->toBeNull()
        ->and($payload['availability']['ready_now'])->toBeFalse()
        ->and($payload['availability']['label'])->toBe('Disponible dans 1 h 30')
        ->and($payload['availability']['minutes_remaining'])->toBe(90);
});

test('le site affiche les chambres occupées mais pas celles en maintenance', function () {
    delayTenant();
    $suite = suiteType();

    $libre       = delayRoom($suite, RoomStatus::AVAILABLE);
    $occupee     = delayRoom($suite, RoomStatus::OCCUPIED);
    $maintenance = delayRoom($suite, RoomStatus::MAINTENANCE);
    $horsService = delayRoom($suite, RoomStatus::OUT_OF_ORDER);

    $data = collect($this->getJson(route('api.rooms.index'))->assertOk()->json('data'));

    // Occupée : affichée, car elle se vend pour les dates où elle sera libre.
    // Maintenance et hors service : masquées, aucune date n'y est retenable.
    expect($data->pluck('id'))->toContain($libre->id, $occupee->id)
        ->and($data->pluck('id'))->not->toContain($maintenance->id)
        ->and($data->pluck('id'))->not->toContain($horsService->id);

    $this->getJson(route('api.rooms.show', $occupee))->assertOk();
    $this->getJson(route('api.rooms.show', $maintenance))->assertNotFound();
});

test('une chambre en remise en état reste réservable depuis le site', function () {
    $suite = suiteType();
    delayTenant(['cleaning_delay_by_type' => [$suite->id => 120]]);
    $room = delayRoom($suite, RoomStatus::DIRTY, now()->subMinutes(10)->toDateTimeString());

    // Sans cette acceptation, le site annoncerait une chambre qu'il refuserait
    // ensuite de réserver — l'annonce serait mensongère.
    $this->postJson(route('api.bookings.store'), [
        'room_id'    => $room->id,
        'check_in'   => today()->addDay()->toDateString(),
        'check_out'  => today()->addDays(3)->toDateString(),
        'adults'     => 2,
        'first_name' => 'Aminatou',
        'last_name'  => 'Njoya',
        'email'      => 'aminatou@example.com',
        'phone'      => '+237699112233',
    ])->assertCreated();
});

// ── Réglage depuis l'onglet Paramètres ────────────────────────────────────────

test('le manager règle les délais depuis l\'onglet Hébergement', function () {
    delayTenant();
    $suite = suiteType();

    $manager = \App\Models\User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $this->actingAs($manager)
        ->post(route('settings.update', ['tab' => 'hebergement']), [
            'settings' => [
                'cleaning_delay_minutes' => 45,
                'cleaning_delay_by_type' => [$suite->id => 120],
            ],
        ])
        ->assertRedirect();

    // Service reconstruit : les réglages sont bien relus depuis la base.
    $service = app()->make(RoomAvailabilityService::class);

    expect($service->defaultDelayMinutes())->toBe(45)
        ->and($service->delayMinutesFor($suite))->toBe(120);
});

test('le formulaire affiche les délais enregistrés', function () {
    $suite = suiteType('Suite Duplex');
    delayTenant([
        'cleaning_delay_minutes' => 75,
        'cleaning_delay_by_type' => [$suite->id => 150],
    ]);

    $manager = \App\Models\User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $this->actingAs($manager)
        ->get(route('settings.index', ['tab' => 'hebergement']))
        ->assertOk()
        ->assertSee('Remise en vente après départ')
        ->assertSee('value="75"', false)
        ->assertSee('value="150"', false)
        ->assertSee('Suite Duplex');
});
