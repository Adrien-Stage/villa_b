<?php

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use App\Models\HousekeepingTeam;
use App\Models\Room;
use App\Models\User;
use App\Services\CheckOutService;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Trois échelons au housekeeping — responsable des étages, gouvernante, équipe
 * de nettoyage — et le suivi de chaque geste : qui a nettoyé, contrôlé, remis en
 * vente quelle chambre, et à quelle heure. Les chambres occupées ont leur
 * recouche, que le client garde.
 */

beforeEach(function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    RoleCatalog::sync();
    $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['housekeeping']);

    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00'));
    $this->room = Room::where('number', '101')->first();
});

function agentHk(string $role, string $nom): User
{
    return User::factory()->create(['role' => $role, 'name' => $nom, 'is_active' => true]);
}

function equipeHk(User $chef, array $membres): HousekeepingTeam
{
    $equipe = HousekeepingTeam::create(['name' => 'Équipe Étage 1', 'leader_id' => $chef->id, 'is_active' => true]);
    $equipe->members()->sync(collect($membres)->push($chef)->pluck('id')->unique()->all());

    return $equipe;
}

test('la gouvernante est un échelon du housekeeping entre le responsable et l’équipe', function () {
    expect(RoleCatalog::find('housekeeping_leader')['name'])->toBe('Responsable des étages')
        ->and(RoleCatalog::developper(['housekeeping_leader']))->toContain('housekeeping_supervisor', 'housekeeping_staff')
        ->and(RoleCatalog::find('housekeeping_supervisor')['name'])->toBe('Gouvernante');

    $gouvernante = PermissionCatalog::forRole('housekeeping_supervisor');
    expect($gouvernante)->toContain('housekeeping.assignments.creer', 'housekeeping.inspect', 'housekeeping.journal.voir')
        ->not->toContain('housekeeping.teams.creer');

    expect(PermissionCatalog::forRole('housekeeping_staff'))->not->toContain('housekeeping.journal.voir', 'housekeeping.assignments.creer');
});

test('la gouvernante pilote le service sans composer les équipes', function () {
    $this->actingAs(agentHk('housekeeping_supervisor', 'Awa Gouvernante'))
        ->get(route('housekeeping.index'))->assertOk()
        ->assertSee('Chambres en cours de traitement')
        ->assertSee('Recouches')
        ->assertDontSee('Créer une équipe');

    $this->actingAs(agentHk('housekeeping_leader', 'Paul Responsable'))
        ->get(route('housekeeping.index'))->assertOk()
        ->assertSee('Créer une équipe');
});

