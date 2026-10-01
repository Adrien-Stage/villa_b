<?php

/**
 * Configurer les chambres n'est pas les exploiter.
 *
 * Le réceptionniste consulte les chambres et change leur statut ; créer,
 * modifier ou supprimer une chambre ou un type revient au chef de réception,
 * à la direction et à l'administrateur. L'écran pose la même question que la
 * route : un bouton que la route refuserait n'est pas affiché.
 */

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
});

test('le réceptionniste consulte les chambres sans bouton de configuration', function () {
    $this->actingAs(User::factory()->create(['role' => 'reception']));

    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSee('Exporter CSV')
        ->assertDontSee('Nouvelle chambre')
        ->assertDontSee('Importer CSV');

    $this->get(route('rooms.index', ['tab' => 'types']))
        ->assertOk()
        ->assertDontSee('Nouveau type');
});

test('la route refuse au réceptionniste de créer ou supprimer une chambre', function () {
    $this->actingAs(User::factory()->create(['role' => 'reception']));

    $type = RoomType::first();
    $existante = Room::first();

    $this->post(route('rooms.store'), [
        'room_type_id' => $type->id, 'number' => '999', 'floor' => '9', 'view_type' => 'pool',
    ]);
    $this->delete(route('rooms.destroy', $existante));

    expect(Room::where('number', '999')->exists())->toBeFalse()
        ->and(Room::whereKey($existante->id)->exists())->toBeTrue();
});

test('le manager garde la configuration des chambres', function () {
    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $this->get(route('rooms.index'))->assertOk()->assertSee('Nouvelle chambre');
});

test("l'administrateur configure les chambres", function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $this->get(route('rooms.index'))->assertOk()->assertSee('Nouvelle chambre');

    $this->post(route('rooms.store'), [
        'room_type_id' => RoomType::first()->id, 'number' => '998', 'floor' => '9', 'view_type' => 'pool',
    ]);

    expect(Room::where('number', '998')->exists())->toBeTrue();
});
