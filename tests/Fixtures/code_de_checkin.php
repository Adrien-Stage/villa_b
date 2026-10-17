<?php

/*
 * Aides communes aux tests du code de check-in (CheckinCodeRecipientTest,
 * CheckinCodeSendTest). Chargées par les deux fichiers : en exécution
 * parallèle, chacun peut tourner sans l'autre.
 */

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Room;
use App\Models\User;

function receptionAvecCaisse(): User
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    CashRegisterSession::create([
        'user_id'        => $user->id,
        'module'         => 'reception',
        'opening_amount' => 5000000,
        'opened_at'      => now(),
    ]);

    test()->actingAs($user);

    return $user;
}

function clientFinal(): Customer
{
    return Customer::create([
        'first_name' => 'Aminatou', 'last_name' => 'Njoya',
        'email' => 'aminatou@exemple.cm', 'phone' => '+237690000001',
    ]);
}

function mandataire(): Customer
{
    return Customer::create([
        'first_name' => 'Serge', 'last_name' => 'Kamdem',
        'email' => 'serge@agence.cm', 'phone' => '+237690000002',
    ]);
}

/** Soumet la réservation finale. */
function soumetReservation(Customer $client, ?Customer $booker, ?string $destinataire)
{
    return test()->post(route('bookings.store'), array_filter([
        'step'           => '4',
        'room_id'        => Room::where('number', '101')->value('id'),
        'customer_id'    => $client->id,
        'booker_id'      => $booker?->id,
        'check_in'       => now()->addDay()->format('Y-m-d'),
        'check_out'      => now()->addDays(2)->format('Y-m-d'),
        'adults_count'   => 1,
        'children_count' => 0,
        'source'         => 'direct',
        'custom_price'   => '45000',
        'payment_amount' => '45000',
        'payment_method' => 'cash',
        'recipient_type' => $destinataire,
    ], fn ($v) => $v !== null));
}
