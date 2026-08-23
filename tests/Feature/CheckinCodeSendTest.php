<?php

use App\Mail\CheckinCodeMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\CheckinCodeNotifier;
use App\Support\MailIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Envoi du code depuis la fenêtre qui suit la création, et habillage du message.
 *
 * Le code part automatiquement, mais la réception doit pouvoir le renvoyer sans
 * rouvrir le dossier — un client qui dit n'avoir rien reçu ne peut pas attendre
 * qu'on recrée sa réservation. Et le message doit arriver aux couleurs de
 * l'établissement, pas à celles d'un autre.
 */

/** Réservation confirmée, avec code, tenue par un mandataire. */
function reservationAvecCode(string $destinataire = CheckinCodeNotifier::TO_BOOKER): Booking
{
    receptionAvecCaisse();

    $client = clientFinal();
    $agent  = mandataire();

    soumetReservation($client, $agent, $destinataire);

    return Booking::latest('id')->firstOrFail();
}

test('le destinataire choisi est mémorisé sur la réservation', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_BOOKER);

    expect($booking->code_recipient)->toBe(CheckinCodeNotifier::TO_BOOKER)
        ->and($booking->checkin_code)->not->toBeNull();
});

test('la fenêtre de confirmation propose l\'envoi au destinataire retenu', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_BOOKER);

    // La fenêtre n'apparaît qu'avec le code en session : on suit la redirection.
    $html = test()->withSession(['checkin_code' => $booking->checkin_code])
        ->get(route('bookings.show', $booking))->assertOk()->getContent();

    expect($html)->toContain('Destinataire du code')
        ->and($html)->toContain('Mandataire')
        ->and($html)->toContain('serge@agence.cm')
        ->and($html)->toContain('Envoyer le code par email')
        ->and($html)->toContain(route('bookings.checkin_code.send', $booking));
});

test('le bouton envoie le code au destinataire mémorisé', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_BOOKER);

    $reponse = test()->postJson(route('bookings.checkin_code.send', $booking))->assertOk();

    expect($reponse->json('ok'))->toBeTrue()
        ->and($reponse->json('email'))->toBe('serge@agence.cm');

    // Un envoi à la création, un second au clic : deux au total, jamais au client.
    Mail::assertSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('serge@agence.cm'));
    Mail::assertNotSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('aminatou@exemple.cm'));
});

test('l\'écran ne peut pas détourner le code vers une autre adresse', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_CUSTOMER);

    // Le destinataire vient de la réservation : ce que poste le navigateur est ignoré.
    test()->postJson(route('bookings.checkin_code.send', $booking), [
        'recipient_type' => CheckinCodeNotifier::TO_BOOKER,
        'email'          => 'pirate@exemple.cm',
    ])->assertOk();

    Mail::assertNotSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('pirate@exemple.cm'));
    Mail::assertNotSent(CheckinCodeMail::class, fn ($mail) => $mail->hasTo('serge@agence.cm'));
});

test('un destinataire sans adresse renvoie une erreur explicite', function () {
    Mail::fake();
    receptionAvecCaisse();

    $client = Customer::create(['first_name' => 'Sans', 'last_name' => 'Courriel', 'phone' => '+237690000009']);
    soumetReservation($client, null, null);

    $reponse = test()->postJson(route('bookings.checkin_code.send', Booking::latest('id')->first()))
        ->assertStatus(422);

    expect($reponse->json('ok'))->toBeFalse()
        ->and($reponse->json('message'))->toContain('Aucune adresse');
});

test('une réservation sans code ne déclenche aucun envoi', function () {
    Mail::fake();

    $booking = reservationAvecCode();
    $booking->update(['checkin_code' => null]);

    test()->postJson(route('bookings.checkin_code.send', $booking))->assertStatus(422);

    // Seul l'envoi initial a eu lieu ; le clic n'a rien produit.
    Mail::assertSentCount(1);
});

// ── Habillage du message ────────────────────────────────────────────────────

test('le message porte les couleurs et le nom de l\'établissement', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_CUSTOMER);

    Tenant::first()->update([
        'name'     => 'Résidence Kribi Plage',
        'address'  => 'Kribi, Sud Cameroun',
        'phone'    => '+237 233 000 000',
        'settings' => array_merge((array) Tenant::first()->settings, [
            'theme' => ['primary' => '#0F766E', 'secondary' => '#F5D0A9', 'accent' => '#CFF5EE'],
        ]),
    ]);
    MailIdentity::forget();

    $html = (new CheckinCodeMail($booking, $booking->customer))->render();

    expect($html)->toContain('RÉSIDENCE KRIBI PLAGE')
        ->and($html)->toContain('#0F766E')
        ->and($html)->toContain('#F5D0A9')
        ->and($html)->toContain('Kribi, Sud Cameroun')
        // Plus aucune trace de l'établissement codé en dur.
        ->and($html)->not->toContain('VILLA BOUTANGA');
});

test('un message adressé au mandataire le salue lui, et nomme le client', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_BOOKER);

    $html = (new CheckinCodeMail($booking, $booking->booker))->render();

    expect($html)->toContain('Bonjour Serge')
        ->and($html)->toContain('au nom de')
        ->and($html)->toContain('Aminatou Njoya');
});

test('un message adressé au client le salue directement', function () {
    Mail::fake();

    $booking = reservationAvecCode(CheckinCodeNotifier::TO_CUSTOMER);

    $html = (new CheckinCodeMail($booking, $booking->customer))->render();

    expect($html)->toContain('Bonjour Aminatou')
        ->and($html)->not->toContain('au nom de');
});

test('un envoi refusé par le fournisseur annonce la vraie raison', function () {
    Mail::fake();
    $booking = reservationAvecCode(CheckinCodeNotifier::TO_CUSTOMER);

    // Le fournisseur rejette le message : l'adresse existe pourtant bien.
    Mail::shouldReceive('to')->andThrow(new \RuntimeException(
        'The gmail.com domain is not verified.'
    ));

    $reponse = test()->postJson(route('bookings.checkin_code.send', $booking))->assertStatus(422);

    expect($reponse->json('message'))
        ->toContain('Envoi refusé')
        ->toContain('gmail.com domain is not verified')
        // Le défaut d'origine : accuser une adresse manquante alors qu'elle existe.
        ->not->toContain('Aucune adresse');
});
