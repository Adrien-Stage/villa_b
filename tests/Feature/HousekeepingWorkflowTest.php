<?php

use App\Enums\RoomStatus;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingTeam;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Active le module housekeeping (lu depuis un cache statique). */
function enableHousekeeping(): void
{
    $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['housekeeping']);
}

function hkSetup(): Room
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    enableHousekeeping();

    return Room::first();
}

/** Crée une équipe avec un chef et des membres, et lui affecte une chambre. */
function hkTeamWithAssignment(Room $room, User $leader, array $members, string $assignmentStatus): HousekeepingTeam
{
    $team = HousekeepingTeam::create(['name' => 'Équipe A', 'leader_id' => $leader->id, 'is_active' => true]);
    $team->members()->sync(collect($members)->push($leader)->pluck('id')->unique()->all());

    HousekeepingAssignment::create([
        'housekeeping_team_id' => $team->id,
        'room_id'              => $room->id,
        'assigned_by'          => $leader->id,
        'status'               => $assignmentStatus,
        'assigned_at'          => now(),
        'completed_at'         => $assignmentStatus === 'completed' ? now() : null,
    ]);

    return $team;
}

test('le chef de service peut rendre disponible une chambre contrôlée', function () {
    $room = hkSetup();
    $room->update(['status' => RoomStatus::INSPECTED]);
    $chief = User::factory()->create(['role' => 'housekeeping_leader']);

    $this->actingAs($chief)->post(route('housekeeping.available', $room))->assertRedirect();

    expect($room->fresh()->status)->toBe(RoomStatus::AVAILABLE);
});

test('le chef d’équipe peut contrôler puis libérer la chambre de son équipe', function () {
    $room   = hkSetup();
    $leader = User::factory()->create(['role' => 'housekeeping_staff']);
    hkTeamWithAssignment($room, $leader, [], 'completed');
    $room->update(['status' => RoomStatus::CLEAN]);

    // Contrôle : Nettoyée -> Contrôlée
    $this->actingAs($leader)->post(route('housekeeping.inspect', $room))->assertRedirect();
    expect($room->fresh()->status)->toBe(RoomStatus::INSPECTED);

    // Libération : Contrôlée -> Disponible
    $this->actingAs($leader)->post(route('housekeeping.available', $room))->assertRedirect();
    expect($room->fresh()->status)->toBe(RoomStatus::AVAILABLE);
});

test('un membre simple peut démarrer le nettoyage mais pas contrôler', function () {
    $room   = hkSetup();
    $leader = User::factory()->create(['role' => 'housekeeping_staff']);
    $member = User::factory()->create(['role' => 'housekeeping_staff']);
    hkTeamWithAssignment($room, $leader, [$member], 'pending');
    $room->update(['status' => RoomStatus::DIRTY]);

    // Démarrer le nettoyage : autorisé (membre de l'équipe)
    $this->actingAs($member)->post(route('housekeeping.clean', $room))->assertRedirect();
    expect($room->fresh()->status)->toBe(RoomStatus::CLEANING);

    // Contrôler : interdit (pas chef d'équipe)
    $room->update(['status' => RoomStatus::CLEAN]);
    $this->actingAs($member)->post(route('housekeeping.inspect', $room))->assertForbidden();
    expect($room->fresh()->status)->toBe(RoomStatus::CLEAN);
});

test('un membre d’une autre équipe ne peut pas agir sur la chambre', function () {
    $room     = hkSetup();
    $leader   = User::factory()->create(['role' => 'housekeeping_staff']);
    hkTeamWithAssignment($room, $leader, [], 'pending');
    $room->update(['status' => RoomStatus::DIRTY]);

    $outsider = User::factory()->create(['role' => 'housekeeping_staff']);
    $this->actingAs($outsider)->post(route('housekeeping.clean', $room))->assertForbidden();
    expect($room->fresh()->status)->toBe(RoomStatus::DIRTY);
});

test('le rôle housekeeping n’accède plus à la rubrique Chambres', function () {
    hkSetup();
    $agent = User::factory()->create(['role' => 'housekeeping']);

    // La rubrique Chambres est désormais réservée à manager/réception.
    $this->actingAs($agent)->get(route('rooms.index'))->assertRedirect();
});

test('l’agent voit l’espace membre et le chef voit le pilotage', function () {
    hkSetup();

    $agent = User::factory()->create(['role' => 'housekeeping_staff']);
    $this->actingAs($agent)->get(route('housekeeping.index'))
        ->assertOk()
        ->assertSee('Mon espace housekeeping')
        ->assertDontSee('Créer une équipe');

    $chief = User::factory()->create(['role' => 'housekeeping_leader']);
    $this->actingAs($chief)->get(route('housekeeping.index'))
        ->assertOk()
        ->assertSee('Chambres en cours de traitement')
        ->assertSee('Créer une équipe');
});

test('le libellé du statut « à nettoyer » remplace « Sale »', function () {
    expect(RoomStatus::DIRTY->label())->toBe('À nettoyer');
});
