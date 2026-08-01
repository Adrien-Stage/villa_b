<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Vérifie que les trois fichiers de garnissage passent réellement par les
 * importeurs de l'application, dans l'ordre imposé (types puis chambres).
 * Ignoré si les fichiers ne sont pas présents sur la machine.
 */
function seedCsv(string $name): ?UploadedFile
{
    $path = 'C:/Users/user/Downloads/' . $name;

    return is_file($path) ? new UploadedFile($path, $name, 'text/csv', null, true) : null;
}

test('les trois CSV de garnissage importent 20 lignes chacun', function () {
    $types    = seedCsv('types_chambre_20260711_084818.csv');
    $rooms    = seedCsv('chambres_20260711_084805.csv');
    $clients  = seedCsv('clients_20260724_073123.csv');

    if (!$types || !$rooms || !$clients) {
        $this->markTestSkipped('Fichiers de garnissage absents du dossier Downloads.');
    }

    $this->seed(\Database\Seeders\TenantSeeder::class);
    // Les routes d'import exigent role:manager,reception — pas admin.
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
    $this->actingAs($manager);

    // 1. Types d'abord : les chambres y font référence par code.
    $this->post(route('rooms.types.import'), ['csv_file' => $types])
        ->assertRedirect();
    expect(RoomType::count())->toBe(20);

    // 2. Chambres : chaque code_type doit résoudre, sinon la ligne est rejetée.
    $this->post(route('rooms.import'), ['csv_file' => $rooms])
        ->assertRedirect();
    expect(Room::count())->toBe(20);
    expect(Room::whereNull('room_type_id')->count())->toBe(0);

    // 3. Clients : le pays doit survivre à Countries::normalize, sinon la
    // carte des marchés émetteurs reste vide.
    $this->post(route('customers.import'), ['csv_file' => $clients])
        ->assertRedirect();
    expect(Customer::count())->toBe(20)
        ->and(Customer::whereNotNull('country')->count())->toBe(17)
        ->and(Customer::where('is_vip', true)->count())->toBe(4)
        ->and(Customer::where('is_blacklisted', true)->count())->toBe(1);

    // Les prix sont saisis en FCFA et stockés en centimes.
    expect(RoomType::where('code', 'STD')->value('base_price'))->toBe(4500000);

    // Les équipements sont éclatés sur « | ».
    expect(RoomType::where('code', 'VILL')->value('amenities'))
        ->toContain('Piscine privée');
});
