<?php

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('le tableau de bord du manager affiche l\'ensemble des widgets exécutifs', function () {
    $manager = User::factory()->create([
        'name' => 'Clyde',
        'role' => 'manager',
    ]);

    // Données de test
    $roomType = RoomType::create([
        'name' => 'Bungalow Familial',
        'code' => 'BF',
        'base_capacity' => 2,
        'max_capacity' => 4,
        'base_price' => 5000000,
        'is_active' => true,
    ]);

    $room = Room::create([
        'room_type_id' => $roomType->id,
        'number' => '404',
        'floor' => 1,
        'status' => RoomStatus::AVAILABLE,
    ]);

    $customer = Customer::create([
        'first_name' => 'Viviane',
        'last_name' => 'Atangana',
        'email' => 'viviane@example.com',
        'phone' => '+237699000000',
    ]);

    Booking::create([
        'booking_number' => 'BKG-2026-0001',
        'customer_id' => $customer->id,
        'room_id' => $room->id,
        'status' => BookingStatus::CONFIRMED,
        'check_in' => Carbon::today(),
        'check_out' => Carbon::tomorrow(),
        'price_per_night' => 5000000,
        'total_room_amount' => 5000000,
        'total_nights' => 1,
        'adults_count' => 2,
        'children_count' => 0,
        'reference' => 'PF2345',
        'total_amount' => 5000000,
        'paid_amount' => 0,
        'balance_due' => 5000000,
    ]);

    StockItem::create([
        'name' => 'Article Test',
        'unit' => 'pièce',
        'current_stock' => 2,
        'min_stock' => 5,
        'average_cost' => 5603000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->get('/dashboard');

    $response->assertOk();

    // 1. En-tête
    $response->assertSee('Tableau de bord')
        ->assertSee('Yaoundé')
        ->assertSee('28°C')
        ->assertSee('Ensoleillé')
        ->assertSee('SESSION : CLYDE (MANAGER)', false);

    // 2. Actions rapides
    $response->assertSee('Mode POS Réception')
        ->assertSee('Nouvelle Réservation')
        ->assertSee('Nouveau Client')
        ->assertSee('Planning')
        ->assertSee('Gérer les commandes (Restaurant)')
        ->assertSee('Accéder au Housekeeping');

    // 3. 6 Cartes Chiffres Clés
    $response->assertSee('Arrivées')
        ->assertSee('Départs')
        ->assertSee('En séjour')
        ->assertSee('Occupation')
        ->assertSee('CA Resto')
        ->assertSee('Solde Hôtel');

    // 4. Cartes du Milieu
    $response->assertSee('Occupation en temps réel')
        ->assertSee('Arrivées & Départs du jour', false)
        ->assertSee('Disponibilité des chambres')
        ->assertSee('Viviane Atangana')
        ->assertSee('Chambre 404 (Bungalow Familial)')
        ->assertSee('Faire le Check-in');

    // 5. Section du bas
    $response->assertSee('Dernières commandes')
        ->assertSee('Statistiques du jour')
        ->assertSee('Articles vendus')
        ->assertSee('Articles sous seuil')
        ->assertSee('Valeur du stock')
        ->assertSee('Évolution du CA (Hôtel + Restaurant)', false);
});
