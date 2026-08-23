<?php

use App\Mail\CheckinCodeMail;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Room;
use App\Models\User;
use App\Services\CheckinCodeNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Destinataire du code de check-in.
 *
 * Le code est un code d'accès : il ne part qu'à une personne. Quand un
 * mandataire réserve pour un tiers, la réception tranche à la dernière étape ;
 * en réservation directe, la question ne se pose pas et l'écran ne la pose pas.
 */

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

/** Charge l'écran de confirmation (étape 3). */
function ecranFinal(?Customer $booker = null): string
{
    return test()->post(route('bookings.store'), array_filter([
        'step'           => '3',
        'room_id'        => Room::where('number', '101')->value('id'),
        'customer_id'    => clientFinal()->id,
        'booker_id'      => $booker?->id,
        'check_in'       => now()->addDay()->format('Y-m-d'),
        'check_out'      => now()->addDays(2)->format('Y-m-d'),
        'adults_count'   => 1,
        'children_count' => 0,
        'source'         => 'direct',
    ]))->assertOk()->getContent();
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

// ── 1. Condition d'affichage ────────────────────────────────────────────────

test('en mode mandataire, l\'écran propose de choisir le destinataire', function () {
    receptionAvecCaisse();

    $html = ecranFinal(mandataire());

    expect($html)->toContain('name="recipient_type"')
        ->and($html)->toContain('value="booker"')
        ->and($html)->toContain('value="customer"')
        // Les coordonnées des deux interlocuteurs, pour choisir en connaissance de cause.
        ->and($html)->toContain('serge@agence.cm')
        ->and($html)->toContain('aminatou@exemple.cm');
});

test('en réservation directe, aucun choix n\'est proposé', function () {
    receptionAvecCaisse();

    $html = ecranFinal(null);

    // Le champ existe — il porte le routage — mais il est figé sur le client.
    expect($html)->toContain('name="recipient_type" value="customer"')
        ->and($html)->not->toContain('value="booker"');
});

// ── 2. Validation ───────────────────────────────────────────────────────────

test('avec un mandataire, le destinataire est obligatoire', function () {
    receptionAvecCaisse();

    soumetReservation(clientFinal(), mandataire(), null)
        ->assertSessionHasErrors('recipient_type');
});

test('sans mandataire, on ne peut pas router vers un mandataire', function () {
    receptionAvecCaisse();

    soumetReservation(clientFinal(), null, CheckinCodeNotifier::TO_BOOKER)
        ->assertSessionHasErrors('recipient_type');
});

test('sans mandataire, le destinataire reste facultatif', function () {
    receptionAvecCaisse();

    soumetReservation(clientFinal(), null, null)
        ->assertSessionHasNoErrors();
});

// ── 3. Routage effectif de l'envoi ──────────────────────────────────────────

test('le code part au mandataire quand il est désigné', function () {
    receptionAvecCaisse();
    Mail::fake();

    $client = clientFinal();
    $agent  = mandataire();

    soumetReservation($client, $agent, CheckinCodeNotifier::TO_BOOKER);

    Mail::assertSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('serge@agence.cm'));
    Mail::assertNotSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('aminatou@exemple.cm'));
});

test('le code part au client final quand il est désigné', function () {
    receptionAvecCaisse();
    Mail::fake();

    soumetReservation(clientFinal(), mandataire(), CheckinCodeNotifier::TO_CUSTOMER);

    Mail::assertSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('aminatou@exemple.cm'));
    Mail::assertNotSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('serge@agence.cm'));
});

test('une réservation directe route automatiquement vers le client', function () {
    receptionAvecCaisse();
    Mail::fake();

    soumetReservation(clientFinal(), null, null);

    Mail::assertSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('aminatou@exemple.cm'));
    Mail::assertSentCount(1);
});

test('le code ne part plus aux deux interlocuteurs à la fois', function () {
    receptionAvecCaisse();
    Mail::fake();

    soumetReservation(clientFinal(), mandataire(), CheckinCodeNotifier::TO_BOOKER);

    Mail::assertSentCount(1);
});

// ── 4. Coordonnées résolues pour tous les canaux ────────────────────────────

test('le service résout les coordonnées du destinataire retenu', function () {
    receptionAvecCaisse();
    Mail::fake();

    $client = clientFinal();
    $agent  = mandataire();

    soumetReservation($client, $agent, CheckinCodeNotifier::TO_BOOKER);

    $booking = \App\Models\Booking::latest('id')->first();
    $envoi = app(CheckinCodeNotifier::class)->send($booking, CheckinCodeNotifier::TO_BOOKER);

    // Le numéro est résolu au même endroit que l'adresse : c'est ce dont un
    // envoi WhatsApp aura besoin, sans redécider du destinataire.
    expect($envoi['type'])->toBe(CheckinCodeNotifier::TO_BOOKER)
        ->and($envoi['email'])->toBe('serge@agence.cm')
        ->and($envoi['phone'])->toBe('+237690000002')
        ->and($envoi['sent'])->toContain('email');
});

test('un mandataire demandé mais absent du dossier retombe sur le client', function () {
    receptionAvecCaisse();
    Mail::fake();

    soumetReservation(clientFinal(), null, null);

    $booking = \App\Models\Booking::latest('id')->first();
    $envoi = app(CheckinCodeNotifier::class)->send($booking, CheckinCodeNotifier::TO_BOOKER);

    expect($envoi['type'])->toBe(CheckinCodeNotifier::TO_CUSTOMER)
        ->and($envoi['email'])->toBe('aminatou@exemple.cm');
});

test('un destinataire sans adresse est signalé plutôt que silencieux', function () {
    receptionAvecCaisse();
    Mail::fake();

    $client = Customer::create(['first_name' => 'Sans', 'last_name' => 'Courriel', 'phone' => '+237690000003']);

    soumetReservation($client, null, null);

    Mail::assertNothingSent();
    expect(session('success'))->toContain('aucun code');
});