test('chaque étape du ménage de départ garde son auteur et son heure', function () {
    $valet = agentHk('housekeeping_staff', 'Marie Valet');
    $gouvernante = agentHk('housekeeping_supervisor', 'Awa Gouvernante');
    $equipe = equipeHk($valet, []);
    $this->room->update(['status' => RoomStatus::DIRTY]);

    $this->actingAs($gouvernante)->post(route('housekeeping.assignments.store'), [
        'housekeeping_team_id' => $equipe->id, 'room_ids' => [$this->room->id],
    ])->assertSessionHasNoErrors();

    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:10'));
    $this->actingAs($valet)->post(route('housekeeping.clean', $this->room))->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:42'));
    $this->actingAs($valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:05'));
    $this->actingAs($gouvernante)->post(route('housekeeping.inspect', $this->room))->assertSessionHasNoErrors();
    $this->actingAs($gouvernante)->post(route('housekeeping.available', $this->room))->assertSessionHasNoErrors();

    $passage = HousekeepingAssignment::sole();
    expect($this->room->fresh()->status)->toBe(RoomStatus::AVAILABLE)
        ->and($passage->started_by)->toBe($valet->id)
        ->and($passage->started_at->format('H:i'))->toBe('09:10')
        ->and($passage->completed_by)->toBe($valet->id)
        ->and($passage->dureeEnMinutes())->toBe(32)
        ->and($passage->inspected_by)->toBe($gouvernante->id)
        ->and($passage->released_by)->toBe($gouvernante->id)
        ->and(HousekeepingLog::orderBy('id')->pluck('action')->all())->toBe([
            HousekeepingLog::ASSIGNED, HousekeepingLog::STARTED, HousekeepingLog::CLEANED,
            HousekeepingLog::INSPECTED, HousekeepingLog::RELEASED,
        ]);

    $this->actingAs(agentHk('housekeeping_leader', 'Paul Responsable'))
        ->get(route('housekeeping.journal.index', ['du' => '2026-10-15']))->assertOk()
        ->assertSee('Suivi des chambres')
        ->assertSee('Marie Valet')
        ->assertSee('09:42')
        ->assertSee('32 min')
        ->assertSee('Awa Gouvernante');

    // L'équipe fait le travail ; le suivi est celui de l'encadrement.
    $this->actingAs($valet)->get(route('housekeeping.journal.index'))->assertRedirect();
});

test('une chambre nettoyée alerte la gouvernante et la réception', function () {
    $valet = agentHk('housekeeping_staff', 'Marie Valet');
    $gouvernante = agentHk('housekeeping_supervisor', 'Awa Gouvernante');
    $reception = agentHk('reception', 'Joël Réception');
    $equipe = equipeHk($valet, []);
    $this->room->update(['status' => RoomStatus::CLEANING]);
    HousekeepingAssignment::create(['housekeeping_team_id' => $equipe->id, 'room_id' => $this->room->id,
        'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);

    $this->actingAs($valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasNoErrors();

    expect($gouvernante->notifications()->first()?->data['title'])->toBe('Chambre à contrôler')
        ->and($reception->notifications()->first()?->data['message'])->toContain('Chambre 101 nettoyée par Marie Valet');
});

test("la recouche d'une chambre occupée la laisse au client et garde son auteur", function () {
    $valet = agentHk('housekeeping_staff', 'Marie Valet');
    $gouvernante = agentHk('housekeeping_supervisor', 'Awa Gouvernante');
    $equipe = equipeHk($valet, []);
    $this->room->update(['status' => RoomStatus::OCCUPIED]);

    $this->actingAs($gouvernante)->post(route('housekeeping.assignments.store'), [
        'housekeeping_team_id' => $equipe->id, 'room_ids' => [$this->room->id],
    ])->assertSessionHasNoErrors();

    $recouche = HousekeepingAssignment::sole();
    expect($recouche->kind)->toBe(HousekeepingAssignment::KIND_RECOUCHE);

    $this->actingAs($valet)->get(route('housekeeping.index'))->assertSee('Recouches — chambres occupées')->assertSee('Commencer la recouche');
    $this->actingAs($valet)->post(route('housekeeping.clean', $this->room))->assertSessionHasNoErrors();
    expect($this->room->fresh()->status)->toBe(RoomStatus::OCCUPIED)
        ->and($recouche->fresh()->status)->toBe('in_progress');

    // Une recouche ne passe pas par le contrôle : elle se termine.
    $this->actingAs($valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasErrors('status');
    $this->actingAs($valet)->post(route('housekeeping.stayover', $this->room))->assertSessionHasNoErrors();

    expect($this->room->fresh()->status)->toBe(RoomStatus::OCCUPIED)
        ->and($recouche->fresh()->status)->toBe('completed')
        ->and($recouche->fresh()->completed_by)->toBe($valet->id)
        ->and(HousekeepingLog::pluck('action')->all())->toContain(HousekeepingLog::STAYOVER_STARTED, HousekeepingLog::STAYOVER_DONE);
});

test("le départ du client annule la recouche prévue", function () {
    $reception = agentHk('reception', 'Joël Réception');
    $this->actingAs($reception);
    $valet = agentHk('housekeeping_staff', 'Marie Valet');
    $equipe = equipeHk($valet, []);
    $this->room->update(['status' => RoomStatus::OCCUPIED]);

    $booking = Booking::create([
        'customer_id' => Customer::factory()->create()->id, 'room_id' => $this->room->id, 'booking_number' => 'VB-2026-000901',
        'status' => BookingStatus::CHECKED_IN, 'check_in' => now()->subDay()->toDateString(), 'check_out' => now()->toDateString(),
        'actual_check_in' => now()->subDay(), 'adults_count' => 1, 'total_nights' => 1, 'price_per_night' => 4500000,
        'total_room_amount' => 4500000, 'total_amount' => 4500000, 'paid_amount' => 4500000, 'balance_due' => 0, 'source' => 'direct',
    ]);
    HousekeepingAssignment::create(['housekeeping_team_id' => $equipe->id, 'room_id' => $this->room->id,
        'kind' => HousekeepingAssignment::KIND_RECOUCHE, 'status' => 'pending', 'assigned_at' => now()]);

    app(CheckOutService::class)->process($booking->fresh());

    expect($this->room->fresh()->status)->toBe(RoomStatus::DIRTY)
        ->and(HousekeepingAssignment::sole()->status)->toBe('cancelled')
        ->and($this->room->fresh()->activeHousekeepingAssignment)->toBeNull();
});
