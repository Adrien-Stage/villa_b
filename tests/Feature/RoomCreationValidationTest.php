<?php

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating a room with a duplicate number returns validation error instead of 500', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($user);

    $existingRoom = Room::first();
    $roomType = RoomType::first();

    $response = $this->post(route('rooms.store'), [
        'room_type_id' => $roomType->id,
        'number'       => $existingRoom->number, // Duplicate number
        'floor'        => '1',
        'view_type'    => 'pool',
    ]);

    $response->assertSessionHasErrors(['number']);
    $response->assertSessionMissing('success');
    expect(session('errors')->first('number'))->toContain("déjà utilisé");
});

test('updating a room with an existing number from another room returns validation error', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($user);

    $rooms = Room::take(2)->get();
    $room1 = $rooms[0];
    $room2 = $rooms[1];

    $response = $this->put(route('rooms.update', $room2), [
        'room_type_id' => $room2->room_type_id,
        'number'       => $room1->number, // duplicate of room 1
    ]);

    $response->assertSessionHasErrors(['number']);
});

test('updating a room keeping its own number succeeds', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($user);

    $room = Room::first();

    $response = $this->put(route('rooms.update', $room), [
        'room_type_id' => $room->room_type_id,
        'number'       => $room->number,
        'notes'        => 'Note mise à jour',
    ]);

    $response->assertRedirect(route('rooms.index', ['tab' => 'rooms']));
    $response->assertSessionHas('success');
});

test('creating a room with a unique number succeeds', function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager']);
    $this->actingAs($user);

    $roomType = RoomType::first();

    $response = $this->post(route('rooms.store'), [
        'room_type_id' => $roomType->id,
        'number'       => '999X',
        'floor'        => '9',
    ]);

    $response->assertRedirect(route('rooms.index', ['tab' => 'rooms']));
    $response->assertSessionHas('success');
    expect(Room::where('number', '999X')->exists())->toBeTrue();
});
